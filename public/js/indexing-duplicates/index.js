/**
 * Indexing Duplicates — read-only list over the indexing_duplicates table.
 *
 * The records shown here have been deleted from file_indexings, fileNumber,
 * customers_staging and entities_staging, so the details modal renders the stored
 * snapshot: it is the only surviving copy of what was removed.
 */
const config = window.indexingDuplicatesConfig || {};

const state = {
  page: 1,
  perPage: 20,
  sort: 'moved_at',
  direction: 'desc',
  search: '',
  isLoading: false,
  lastPage: 1,
  // S/N of the first row on the current page, so numbering runs on across pages.
  rowOffset: 1,
};

const dom = {
  tbody: document.getElementById('dup-tbody'),
  table: document.getElementById('dup-table'),
  search: document.getElementById('dup-search'),
  perPage: document.getElementById('dup-per-page'),
  prev: document.getElementById('dup-prev'),
  next: document.getElementById('dup-next'),
  page: document.getElementById('dup-page'),
  summary: document.getElementById('dup-summary'),
  modal: document.getElementById('dup-modal'),
  modalTitle: document.getElementById('dup-modal-title'),
  modalSubtitle: document.getElementById('dup-modal-subtitle'),
  modalBody: document.getElementById('dup-modal-body'),
  modalClose: document.getElementById('dup-modal-close'),
  statTotal: document.getElementById('stat-total'),
  statToday: document.getElementById('stat-today'),
  statRegistries: document.getElementById('stat-registries'),
  statMls: document.getElementById('stat-mls'),
};

const COLUMN_COUNT = dom.table ? dom.table.querySelectorAll('thead th').length : 10;

function escapeHtml(value) {
  const text = value === undefined || value === null ? '' : String(value);
  return text.replace(/[&<>"']/g, (c) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
  }[c]));
}

function dash(value) {
  const text = value === undefined || value === null ? '' : String(value).trim();
  return text === '' ? '<span class="text-slate-300">—</span>' : escapeHtml(text);
}

function debounce(callback, delay) {
  let timeoutId;
  return (...args) => {
    window.clearTimeout(timeoutId);
    timeoutId = window.setTimeout(() => callback(...args), delay);
  };
}

function refreshIcons() {
  if (window.lucide && typeof window.lucide.createIcons === 'function') {
    window.lucide.createIcons();
  }
}

function renderRows(rows) {
  if (!rows.length) {
    dom.tbody.innerHTML = `<tr><td colspan="${COLUMN_COUNT}" class="p-8 text-center text-sm text-slate-400">
        No duplicate records have been moved yet.
      </td></tr>`;
    return;
  }

  const cell = 'px-4 py-3 whitespace-nowrap text-slate-600';

  dom.tbody.innerHTML = rows.map((row, index) => {
    // Same rule the indexed files views use: prefer the stored location string,
    // otherwise compose it from plot / district / LGA.
    const locationParts = [row.plot_number, row.district, row.lga]
      .filter((v) => v && String(v).trim() !== '' && String(v).trim() !== '-');
    const location = (row.location && row.location !== '-') ? row.location : locationParts.join(', ');

    return `
      <tr class="hover:bg-slate-50/60 transition-colors">
        <td class="px-4 py-3 whitespace-nowrap text-slate-400 text-xs">${state.rowOffset + index}</td>
        <td class="px-4 py-3 whitespace-nowrap">
          <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-mono font-semibold bg-slate-100 text-slate-600 border border-slate-200"
                title="Original file_indexings.id">${dash(row.file_indexing_id)}</span>
        </td>
        <td class="px-4 py-3 whitespace-nowrap">
          <span class="font-semibold text-slate-800">${escapeHtml(row.file_number)}</span>
        </td>
        <td class="px-4 py-3 text-slate-700 max-w-[18rem] truncate" title="${escapeHtml(row.file_title || '')}">${dash(row.file_title)}</td>
        <td class="${cell}">${dash(row.land_use_type)}</td>
        <td class="px-4 py-3 text-slate-600 max-w-[16rem] truncate" title="${escapeHtml(location)}">${dash(location)}</td>
        <td class="${cell} text-xs">${dash(row.indexed_by)}</td>
        <td class="${cell} text-xs">${dash(row.indexed_at)}</td>
        <td class="${cell} text-xs">${dash(row.moved_by)}</td>
        <td class="px-4 py-3 text-right">
          <button type="button" class="dup-details-btn inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-slate-200 bg-white text-xs font-medium text-slate-600 hover:text-blue-600 hover:border-blue-200 transition-all"
                  data-id="${escapeHtml(row.id)}">
            <i data-lucide="file-search" class="h-3.5 w-3.5"></i>
            Snapshot
          </button>
        </td>
      </tr>
    `;
  }).join('');

  refreshIcons();
}

function updateSortIndicators() {
  dom.table.querySelectorAll('thead th[data-sort]').forEach((header) => {
    const isActive = header.dataset.sort === state.sort;
    let arrow = header.querySelector('.sort-indicator');
    if (!arrow) {
      arrow = document.createElement('span');
      arrow.className = 'sort-indicator ml-1 text-[10px]';
      header.appendChild(arrow);
    }
    arrow.textContent = isActive ? (state.direction === 'asc' ? '▲' : '▼') : '';
    header.classList.toggle('text-blue-700', isActive);
    header.classList.toggle('text-gray-700', !isActive);
  });
}

function updatePagination(meta) {
  if (!meta) {
    dom.summary.textContent = '';
    dom.page.textContent = 'Page 1';
    dom.prev.disabled = true;
    dom.next.disabled = true;
    return;
  }

  state.lastPage = meta.last_page || 1;
  dom.summary.textContent = meta.total === 0
    ? 'No records'
    : `Showing ${meta.from}–${meta.to} of ${meta.total} moved record(s)`;
  dom.page.textContent = `Page ${meta.page} of ${state.lastPage}`;
  dom.prev.disabled = meta.page <= 1;
  dom.next.disabled = meta.page >= state.lastPage;
}

async function loadTable() {
  if (!config.listUrl) {
    dom.tbody.innerHTML = `<tr><td colspan="${COLUMN_COUNT}" class="p-6 text-center text-sm text-red-500">List endpoint is not configured.</td></tr>`;
    return;
  }

  state.isLoading = true;
  dom.tbody.innerHTML = `<tr><td colspan="${COLUMN_COUNT}" class="p-8 text-center text-sm text-slate-400">Loading…</td></tr>`;

  const params = new URLSearchParams({
    page: String(state.page),
    per_page: String(state.perPage),
    sort: state.sort,
    direction: state.direction,
  });
  if (state.search.trim() !== '') {
    params.set('search', state.search.trim());
  }

  try {
    const response = await fetch(`${config.listUrl}?${params.toString()}`, {
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
    });
    if (!response.ok) {
      throw new Error(`Request failed with ${response.status}`);
    }
    const payload = await response.json();
    state.rowOffset = Number(payload.meta && payload.meta.from) || 1;
    renderRows(payload.data || []);
    updatePagination(payload.meta);
    updateSortIndicators();
  } catch (error) {
    console.error('Failed to load indexing duplicates:', error);
    dom.tbody.innerHTML = `<tr><td colspan="${COLUMN_COUNT}" class="p-6 text-center text-sm text-red-500">
        Unable to load indexing duplicates. Please try again.
      </td></tr>`;
    updatePagination(null);
  } finally {
    state.isLoading = false;
  }
}

async function loadStats() {
  if (!config.statsUrl) {
    return;
  }

  try {
    const response = await fetch(config.statsUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    if (!response.ok) {
      return;
    }
    const stats = await response.json();
    dom.statTotal.textContent = stats.total ?? 0;
    dom.statToday.textContent = stats.today ?? 0;
    dom.statRegistries.textContent = stats.registries ?? 0;
    dom.statMls.textContent = stats.mls_retained ?? 0;
  } catch (error) {
    console.error('Failed to load indexing duplicate stats:', error);
  }
}

function renderSnapshotTable(label, rows) {
  const heading = `<div class="flex items-center gap-2 mb-2">
      <span class="text-xs font-bold text-slate-700 uppercase tracking-wider">${escapeHtml(label)}</span>
      <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-rose-50 text-rose-600 border border-rose-200">
        ${rows.length} row(s) deleted
      </span>
    </div>`;

  if (!rows.length) {
    return `<div>${heading}<p class="text-xs text-slate-400">Nothing was deleted from this table.</p></div>`;
  }

  const body = rows.map((row) => {
    const cells = Object.entries(row).map(([key, value]) => `
        <div class="flex flex-col py-1.5 border-b border-slate-50 last:border-0">
          <dt class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">${escapeHtml(key)}</dt>
          <dd class="text-xs text-slate-700 break-words">${escapeHtml(value)}</dd>
        </div>`).join('');

    return `<div class="rounded-xl border border-slate-100 bg-slate-50/40 p-3">
        <dl class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-4">${cells}</dl>
      </div>`;
  }).join('');

  return `<div>${heading}<div class="space-y-2">${body}</div>
      <p class="text-[10px] text-slate-400 mt-1.5">Empty columns are hidden.</p>
    </div>`;
}

async function openDetails(id) {
  if (!config.showUrlTemplate) {
    return;
  }

  dom.modal.classList.remove('hidden');
  dom.modal.classList.add('flex');
  dom.modalTitle.textContent = 'Loading…';
  dom.modalSubtitle.textContent = '';
  dom.modalBody.innerHTML = '<p class="text-sm text-slate-400">Loading snapshot…</p>';

  try {
    const response = await fetch(config.showUrlTemplate.replace('__ID__', encodeURIComponent(id)), {
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
    });
    const payload = await response.json();

    if (!response.ok || !payload.success) {
      throw new Error(payload.message || 'Unable to load the snapshot.');
    }

    const r = payload.record;
    dom.modalTitle.textContent = r.file_number;
    dom.modalSubtitle.textContent = [
      r.file_title,
      r.registry ? `registry ${r.registry}` : null,
      r.moved_by ? `moved by ${r.moved_by}` : null,
      r.moved_at,
    ].filter(Boolean).join(' · ');

    const meta = `
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div class="rounded-xl border border-slate-100 p-3">
          <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Duplicate Of</p>
          <p class="text-sm text-slate-800 mt-0.5">${dash(r.duplicate_of)}</p>
        </div>
        <div class="rounded-xl border border-slate-100 p-3">
          <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Reason</p>
          <p class="text-sm text-slate-800 mt-0.5">${dash(r.reason)}</p>
        </div>
        <div class="rounded-xl border border-slate-100 p-3">
          <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Originally Indexed</p>
          <p class="text-sm text-slate-800 mt-0.5">${dash(r.indexed_by)}${r.indexed_at ? ` · ${escapeHtml(r.indexed_at)}` : ''}</p>
        </div>
        <div class="rounded-xl border border-slate-100 p-3">
          <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">File Numbers Matched</p>
          <p class="text-sm text-slate-800 mt-0.5">${(r.matched_numbers || []).length ? escapeHtml((r.matched_numbers || []).join(', ')) : '<span class="text-slate-300">—</span>'}</p>
        </div>
      </div>`;

    const mlsNote = r.mls_file_no_retained
      ? `<div class="rounded-xl border border-amber-200 bg-amber-50 p-3">
          <p class="text-xs text-amber-800">
            <strong>Commissioning record kept.</strong> A row for this file still exists in
            <code>mls_file_no</code>. That table is outside the cascade, so the file remains commissioned.
          </p>
        </div>`
      : '';

    const childRows = Object.entries(payload.child_rows || {});
    const childNote = childRows.length
      ? `<div class="rounded-xl border border-slate-100 p-3">
          <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1.5">Linked Rows Also Removed</p>
          <div class="flex flex-wrap gap-1.5">
            ${childRows.map(([table, count]) => `<span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-slate-50 text-slate-600 border border-slate-200">${escapeHtml(table)}: ${escapeHtml(count)}</span>`).join('')}
          </div>
        </div>`
      : '';

    const retained = Object.entries(payload.retained_references || {});
    const retainedNote = retained.length
      ? `<div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3">
          <p class="text-xs text-emerald-800">
            <strong>Tracker records kept.</strong> The file's movement history was left in place
            and is still queryable:
            ${retained.map(([table, count]) => `<code>${escapeHtml(table)}</code> (${escapeHtml(count)} row(s))`).join(', ')}.
          </p>
        </div>`
      : '';

    const tables = Object.entries(payload.tables || {})
      .map(([label, rows]) => renderSnapshotTable(label, rows))
      .join('');

    dom.modalBody.innerHTML = meta + mlsNote + retainedNote + childNote + tables;
    refreshIcons();
  } catch (error) {
    console.error('Failed to load snapshot:', error);
    dom.modalBody.innerHTML = `<p class="text-sm text-red-500">${escapeHtml(error.message)}</p>`;
  }
}

function closeModal() {
  dom.modal.classList.add('hidden');
  dom.modal.classList.remove('flex');
  dom.modalBody.innerHTML = '';
}

function attachEventListeners() {
  dom.search.addEventListener('input', debounce((event) => {
    state.search = event.target.value;
    state.page = 1;
    loadTable();
  }, 350));

  dom.perPage.addEventListener('change', (event) => {
    state.perPage = Number(event.target.value) || 20;
    state.page = 1;
    loadTable();
  });

  dom.prev.addEventListener('click', () => {
    if (state.page > 1) {
      state.page -= 1;
      loadTable();
    }
  });

  dom.next.addEventListener('click', () => {
    if (state.page < state.lastPage) {
      state.page += 1;
      loadTable();
    }
  });

  dom.table.querySelectorAll('thead th[data-sort]').forEach((header) => {
    header.addEventListener('click', () => {
      const key = header.dataset.sort;
      if (!key || state.isLoading) {
        return;
      }
      if (state.sort === key) {
        state.direction = state.direction === 'asc' ? 'desc' : 'asc';
      } else {
        state.sort = key;
        state.direction = key === 'moved_at' || key === 'indexed_at' ? 'desc' : 'asc';
      }
      state.page = 1;
      loadTable();
    });
  });

  dom.tbody.addEventListener('click', (event) => {
    const button = event.target.closest('.dup-details-btn');
    if (button) {
      openDetails(button.getAttribute('data-id'));
    }
  });

  dom.modalClose.addEventListener('click', closeModal);
  dom.modal.addEventListener('click', (event) => {
    if (event.target === dom.modal) {
      closeModal();
    }
  });
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && !dom.modal.classList.contains('hidden')) {
      closeModal();
    }
  });
}

attachEventListeners();
loadTable();
loadStats();
