/**
 * Indexing Duplicates — list over the indexing_duplicates table, with Snapshot and
 * Reverse Duplicate in each row's action menu.
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
          ${row.source === 'manual' ? `<span style="margin-left:4px;display:inline-block;padding:1px 6px;border-radius:9999px;background:#ffe4e6;color:#9f1239;font-size:10px;font-weight:700;" title="Captured by hand: never indexed, nothing to reverse">Manual</span>` : ''}
        </td>
        <td class="px-4 py-3 whitespace-nowrap">
          <span class="font-semibold text-slate-800">${escapeHtml(row.file_number)}</span>
          ${row.restored_at ? `<span style="margin-left:6px;display:inline-block;padding:1px 6px;border-radius:9999px;background:#dcfce7;color:#166534;font-size:10px;font-weight:700;"
                title="Reversed by ${escapeHtml(row.restored_by || 'Unknown')} on ${escapeHtml(row.restored_at)}">Reversed</span>` : ''}
        </td>
        <td class="px-4 py-3 whitespace-nowrap font-mono text-xs text-slate-700">${dash(row.temp_file_no)}</td>
        <td class="px-4 py-3 text-slate-700 max-w-[18rem] truncate" title="${escapeHtml(row.file_title || '')}">${dash(row.file_title)}</td>
        <td class="${cell}">${dash(row.land_use_type)}</td>
        <td class="px-4 py-3 text-slate-600 max-w-[16rem] truncate" title="${escapeHtml(location)}">${dash(location)}</td>
        <td class="${cell} text-xs">${dash(row.indexed_by)}</td>
        <td class="${cell} text-xs">${dash(row.indexed_at)}</td>
        <td class="${cell} text-xs">${dash(row.moved_by)}</td>
        <td class="px-4 py-3 text-right">
          <button type="button" class="dup-actions-btn inline-flex items-center justify-center p-1.5 rounded-lg text-slate-500 hover:text-blue-600 hover:bg-slate-100 transition-all"
                  aria-label="Actions" aria-haspopup="menu" aria-expanded="false"
                  data-id="${escapeHtml(row.id)}" data-reversed="${row.restored_at ? '1' : '0'}" data-manual="${row.source === 'manual' ? '1' : '0'}">
            <i data-lucide="more-vertical" class="h-4 w-4"></i>
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

/*
 * Row action menu. One panel on <body>, positioned with `fixed`: the table scrolls inside
 * overflow-x-auto, which would clip a panel kept in the row. Redraws close it.
 */
const rowMenu = (() => {
  const panel = document.createElement('div');
  panel.setAttribute('role', 'menu');
  panel.style.cssText = 'display:none;position:fixed;z-index:60;width:13rem;padding:4px 0;background:#fff;border:1px solid #e2e8f0;border-radius:10px;box-shadow:0 10px 25px rgba(15,23,42,.15);';
  document.body.appendChild(panel);

  let openFor = null;

  const close = () => {
    if (openFor) {
      openFor.setAttribute('aria-expanded', 'false');
    }
    openFor = null;
    panel.style.display = 'none';
  };

  const item = (action, icon, label, colour) => `
    <button type="button" role="menuitem" data-action="${action}"
            class="flex items-center gap-2 w-full text-left px-3 py-2 text-sm hover:bg-slate-50 transition-colors" style="color:${colour};">
      <i data-lucide="${icon}" class="h-4 w-4"></i>
      ${label}
    </button>`;

  const open = (button) => {
    if (openFor === button) {
      close();
      return;
    }
    close();

    const id = button.getAttribute('data-id');
    const reversed = button.getAttribute('data-reversed') === '1';
    // A manual entry was never indexed: there is nothing to put back.
    const manual = button.getAttribute('data-manual') === '1';
    panel.dataset.id = id;
    panel.innerHTML = item('snapshot', 'file-search', 'Snapshot', '#334155')
      + item('tempfile', 'file-clock', 'Check Temporary File', '#1d4ed8')
      + (config.canReverse && !reversed && !manual ? item('reverse', 'rotate-ccw', 'Reverse Duplicate', '#be123c') : '');
    refreshIcons();

    panel.style.display = 'block';
    openFor = button;
    button.setAttribute('aria-expanded', 'true');
    place();
  };

  // A fixed panel does not follow its button, so it is moved while the page or the table
  // scrolls under it, and closed once the button has gone out of view.
  const place = () => {
    if (!openFor) {
      return;
    }
    const anchor = openFor.getBoundingClientRect();
    if (anchor.bottom < 0 || anchor.top > window.innerHeight) {
      close();
      return;
    }

    const { width, height } = panel.getBoundingClientRect();
    const margin = 8;
    let top = anchor.bottom + 4;
    if (top + height > window.innerHeight - margin) {
      top = Math.max(margin, anchor.top - height - 4);
    }
    panel.style.left = `${Math.max(margin, Math.min(anchor.right - width, window.innerWidth - width - margin))}px`;
    panel.style.top = `${top}px`;
  };

  panel.addEventListener('click', (event) => {
    const button = event.target.closest('[data-action]');
    if (!button) {
      return;
    }
    const id = panel.dataset.id;
    close();
    if (button.dataset.action === 'snapshot') {
      openDetails(id);
    } else if (button.dataset.action === 'tempfile') {
      checkTempFile(id);
    } else {
      reverseDuplicate(id);
    }
  });

  // composedPath, not target.closest(): opening the menu redraws the Lucide icons, which
  // swaps out the very icon that was clicked, so the target is no longer inside its button.
  document.addEventListener('click', (event) => {
    const onMenu = event.composedPath().some((el) => el === panel || (el.classList && el.classList.contains('dup-actions-btn')));
    if (!onMenu) {
      close();
    }
  });
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      close();
    }
  });
  window.addEventListener('resize', close);
  window.addEventListener('scroll', place, true);

  return { open, close };
})();

async function readJson(response) {
  try {
    return await response.json();
  } catch (error) {
    return {};
  }
}

function showReverseRefusal(payload) {
  if (!payload.blocked) {
    window.Swal.fire({ icon: 'error', title: 'Cannot reverse', text: payload.message || 'The reversal could not be done.' });
    return;
  }

  const holders = (payload.held_by || []).map((h) => `
      <li><strong>${escapeHtml(h.file_number || '(no file number)')}</strong> — ${escapeHtml(h.file_title || 'no title')} (Indexing ID ${escapeHtml(h.id)})</li>`).join('');

  window.Swal.fire({
    icon: 'warning',
    title: 'Number is in use',
    width: 560,
    html: `<div class="text-left text-sm text-slate-600">
        <p>${escapeHtml(payload.file_number)} was indexed again after the move, so reversing it now would create a real duplicate.</p>
        <ul class="list-disc pl-5 my-3">${holders}</ul>
        <p>If this archived record is the correct one, move the live record above to Indexing Duplicates first, then reverse this one.</p>
      </div>`,
  });
}

async function reverseDuplicate(id) {
  if (!window.Swal) {
    return;
  }

  const headers = { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' };
  let preview;
  try {
    const response = await fetch(config.reversePreviewUrlTemplate.replace('__ID__', encodeURIComponent(id)), { headers });
    preview = await readJson(response);
    if (!response.ok || !preview.success) {
      showReverseRefusal(preview);
      return;
    }
  } catch (error) {
    window.Swal.fire({ icon: 'error', title: 'Cannot reverse', text: error.message });
    return;
  }

  const groups = (preview.summary || []).map((g) => `
      <li>${escapeHtml(g.group)}: <strong>${Number(g.total).toLocaleString()}</strong></li>`).join('');
  const relink = Object.values(preview.relink || {}).reduce((sum, n) => sum + Number(n || 0), 0);

  const { value: reason } = await window.Swal.fire({
    icon: 'warning',
    title: 'Reverse this duplicate?',
    width: 560,
    html: `<div class="text-left text-sm text-slate-600">
        <p><strong>${escapeHtml(preview.file_number)}</strong>${preview.file_title ? ` (${escapeHtml(preview.file_title)})` : ''} goes back to the live tables with its original IDs.</p>
        <ul class="list-disc pl-5 my-3">${groups}</ul>
        ${relink ? `<p class="mb-3">${relink} unlinked blind scan / SPAS record(s) are linked back, unless they have been linked elsewhere since.</p>` : ''}
        <label class="block font-semibold text-slate-700 mb-1" for="dup-reverse-reason">Reason <span style="color:#dc2626;">*</span></label>
        <textarea id="dup-reverse-reason" class="swal2-textarea" style="margin:0;width:100%;" rows="3" maxlength="500" placeholder="Why is this duplicate being reversed?"></textarea>
      </div>`,
    showCancelButton: true,
    confirmButtonText: 'Reverse Duplicate',
    confirmButtonColor: '#e11d48',
    focusCancel: true,
    allowOutsideClick: false,
    preConfirm: () => {
      const value = document.getElementById('dup-reverse-reason').value.trim();
      if (value.length < 5) {
        window.Swal.showValidationMessage('Enter a reason (at least 5 characters).');
        return false;
      }
      return value;
    },
  });

  if (!reason) {
    return;
  }

  window.Swal.fire({ title: 'Reversing…', allowOutsideClick: false, didOpen: () => window.Swal.showLoading() });

  try {
    const response = await fetch(config.reverseUrlTemplate.replace('__ID__', encodeURIComponent(id)), {
      method: 'POST',
      headers: { ...headers, 'Content-Type': 'application/json', 'X-CSRF-TOKEN': config.csrfToken },
      body: JSON.stringify({ reason }),
    });
    const result = await readJson(response);
    if (!response.ok || !result.success) {
      showReverseRefusal(result);
      return;
    }

    const skipped = Object.entries(result.skipped || {});
    await window.Swal.fire({
      icon: 'success',
      title: 'Reversed',
      html: `<p class="text-sm text-slate-600">${escapeHtml(result.message)}</p>
        ${skipped.length ? `<p class="text-xs text-slate-500 mt-2">Left out because the row already exists or its link has gone: ${escapeHtml(skipped.map(([t, n]) => `${t} (${n})`).join(', '))}</p>` : ''}`,
    });
    loadTable();
    loadStats();
  } catch (error) {
    window.Swal.fire({ icon: 'error', title: 'Reversal failed', text: error.message });
  }
}

async function loadTable() {
  rowMenu.close();

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
          <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Temp File No (file &amp; transactions)</p>
          <p class="text-sm text-slate-800 mt-0.5 font-mono font-semibold">${dash(r.temp_file_no)}</p>
        </div>
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

    const reversedNote = r.restored_at
      ? `<div style="background:#dcfce7;border:1px solid #86efac;border-radius:12px;padding:12px;">
          <p class="text-xs" style="color:#166534;">
            <strong>Reversed.</strong> ${escapeHtml(r.restored_by || 'Unknown')} put this file back in the live tables on
            ${escapeHtml(r.restored_at)}. This snapshot is what the file held when it was moved.
          </p>
        </div>`
      : '';

    const mlsNote = r.mls_file_no_retained
      ? `<div class="rounded-xl border border-amber-200 bg-amber-50 p-3">
          <p class="text-xs text-amber-800">
            <strong>Commissioning record kept.</strong> A row for this file still exists in
            <code>mls_file_no</code>. That table is outside the cascade, so the file remains commissioned.
          </p>
        </div>`
      : '';

    // What the move archived and removed, grouped (indexing, registry, property &
    // title, file tracking, parties, linked rows).
    const groups = Array.isArray(payload.summary) ? payload.summary : [];
    const removedTotal = groups.reduce((sum, g) => sum + Number(g.total || 0), 0);
    // Inline styles: the layout's Tailwind v2.2.19 CDN has no rose palette or
    // arbitrary sizes, so class-only styling would render this as plain text.
    const summaryCard = groups.length
      ? `<div style="background:#fff1f2;border:1px solid #fda4af;border-radius:12px;padding:14px;">
          <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;">
            <span style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#be123c;">Archived &amp; Removed</span>
            <span style="font-size:12px;font-weight:600;color:#be123c;">${removedTotal.toLocaleString()} row(s) in ${groups.length} group(s)</span>
          </div>
          <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:8px;margin-top:10px;">
            ${groups.map((g) => `
              <div style="background:#fff;border:1px solid #fecdd3;border-radius:8px;padding:8px 10px;">
                <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#64748b;">${escapeHtml(g.group)}</div>
                <div style="font-size:22px;font-weight:700;color:#be123c;line-height:1.2;">${Number(g.total).toLocaleString()}</div>
                <div style="font-size:11px;color:#64748b;line-height:1.35;">${(g.tables || []).map((t) => `${escapeHtml(t.label)} (${Number(t.count).toLocaleString()})`).join('<br>')}</div>
              </div>`).join('')}
          </div>
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

    dom.modalBody.innerHTML = reversedNote + summaryCard + meta + mlsNote + retainedNote + childNote + tables;
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
    const button = event.target.closest('.dup-actions-btn');
    if (button) {
      rowMenu.open(button);
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

/*
 * Check Temporary File (CR 11112026-2): where the duplicate's "(T)" file is now and
 * what it is, so an officer can answer the owner when they call about it.
 */
async function checkTempFile(id) {
  if (!window.Swal) {
    return;
  }
  let payload = {};
  try {
    const response = await fetch(config.tempFileUrlTemplate.replace('__ID__', encodeURIComponent(id)), {
      headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
    });
    payload = await readJson(response);
    if (!response.ok || !payload.success) {
      throw new Error(payload.message || 'Could not look up the temporary file.');
    }
  } catch (error) {
    window.Swal.fire({ icon: 'error', title: 'Temporary File', text: error.message });
    return;
  }

  const files = payload.files || [];
  const body = files.length === 0
    ? `<p>No temporary file is recorded for <strong>${escapeHtml(payload.file_number)}</strong>.</p>
       <p class="text-xs text-slate-500 mt-2">Looked for: ${escapeHtml((payload.looked_for || []).join(', '))}</p>`
    : files.map((f) => `
        <div class="border border-slate-200 rounded-xl p-3 mb-2">
          <p class="font-semibold text-slate-800">${escapeHtml(f.file_number)} &middot; ${escapeHtml(f.file_title || 'no title')}</p>
          <p class="text-sm mt-1"><strong>Status:</strong> ${escapeHtml(f.status || '—')}${f.current_location ? ` &middot; <strong>At:</strong> ${escapeHtml(f.current_location)}` : ''}</p>
          <p class="text-xs text-slate-500 mt-1">${f.indexed ? 'Indexed' : 'Not indexed'}${f.problem_files ? ` &middot; Problem Files: ${escapeHtml(f.problem_files)}` : ''}${f.registry ? ` &middot; ${escapeHtml(f.registry)}` : ''}${f.rack_shelf ? ` &middot; Rack/Shelf ${escapeHtml(f.rack_shelf)}` : ''}</p>
        </div>`).join('');

  window.Swal.fire({
    title: 'Temporary File',
    width: 600,
    html: `<div class="text-left text-sm text-slate-600">${body}</div>`,
  });
}

/* Manual Entry (Super Admin): a duplicate file that was never indexed. */
function attachManualEntry() {
  const modal = document.getElementById('dup-manual-modal');
  const openBtn = document.getElementById('dup-manual-open');
  const form = document.getElementById('dup-manual-form');
  if (!modal || !openBtn || !form) {
    return;
  }
  const fileNo = document.getElementById('dup-manual-fileno');
  const dupOf = document.getElementById('dup-manual-dupof');
  const error = document.getElementById('dup-manual-error');
  const save = document.getElementById('dup-manual-save');

  const show = () => { modal.classList.remove('hidden'); modal.classList.add('flex'); refreshIcons(); };
  const hide = () => { modal.classList.add('hidden'); modal.classList.remove('flex'); };
  const fail = (message) => { error.textContent = message; error.classList.toggle('hidden', !message); };

  /*
   | Land use comes from the file number, in full (RES-2023-5062 -> Residential), the
   | same reading as FileNumberLandUse::codeFor; the server sets it again on save.
   | A number with no land-use code (a KANGIS number) leaves the box open to type.
   */
  const landUse = document.getElementById('dup-manual-landuse');
  const landUseLabels = config.landUseLabels || {};
  const landUseCode = (number) => {
    const first = String(number || '').split(',')[0].trim().toUpperCase();
    if (first === '' || first.startsWith('DPX-') || /^(MLKNGP|KNGP|MLKN|KNML)\s*\d+$/.test(first)) return '';
    const parts = first.split('-').filter(Boolean);
    if (!parts.length) return '';
    if (['CON', 'ST', 'SLTR', 'KN'].includes(parts[0]) && parts[1] && !/^\d+$/.test(parts[1])) return parts[1];
    return /^\d+$/.test(parts[0]) ? '' : parts[0];
  };
  const setLandUse = (number) => {
    const full = landUseLabels[landUseCode(number)] || '';
    landUse.value = full;
    landUse.readOnly = full !== '';
    landUse.classList.toggle('bg-slate-50', full !== '');
    landUse.placeholder = full !== '' || !number ? 'Select a file number' : 'No land use in this file number: type it';
  };

  // Physical Registry: the File Indexing list, with one "Other" that opens a required box.
  const registry = document.getElementById('dup-manual-registry');
  const registryOther = document.getElementById('dup-manual-registry-other');
  const syncRegistry = () => {
    const isOther = registry.value === '__other';
    registryOther.classList.toggle('hidden', !isOther);
    registryOther.disabled = !isOther;
    if (!isOther) registryOther.value = '';
    else registryOther.focus();
  };
  registry.addEventListener('change', syncRegistry);

  // Picked from the global selector, never typed -- and nothing is backfilled: the
  // indexed record behind the number is the OTHER file.
  const pick = () => {
    if (typeof window.GlobalFileNoModal === 'undefined') {
      fail('The file number selector did not load. Refresh the page and try again.');
      return;
    }
    window.GlobalFileNoModal.open({
      initialValue: fileNo.value || '',
      callback: (fileData) => {
        const picked = fileData && (fileData.fileNumber || fileData.fileno || fileData.mlsFNo
          || fileData.kangisFileNo || fileData.NewKANGISFileno
          || (fileData.record && (fileData.record.file_number || fileData.record.mlsFNo || fileData.record.kangisFileNo)));
        if (picked) {
          fileNo.value = picked;
          dupOf.value = picked;
          setLandUse(picked);
          fail('');
        }
      },
    });
  };

  /*
   | District and LGA come from the lookup lists, each with one "Other" that opens a
   | required specify box. Location is built from them -- "District, LGA, Kano" --
   | and never carries the plot number.
   */
  const places = [
    { select: document.getElementById('dup-manual-district'), other: document.getElementById('dup-manual-district-other'), label: 'district' },
    { select: document.getElementById('dup-manual-lga'), other: document.getElementById('dup-manual-lga-other'), label: 'LGA' },
  ];
  const locationInput = document.getElementById('dup-manual-location');
  const placeValue = (p) => (p.select.value === '__other' ? p.other.value.replace(/\s+/g, ' ').trim() : p.select.value);
  const composeLocation = () => {
    const parts = places.map(placeValue).filter(Boolean);
    locationInput.value = parts.length ? [...parts, 'Kano'].join(', ') : '';
  };
  const syncOther = (p) => {
    const isOther = p.select.value === '__other';
    p.other.classList.toggle('hidden', !isOther);
    p.other.disabled = !isOther;
    if (!isOther) p.other.value = '';
    composeLocation();
  };
  const $ = window.jQuery;
  places.forEach((p) => {
    if ($ && $.fn && $.fn.select2) {
      $(p.select).select2({ dropdownParent: $(modal), width: '100%', placeholder: `Select ${p.label}` })
        .on('change', () => syncOther(p));
    } else {
      p.select.addEventListener('change', () => syncOther(p));
    }
    p.other.addEventListener('input', composeLocation);
  });
  const resetPlaces = () => {
    places.forEach((p) => {
      p.select.value = '';
      if ($ && $.fn && $.fn.select2) $(p.select).trigger('change.select2');
      syncOther(p);
    });
  };

  openBtn.addEventListener('click', () => { form.reset(); resetPlaces(); setLandUse(''); syncRegistry(); fail(''); show(); });
  document.getElementById('dup-manual-close').addEventListener('click', hide);
  document.getElementById('dup-manual-cancel').addEventListener('click', hide);
  fileNo.addEventListener('click', pick);
  document.getElementById('dup-manual-fileno-btn').addEventListener('click', pick);

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    const data = Object.fromEntries(new FormData(form).entries());
    for (const p of places) {
      if (p.select.value === '__other' && !placeValue(p)) {
        fail(`Specify the ${p.label}, or pick one from the list.`);
        p.other.focus();
        return;
      }
      data[p.select.name] = placeValue(p);
    }
    data.location = locationInput.value;
    if (registry.value === '__other') {
      data.registry = registryOther.value.replace(/\s+/g, ' ').trim();
      if (!data.registry) { fail('Specify the physical registry, or pick one from the list.'); registryOther.focus(); return; }
    }
    if (!data.file_number) { fail('Select the file number.'); return; }
    if (!String(data.file_title || '').trim()) { fail('Enter the title on the duplicate file.'); return; }
    if (!String(data.reason || '').trim()) { fail('Say why this file is a duplicate.'); return; }

    save.disabled = true;
    try {
      const response = await fetch(config.manualUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': config.csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify(data),
      });
      const payload = await readJson(response);
      if (!response.ok || !payload.success) {
        const first = payload.errors ? Object.values(payload.errors)[0] : null;
        throw new Error((Array.isArray(first) ? first[0] : first) || payload.message || 'Could not save the entry.');
      }
      hide();
      state.page = 1;
      loadTable();
      loadStats();
      if (window.Swal) {
        window.Swal.fire({ icon: 'success', title: 'Captured', text: payload.message, timer: 2500, showConfirmButton: false });
      }
    } catch (err) {
      fail(err.message);
    } finally {
      save.disabled = false;
    }
  });
}

attachEventListeners();
attachManualEntry();
loadTable();
loadStats();
