/**
 * Moved Transactions — read-only list over the moved_transactions table.
 *
 * Each row is a PRA / File History / CofO transaction that "Move Transaction" took
 * off an indexed file and deleted from its source table; the snapshot modal shows
 * the stored copy, which is the only one left.
 */
const config = window.movedTransactionsConfig || {};

const state = {
  page: 1,
  perPage: 20,
  sort: 'moved_at',
  direction: 'desc',
  search: '',
  isLoading: false,
  lastPage: 1,
  rowOffset: 1,
};

const dom = {
  tbody: document.getElementById('mt-tbody'),
  table: document.getElementById('mt-table'),
  search: document.getElementById('mt-search'),
  perPage: document.getElementById('mt-per-page'),
  prev: document.getElementById('mt-prev'),
  next: document.getElementById('mt-next'),
  page: document.getElementById('mt-page'),
  summary: document.getElementById('mt-summary'),
  modal: document.getElementById('mt-modal'),
  modalTitle: document.getElementById('mt-modal-title'),
  modalSubtitle: document.getElementById('mt-modal-subtitle'),
  modalBody: document.getElementById('mt-modal-body'),
  modalClose: document.getElementById('mt-modal-close'),
};

const COLUMN_COUNT = dom.table ? dom.table.querySelectorAll('thead th').length : 12;

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

// ── Row action menu ───────────────────────────────────────────────────────────
// One menu element on <body>, positioned against the clicked trigger, so the
// table's overflow-x-auto cannot clip it.
let actionMenu = null;

function closeActionMenu() {
  if (actionMenu) {
    actionMenu.remove();
    actionMenu = null;
  }
}

function openActionMenu(trigger) {
  closeActionMenu();

  const id = trigger.getAttribute('data-id');
  actionMenu = document.createElement('div');
  actionMenu.setAttribute('role', 'menu');
  actionMenu.style.cssText = 'position:fixed;z-index:60;min-width:190px;background:#fff;border:1px solid #e2e8f0;border-radius:12px;box-shadow:0 10px 25px rgba(15,23,42,.15);padding:6px 0;';
  actionMenu.innerHTML = `
    <button type="button" role="menuitem" class="mt-view-snapshot w-full text-left px-4 py-2 text-sm text-slate-700 hover:bg-slate-50 flex items-center gap-2" data-id="${escapeHtml(id)}">
      <i data-lucide="file-search" class="h-4 w-4 text-blue-600"></i> View Snapshot
    </button>`;
  document.body.appendChild(actionMenu);
  refreshIcons();

  const rect = trigger.getBoundingClientRect();
  const width = actionMenu.offsetWidth;
  const height = actionMenu.offsetHeight;
  const left = Math.max(8, Math.min(rect.right - width, window.innerWidth - width - 8));
  const top = rect.bottom + 4 + height > window.innerHeight ? rect.top - height - 4 : rect.bottom + 4;
  actionMenu.style.left = `${left}px`;
  actionMenu.style.top = `${Math.max(8, top)}px`;

  actionMenu.addEventListener('click', (event) => {
    const item = event.target.closest('.mt-view-snapshot');
    if (item) {
      event.stopPropagation();
      closeActionMenu();
      openDetails(item.getAttribute('data-id'));
    }
  });
}

function renderRows(rows) {
  if (!rows.length) {
    dom.tbody.innerHTML = `<tr><td colspan="${COLUMN_COUNT}" class="p-8 text-center text-sm text-slate-400">
        No transactions have been moved yet.
      </td></tr>`;
    return;
  }

  const cell = 'px-4 py-3 whitespace-nowrap text-slate-600';

  dom.tbody.innerHTML = rows.map((row, index) => `
      <tr class="hover:bg-slate-50/60 transition-colors">
        <td class="px-4 py-3 whitespace-nowrap text-slate-400 text-xs">${state.rowOffset + index}</td>
        <td class="px-4 py-3 whitespace-nowrap font-mono text-xs font-semibold text-slate-800">${dash(row.temp_file_no)}</td>
        <td class="px-4 py-3 whitespace-nowrap font-semibold text-slate-800">${dash(row.file_number)}</td>
        <td class="${cell} text-xs">${dash(row.source_label)}</td>
        <td class="px-4 py-3 text-slate-700 max-w-[14rem] truncate" title="${escapeHtml(row.transaction_type || '')}">${dash(row.transaction_type)}</td>
        <td class="px-4 py-3 text-slate-600 text-xs max-w-[16rem]">
          <div class="truncate" title="${escapeHtml(row.party_1 || '')}">${dash(row.party_1)}</div>
          <div class="truncate" title="${escapeHtml(row.party_2 || '')}">${dash(row.party_2)}</div>
        </td>
        <td class="${cell} text-xs">${dash(row.transaction_date)}</td>
        <td class="${cell} text-xs">${dash(row.reg_no)}</td>
        <td class="${cell} text-xs">${dash(row.moved_by)}</td>
        <td class="${cell} text-xs">${dash(row.moved_at)}</td>
        <td class="px-4 py-3 text-right">
          <button type="button" class="mt-actions-btn inline-flex items-center justify-center w-9 h-9 rounded-xl border border-slate-200 bg-white text-slate-400 hover:text-blue-600 hover:border-blue-200"
                  aria-label="Actions" data-id="${escapeHtml(row.id)}">
            <i data-lucide="more-vertical" class="h-4 w-4"></i>
          </button>
        </td>
      </tr>
    `).join('');

  refreshIcons();
}

function updateSortIndicators() {
  dom.table.querySelectorAll('thead th[data-sort]').forEach((header) => {
    const isActive = header.dataset.sort === state.sort;
    let arrow = header.querySelector('.sort-indicator');
    if (!arrow) {
      arrow = document.createElement('span');
      arrow.className = 'sort-indicator ml-1 text-xs';
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
    : `Showing ${meta.from}–${meta.to} of ${meta.total} moved transaction(s)`;
  dom.page.textContent = `Page ${meta.page} of ${state.lastPage}`;
  dom.prev.disabled = meta.page <= 1;
  dom.next.disabled = meta.page >= state.lastPage;
}

async function loadTable() {
  state.isLoading = true;
  closeActionMenu();
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
    console.error('Failed to load moved transactions:', error);
    dom.tbody.innerHTML = `<tr><td colspan="${COLUMN_COUNT}" class="p-6 text-center text-sm text-red-500">
        Unable to load moved transactions. Please try again.
      </td></tr>`;
    updatePagination(null);
  } finally {
    state.isLoading = false;
  }
}

async function openDetails(id) {
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
    dom.modalTitle.textContent = `${r.temp_file_no} · ${r.transaction_type || r.source_label}`;
    dom.modalSubtitle.textContent = [
      `moved off ${r.file_number}`,
      r.moved_by ? `by ${r.moved_by}` : null,
      r.moved_at,
    ].filter(Boolean).join(' · ');

    const tile = (label, value) => `
      <div class="rounded-xl border border-slate-100 p-3">
        <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">${escapeHtml(label)}</p>
        <p class="text-sm text-slate-800 mt-0.5 break-words">${dash(value)}</p>
      </div>`;

    const meta = `
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        ${tile('Temp File No', r.temp_file_no)}
        ${tile('Moved From', r.file_number)}
        ${tile('Source', `${r.source_label} #${r.source_id}`)}
        <div class="sm:col-span-2">${tile('Reason', r.reason)}</div>
      </div>`;

    const fields = Object.entries(payload.snapshot || {}).map(([key, value]) => `
        <div class="flex flex-col py-1.5 border-b border-slate-50">
          <dt class="text-xs font-bold text-slate-400 uppercase tracking-wider">${escapeHtml(key)}</dt>
          <dd class="text-xs text-slate-700 break-words">${escapeHtml(value)}</dd>
        </div>`).join('');

    dom.modalBody.innerHTML = `${meta}
      <div>
        <p class="text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Deleted ${escapeHtml(r.source_label)} record</p>
        <div class="rounded-xl border border-slate-100 bg-slate-50 p-3">
          <dl class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-4">${fields}</dl>
        </div>
        <p class="text-xs text-slate-400 mt-1.5">Empty columns are hidden.</p>
      </div>`;
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
        state.direction = key === 'moved_at' ? 'desc' : 'asc';
      }
      state.page = 1;
      loadTable();
    });
  });

  dom.tbody.addEventListener('click', (event) => {
    const trigger = event.target.closest('.mt-actions-btn');
    if (trigger) {
      event.stopPropagation();
      if (actionMenu && actionMenu.dataset.for === trigger.getAttribute('data-id')) {
        closeActionMenu();
        return;
      }
      openActionMenu(trigger);
      actionMenu.dataset.for = trigger.getAttribute('data-id');
    }
  });

  document.addEventListener('click', (event) => {
    if (actionMenu && !actionMenu.contains(event.target)) {
      closeActionMenu();
    }
  });
  window.addEventListener('scroll', closeActionMenu, true);
  window.addEventListener('resize', closeActionMenu);

  dom.modalClose.addEventListener('click', closeModal);
  dom.modal.addEventListener('click', (event) => {
    if (event.target === dom.modal) {
      closeModal();
    }
  });
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      closeActionMenu();
      if (!dom.modal.classList.contains('hidden')) {
        closeModal();
      }
    }
  });
}

attachEventListeners();
loadTable();
