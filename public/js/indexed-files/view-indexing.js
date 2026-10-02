import { genderAttr, genderRowClass } from './gender.js';

const config = window.indexedFilesConfig || {};

const state = {
  page: 1,
  perPage: 20,
  sort: 'id',
  direction: 'desc',
  search: '',
  isLoading: false,
  hasLoadedInitialData: false,
};
 
function escapeHtml(str) {
  if (typeof str !== 'string') return str;
  return str.replace(/[&<>"']/g, function (m) {
    return {
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#39;',
    }[m];
  });
}

function buildLandUseBadge(landUse) {
  if (!landUse || landUse === 'N/A' || landUse === '-') {
    return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold tracking-wider uppercase bg-gray-100 text-gray-500 border border-gray-200">N/A</span>';
  }

  const raw = landUse.toString().trim().toUpperCase();
  const normMap = {
    'COM': 'COMMERCIAL',
    'RES': 'RESIDENTIAL',
    'IND': 'INDUSTRIAL',
    'MIX': 'MIXED USE',
    'PUB': 'PUBLIC',
    'AGR': 'AGRICULTURAL',
    'EDU': 'EDUCATIONAL',
    'REL': 'RELIGIOUS'
  };

  const displayLabel = normMap[raw] || raw;

  const palette = {
    'COMMERCIAL': 'bg-amber-50 text-amber-700 border-amber-200',
    'RESIDENTIAL': 'bg-emerald-50 text-emerald-700 border-emerald-200',
    'INDUSTRIAL': 'bg-slate-50 text-slate-700 border-slate-200',
    'MIXED USE': 'bg-purple-50 text-purple-700 border-purple-200',
    'PUBLIC': 'bg-blue-50 text-blue-700 border-blue-200',
    'AGRICULTURAL': 'bg-lime-50 text-lime-700 border-lime-200',
    'EDUCATIONAL': 'bg-cyan-50 text-cyan-700 border-cyan-200',
    'RELIGIOUS': 'bg-rose-50 text-rose-700 border-rose-200'
  };

  const className = palette[displayLabel] || 'bg-gray-50 text-gray-600 border-gray-200';

  return `<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold tracking-wider uppercase border ${className}">${displayLabel}</span>`;
}

function logDebug(message) {
  const debugEl = document.getElementById('view-indexing-debug');
  if (debugEl) {
    debugEl.classList.remove('hidden');
    const timestamp = new Date().toLocaleTimeString();
    debugEl.innerHTML += `<div>[${timestamp}] ${message}</div>`;
    console.log(`[ViewIndexing] ${message}`);
  } else {
    console.log(`[ViewIndexing] ${message}`);
  }
}

let dom = {};

const headers = [];
let tableAbortController = null;

function initDom() {
  dom = {
    tableBody: document.getElementById('view-indexing-tbody'),
    searchInput: document.getElementById('view-indexing-search'),
    perPageSelect: document.getElementById('view-indexing-per-page'),
    prevButton: document.getElementById('view-indexing-prev'),
    nextButton: document.getElementById('view-indexing-next'),
    pageLabel: document.getElementById('view-indexing-page'),
    summaryText: document.getElementById('view-indexing-summary'),
    table: document.getElementById('view-indexing-table'),
  };

  if (dom.table) {
    const tableHeaders = dom.table.querySelectorAll('thead th[data-sort]');
    if (tableHeaders) {
      headers.push(...Array.from(tableHeaders));
    }
  }
}

function createParams() {
  const params = new URLSearchParams({
    page: String(state.page),
    per_page: String(state.perPage),
    sort: state.sort,
    direction: state.direction,
  });

  if (state.search.trim() !== '') {
    params.set('search', state.search.trim());
  }

  return params;
}

async function fetchJson(url, signal) {
  const response = await fetch(url, {
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
    signal,
  });

  if (!response.ok) {
    const message = await response.text();
    throw new Error(message || `Request failed with status ${response.status}`);
  }

  return response.json();
}

function setLoading(isLoading) {
  state.isLoading = isLoading;
  if (!dom.tableBody) return;

  if (isLoading) {
    dom.tableBody.innerHTML = '<tr><td colspan="10" class="p-6 text-center text-sm text-gray-500">Loading records...</td></tr>';
  }
}

function renderRows(rows) {
  if (!dom.tableBody) return;

  if (!Array.isArray(rows) || rows.length === 0) {
    dom.tableBody.innerHTML = '<tr><td colspan="10" class="p-6 text-center text-sm text-gray-500">No records found.</td></tr>';
    return;
  }

  const standardCellClass = 'p-3 whitespace-nowrap text-gray-600';

  const fragments = rows.map((row) => {
    return `
      <tr class="hover:bg-gray-50 ${genderRowClass(row.gender)}" data-gender="${escapeHtml(genderAttr(row.gender))}">
        <td class="p-3 whitespace-nowrap font-medium ${row.is_temp_fallback ? 'text-red-600 font-bold' : 'text-blue-600'}">${escapeHtml(row.file_number)}</td>
        <td class="${standardCellClass}">
          ${row.has_related_files ? `
            <div class="flex flex-col gap-1">
              <button type="button" class="view-related-files-btn inline-flex items-center gap-1.5 px-3 py-1.5 text-[10px] font-bold text-blue-700 bg-blue-50 border border-blue-200 rounded-lg hover:bg-blue-600 hover:text-white hover:border-blue-600 transition-all shadow-sm" data-id="${row.id}">
                <i data-lucide="link" class="w-3 h-3"></i>
                <span>${escapeHtml(row.related_file_display && row.related_file_display !== '-' ? row.related_file_display : 'View Related Files')}</span>
              </button>
            </div>
          ` : '<span class="text-gray-400 font-medium text-[10px] uppercase">None</span>'}
        </td>
        <td class="${standardCellClass}">${escapeHtml(row.file_title)}</td>
        <td class="${standardCellClass}">${escapeHtml(row.current_holder)}</td>
        <td class="${standardCellClass}">${escapeHtml(row.original_holder)}</td>
        <td class="${standardCellClass}">${escapeHtml(row.plot_number)}</td>
        <td class="p-3 whitespace-nowrap">${buildLandUseBadge(row.land_use_type)}</td>
        <td class="${standardCellClass}">${escapeHtml(row.district)}</td>
        <td class="${standardCellClass}">${escapeHtml(row.lga)}</td>
        <td class="${standardCellClass}">${escapeHtml(row.state)}</td>
      </tr>
    `;
  }).join('');

  dom.tableBody.innerHTML = fragments;

  if (window.lucide && typeof window.lucide.createIcons === 'function') {
    window.lucide.createIcons();
  }
}

function updatePagination(meta) {
  if (!meta) {
    if (dom.summaryText) dom.summaryText.textContent = 'Showing -- of -- results';
    if (dom.pageLabel) dom.pageLabel.textContent = '--';
    if (dom.prevButton) dom.prevButton.disabled = true;
    if (dom.nextButton) dom.nextButton.disabled = true;
    return;
  }

  const { current_page: currentPage, per_page: perPage, total, last_page: lastPage } = meta;
  const start = total === 0 ? 0 : (currentPage - 1) * perPage + 1;
  const end = total === 0 ? 0 : Math.min(start + perPage - 1, total);

  if (dom.summaryText) dom.summaryText.innerHTML = `Showing <span class="text-blue-600 font-semibold">${start.toLocaleString()}</span> to <span class="text-blue-600 font-semibold">${end.toLocaleString()}</span> of <span class="text-blue-600 font-semibold">${total.toLocaleString()}</span> results`;
  if (dom.pageLabel) dom.pageLabel.textContent = currentPage.toLocaleString();
  if (dom.prevButton) dom.prevButton.disabled = currentPage <= 1;
  if (dom.nextButton) dom.nextButton.disabled = currentPage >= lastPage;
}

async function loadTable() {
  logDebug('loadTable called');
  if (!config.viewListUrl) {
    logDebug('ERROR: config.viewListUrl is missing');
    if (dom.tableBody) dom.tableBody.innerHTML = '<tr><td colspan="10" class="p-6 text-center text-sm text-red-500">View list endpoint is not configured.</td></tr>';
    return;
  }

  try {
    setLoading(true);
    const params = createParams();
    if (tableAbortController) {
      tableAbortController.abort();
    }
    tableAbortController = new AbortController();
    const url = `${config.viewListUrl}?${params.toString()}`;
    logDebug(`Fetching: ${url}`);

    const payload = await fetchJson(url, tableAbortController.signal);
    logDebug(`Fetch success. Rows: ${payload.data ? payload.data.length : 0}`);

    renderRows(payload.data || []);
    updatePagination(payload.meta);
  } catch (error) {
    if (error.name === 'AbortError') {
      return;
    }
    logDebug(`ERROR: ${error.message}`);
    console.error('Failed to load view indexing records:', error);
    if (dom.tableBody) dom.tableBody.innerHTML = '<tr><td colspan="10" class="p-6 text-center text-sm text-red-500">Unable to load records. Please try again.</td></tr>';
    updatePagination(null);
  } finally {
    setLoading(false);
  }
}

function openRelatedFilesModal(id) {
  const modal = document.getElementById('related-files-modal');
  const tbody = document.getElementById('related-files-table-body');
  const closeBtns = [
    document.getElementById('close-related-modal-btn'),
    document.getElementById('close-related-modal-footer-btn')
  ];

  if (!modal || !tbody) {
    console.error('Modal or tbody not found');
    return;
  }

  // Show modal and loading state
  modal.classList.remove('hidden');
  const parentContainer = document.getElementById('parent-file-number-container');
  if (parentContainer) parentContainer.classList.add('hidden');

  tbody.innerHTML = '<tr><td colspan="5" class="px-4 py-8 text-center text-sm"><i data-lucide="loader" class="h-5 w-5 animate-spin inline-block mr-2"></i>Loading related files...</td></tr>';
  if (window.lucide) window.lucide.createIcons();

  // Close handlers
  const close = () => {
    modal.classList.add('hidden');
    closeBtns.forEach(btn => btn?.removeEventListener('click', close));
  };
  closeBtns.forEach(btn => btn?.addEventListener('click', close));

  // Fetch data
  const url = `${window.location.origin}/api/indexed-files/related-files/${id}`;
  fetch(url, {
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
      const parentContainer = document.getElementById('parent-file-number-container');
      const parentBadge = document.getElementById('parent-file-number-badge');

      if (data.success && data.data.length > 0) {
        // Show Parent File Number
        const firstRow = data.data[0];
        if (firstRow && firstRow.main_file_number && parentContainer && parentBadge) {
          parentBadge.textContent = firstRow.main_file_number;
          if (firstRow.main_is_temp_fallback) {
            parentBadge.className = 'inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold bg-red-50 text-red-700 border border-red-100';
          } else {
            parentBadge.className = 'inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold bg-blue-50 text-blue-700 border border-blue-100';
          }
          parentContainer.classList.remove('hidden');
        }

        tbody.innerHTML = data.data.map((file, idx) => `
          <tr class="hover:bg-gray-50 transition-colors">
            <td class="px-4 py-3 text-sm text-gray-600 font-medium">${idx + 1}</td>
            <td class="px-4 py-3 text-sm">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-blue-50 text-blue-700 border border-blue-200">
                    ${escapeHtml(file.file_number)}
                </span>
            </td>
            <td class="px-4 py-3 text-sm text-gray-700 font-semibold">${escapeHtml(file.file_title)}</td>
            <td class="px-4 py-3 text-sm text-gray-600">${escapeHtml(file.location || '-')}</td>
            <td class="px-4 py-3 text-xs text-gray-500">
                <div class="flex flex-col gap-1">
                    <span class="font-medium">Plot: ${escapeHtml(file.plot_number || '-')}</span>
                    <span>TP: ${escapeHtml(file.tp_no || '-')} | LPKN: ${escapeHtml(file.lpkn_no || '-')}</span>
                </div>
            </td>
          </tr>
        `).join('');
      } else {
        if (parentContainer) parentContainer.classList.add('hidden');
        const msg = data.message || 'No related files found for this record.';
        tbody.innerHTML = `<tr><td colspan="5" class="px-4 py-8 text-center text-sm ${data.success ? 'text-gray-500' : 'text-red-500'} font-medium">${msg}</td></tr>`;
      }
    })
    .catch(err => {
      console.error(err);
      tbody.innerHTML = '<tr><td colspan="5" class="px-4 py-8 text-center text-sm text-red-500 font-medium">Error loading related files. Please try again.</td></tr>';
    });
}

function attachEventListeners() {
  if (dom.searchInput) {
    const debouncedSearch = debounce((event) => {
      state.search = event.target.value;
      state.page = 1;
      loadTable();
    }, 350);

    dom.searchInput.addEventListener('input', debouncedSearch);
  }

  if (dom.perPageSelect) {
    dom.perPageSelect.addEventListener('change', (event) => {
      const value = Number(event.target.value) || 20;
      state.perPage = Math.max(1, Math.min(value, 100));
      state.page = 1;
      loadTable();
    });
  }

  if (dom.prevButton) {
    dom.prevButton.addEventListener('click', () => {
      if (state.page > 1 && !state.isLoading) {
        state.page -= 1;
        loadTable();
      }
    });
  }

  if (dom.nextButton) {
    dom.nextButton.addEventListener('click', () => {
      if (!state.isLoading) {
        state.page += 1;
        loadTable();
      }
    });
  }

  if (dom.tableBody) {
    dom.tableBody.addEventListener('click', (event) => {
      const relatedBtn = event.target.closest('.view-related-files-btn');
      if (relatedBtn) {
        event.preventDefault();
        event.stopPropagation();
        const id = relatedBtn.getAttribute('data-id');
        openRelatedFilesModal(id);
      }
    });
  }

  headers.forEach((header) => {
    header.addEventListener('click', () => {
      const sortKey = header.dataset.sort;
      if (!sortKey || state.isLoading) {
        return;
      }

      if (state.sort === sortKey) {
        state.direction = state.direction === 'asc' ? 'desc' : 'asc';
      } else {
        state.sort = sortKey;
        state.direction = 'asc';
      }

      state.page = 1;
      loadTable();
    });
  });

  // Listen for tab activation to load data if not loaded
  const tabButton = document.querySelector('[data-indexed-tab="view-indexing"]');
  if (tabButton) {
    tabButton.addEventListener('click', () => {
      logDebug('Tab clicked');
      // If the table body is empty (or has placeholder), load.
      // Or better yet, just blindly load if we want to refresh.
      // But let's check if it's already loading or has data.

      // Simple check: if rows > 1 (header + data), maybe don't reload?
      // But for now, let's load every time the tab is clicked? No, that's annoying.
      // Let's load only if it hasn't been loaded properly yet.
      const hasData = dom.tableBody.querySelectorAll('tr').length > 1 || (dom.tableBody.querySelector('tr') && !dom.tableBody.textContent.includes('Loading'));

      // If it's showing "Loading..." or empty, load it.
      // Or if we want to ensure data freshness, maybe we should just clear and load?
      // Let's use a flag.
      if (!state.hasLoadedInitialData) {
        loadTable();
        state.hasLoadedInitialData = true;
      }
    });
  }
}

function debounce(callback, delay) {
  let timeoutId;
  return (...args) => {
    window.clearTimeout(timeoutId);
    timeoutId = window.setTimeout(() => callback(...args), delay);
  };
}


function bootstrap() {
  initDom();
  logDebug('view-indexing.js bootstrap started');
  logDebug(`Config viewListUrl: ${config.viewListUrl}`);

  if (!dom.tableBody) {
    logDebug('CRITICAL: tableBody not found');
    console.error('CRITICAL: View Indexing table body not found. The feature will not work.');
    return;
  }
  logDebug('DOM initialized successfully');

  if (dom.perPageSelect) {
    state.perPage = Number(dom.perPageSelect.value) || state.perPage;
  }

  attachEventListeners();

  // Lazy load: Check if tab is already active
  const tabButton = document.querySelector('[data-indexed-tab="view-indexing"]');
  if (tabButton && tabButton.classList.contains('indexed-tab-active')) {
    loadTable();
    state.hasLoadedInitialData = true;
  }
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', bootstrap, { once: true });
} else {
  bootstrap();
}
