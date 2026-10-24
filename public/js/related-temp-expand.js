/**
 * Expandable rows for files that have Related File Number(s) or a Temporary File
 * (Change Request 22102026, items 4-6). Used by the Indexed Files, MLPP FileNo
 * Generator, Related Files and Re-grant tables.
 *
 * A table marks each row with data-rt-fileno="<file number>" and calls
 * RelatedTempExpand.scan(tbody) after every render / redraw. Rows whose file has
 * either kind get a chevron; clicking it opens a line per file under the row.
 * A Temporary File is listed on its own, never as a Related File — the server
 * (RelatedTempFileLookup) already keeps the two apart.
 *
 * A table that already holds a row's lists can add them with prime(); they are
 * merged with the server's.
 */
(function () {
  'use strict';

  if (window.RelatedTempExpand) return;

  const ENDPOINT = '/related-file-number/expand';
  const cache = new Map(); // normalised file no -> server entry | null
  const primed = new Map(); // normalised file no -> lists the table supplied

  const norm = (v) => String(v == null ? '' : v).trim().toUpperCase();
  const esc = (v) => String(v == null ? '' : v)
    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;').replace(/'/g, '&#39;');

  function csrf() {
    const m = document.querySelector('meta[name="csrf-token"]');
    return m ? m.getAttribute('content') : '';
  }

  async function load(fileNos) {
    const missing = [...new Set(fileNos.map(norm))].filter((k) => k && !cache.has(k));
    for (let i = 0; i < missing.length; i += 200) {
      const chunk = missing.slice(i, i + 200);
      try {
        const res = await fetch(window.location.origin + ENDPOINT, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrf(),
          },
          body: JSON.stringify({ files: chunk }),
        });
        const json = await res.json();
        const data = (json && json.data) || {};
        const byNorm = {};
        Object.keys(data).forEach((k) => { byNorm[norm(k)] = data[k]; });
        chunk.forEach((k) => cache.set(k, byNorm[k] || null));
      } catch (e) {
        // Leave uncached so a later scan can retry; rows simply get no chevron.
        console.warn('RelatedTempExpand: lookup failed', e);
      }
    }
  }

  const looksTemp = (v) => /^TEMP/i.test(String(v).trim()) || /\(T\)\s*$/i.test(String(v).trim());

  // Everything known for the row's file: the server lookup merged with what the
  // table supplied itself (prime). Nothing is left out because the row already
  // prints it — the expansion is the complete list. Only the file itself is dropped,
  // and a temp-looking number is always a Temporary File, never a Related one.
  function entryFor(tr) {
    const key = norm(tr.dataset.rtFileno);
    const server = cache.get(key) || null;
    const own = primed.get(key) || null;
    if (!server && !own) return null;
    const seen = new Set([key]);
    const related = [];
    const temporary = [];
    const take = (list, forceTemp) => (list || []).forEach((f) => {
      const k = norm(f && f.file_number);
      if (!k || seen.has(k)) return;
      seen.add(k);
      (forceTemp || looksTemp(f.file_number) ? temporary : related).push(f);
    });
    take(own && own.temporary, true);
    take(server && server.temporary, true);
    take(own && own.related, false);
    take(server && server.related, false);
    return related.length || temporary.length ? { related, temporary } : null;
  }

  function chevronHtml(entry) {
    const parts = [];
    if (entry.temporary.length) parts.push(entry.temporary.length + ' temporary');
    if (entry.related.length) parts.push(entry.related.length + ' related');
    const label = 'Show ' + parts.join(' and ') + ' file' + (entry.temporary.length + entry.related.length > 1 ? 's' : '');
    return '<button type="button" class="rt-toggle inline-flex items-center justify-center h-5 w-5 mr-1 rounded border border-slate-300 bg-white text-slate-600 hover:bg-indigo-50 hover:text-indigo-700 hover:border-indigo-300 align-middle"'
      + ' aria-expanded="false" title="' + esc(label) + '" aria-label="' + esc(label) + '">'
      + '<svg class="rt-chev h-3 w-3 transition-transform duration-150" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd"/></svg>'
      + '</button>';
  }

  const details = new Map(); // normalised file no -> details | null

  async function loadDetails(numbers) {
    const missing = [...new Set(numbers.map(norm))].filter((k) => k && !details.has(k));
    if (!missing.length) return;
    try {
      const res = await fetch(window.location.origin + ENDPOINT, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': csrf(),
        },
        body: JSON.stringify({ details: missing }),
      });
      const json = await res.json();
      const data = (json && json.data) || {};
      const byNorm = {};
      Object.keys(data).forEach((k) => { byNorm[norm(k)] = data[k]; });
      missing.forEach((k) => details.set(k, byNorm[k] || null));
    } catch (e) {
      console.warn('RelatedTempExpand: details failed', e);
    }
  }

  const dash = (v) => {
    const t = String(v == null ? '' : v).trim();
    return t && t !== '-' ? esc(t) : '<span class="text-gray-300">-</span>';
  };

  // Same columns as the Related Files card on the Indexed Files table:
  // # | File Number | File Title | Location | Plot/TP/LPKN | Actions.
  function rowHtml(kind, f, idx) {
    const d = details.get(norm(f.file_number)) || {};
    const isTemp = kind === 'temporary';
    const badge = isTemp
      ? '<span class="inline-flex px-1.5 py-0.5 rounded text-[9px] font-bold uppercase bg-amber-50 text-amber-700 border border-amber-200">Temporary</span>'
      : '<span class="inline-flex px-1.5 py-0.5 rounded text-[9px] font-bold uppercase bg-blue-50 text-blue-700 border border-blue-200">Related</span>';
    return '<tr class="hover:bg-gray-50">'
      + '<td class="px-4 py-2.5 text-sm text-gray-600 font-medium">' + (idx + 1) + '</td>'
      + '<td class="px-4 py-2.5 text-sm whitespace-nowrap"><div class="flex items-center gap-2">' + badge
      + '<span class="font-bold ' + (isTemp ? 'text-amber-800' : 'text-blue-700') + '">' + esc(f.file_number) + '</span></div></td>'
      + '<td class="px-4 py-2.5 text-sm text-gray-700 font-semibold">' + dash(d.file_title || f.file_title) + '</td>'
      + '<td class="px-4 py-2.5 text-sm text-gray-600">' + dash(d.location) + '</td>'
      + '<td class="px-4 py-2.5 text-xs text-gray-500"><div class="flex flex-col gap-0.5">'
      + '<span class="font-medium">Plot: ' + dash(d.plot_number) + '</span>'
      + '<span>TP: ' + dash(d.tp_no) + ' | LPKN: ' + dash(d.lpkn_no) + '</span></div></td>'
      + '<td class="px-4 py-2.5 text-center">'
      + '<button type="button" class="rt-menu-btn inline-flex items-center justify-center h-7 w-7 rounded-md text-gray-500 hover:bg-gray-100 hover:text-gray-800" aria-label="Actions"'
      + ' data-file-number="' + esc(f.file_number) + '" data-indexing-id="' + esc(d.indexing_id || '') + '">'
      + '<i data-lucide="more-vertical" class="h-4 w-4"></i></button></td>'
      + '</tr>';
  }

  function tableHtml(entry) {
    const all = entry.temporary.map((f) => ['temporary', f]).concat(entry.related.map((f) => ['related', f]));
    return '<div class="rounded-lg border border-gray-200 bg-white overflow-hidden">'
      + '<table class="min-w-full divide-y divide-gray-100">'
      + '<thead class="bg-gray-50"><tr class="text-left text-[11px] font-bold uppercase tracking-wider text-gray-500">'
      + '<th class="px-4 py-2.5">#</th><th class="px-4 py-2.5">File Number</th><th class="px-4 py-2.5">File Title</th>'
      + '<th class="px-4 py-2.5">Location</th><th class="px-4 py-2.5">Plot/TP/LPKN</th><th class="px-4 py-2.5 text-center">Actions</th>'
      + '</tr></thead><tbody class="divide-y divide-gray-100 bg-white">'
      + all.map(([kind, f], i) => rowHtml(kind, f, i)).join('')
      + '</tbody></table></div>';
  }

  function colspanOf(tr) {
    let n = 0;
    Array.from(tr.cells).forEach((c) => { n += c.colSpan || 1; });
    return Math.max(1, n);
  }

  function icons() {
    if (window.lucide && typeof window.lucide.createIcons === 'function') window.lucide.createIcons();
  }

  async function open(tr, btn) {
    const entry = entryFor(tr);
    if (!entry) return;
    const child = document.createElement('tr');
    child.className = 'rt-child bg-slate-50/70';
    const td = document.createElement('td');
    td.colSpan = colspanOf(tr);
    td.className = 'px-4 py-3 pl-10 border-l-4 border-indigo-200';
    td.innerHTML = '<div class="text-sm text-gray-400 py-2">Loading…</div>';
    child.appendChild(td);
    tr.after(child);
    tr._rtChild = child;
    btn.setAttribute('aria-expanded', 'true');
    const chev = btn.querySelector('.rt-chev');
    if (chev) chev.style.transform = 'rotate(90deg)';

    await loadDetails(entry.temporary.concat(entry.related).map((f) => f.file_number));
    if (tr._rtChild !== child) return; // closed meanwhile
    td.innerHTML = tableHtml(entry);
    fitToView(tr, td);
    icons();
  }

  // A wide table scrolls sideways; keep the expanded block inside the visible part
  // of it (sticky to the left edge, as wide as the scroll box) instead of stretched
  // across every column.
  function scroller(el) {
    for (let n = el.parentElement; n && n !== document.body; n = n.parentElement) {
      const ox = getComputedStyle(n).overflowX;
      if ((ox === 'auto' || ox === 'scroll') && n.scrollWidth > n.clientWidth + 1) return n;
    }
    return null;
  }
  function fitToView(tr, td) {
    const box = td.firstElementChild;
    const sc = scroller(tr);
    if (!box || !sc) return;
    box.style.position = 'sticky';
    box.style.left = '0';
    box.style.width = Math.max(320, sc.clientWidth - 64) + 'px';
    box.style.overflowX = 'auto';
  }

  function close(tr, btn) {
    closeMenu();
    if (tr._rtChild) tr._rtChild.remove();
    tr._rtChild = null;
    btn.setAttribute('aria-expanded', 'false');
    const chev = btn.querySelector('.rt-chev');
    if (chev) chev.style.transform = '';
  }

  // Row action menu: fixed to the page so the table's overflow never clips it.
  let menuEl = null;
  function closeMenu() {
    if (menuEl) { menuEl.remove(); menuEl = null; }
  }
  function openMenu(btn) {
    closeMenu();
    const id = btn.dataset.indexingId;
    const fileNo = btn.dataset.fileNumber || '';
    const item = 'flex items-center gap-2.5 w-full text-left px-4 py-2.5 text-sm';
    menuEl = document.createElement('div');
    menuEl.className = 'fixed z-[200] w-56 rounded-lg border border-gray-200 bg-white shadow-xl py-1';
    menuEl.style.zIndex = '200';
    menuEl.innerHTML = (id
      ? '<a href="' + window.location.origin + '/fileindex/' + encodeURIComponent(id) + '" target="_blank" rel="noopener" class="' + item + ' text-gray-700 hover:bg-blue-50"><i data-lucide="eye" class="h-4 w-4 text-blue-600"></i><span>View Indexed Record</span></a>'
      : '<span class="' + item + ' text-gray-400 cursor-not-allowed" title="This file has not been indexed"><i data-lucide="eye-off" class="h-4 w-4"></i><span>Not Indexed Yet</span></span>')
      + '<button type="button" class="rt-copy ' + item + ' text-gray-700 hover:bg-gray-50"><i data-lucide="copy" class="h-4 w-4 text-gray-500"></i><span>Copy File Number</span></button>';
    menuEl._for = btn;
    menuEl._openedAt = Date.now();
    document.body.appendChild(menuEl);
    const r = btn.getBoundingClientRect();
    const w = menuEl.offsetWidth;
    const h = menuEl.offsetHeight;
    menuEl.style.left = Math.max(8, Math.min(r.right - w, window.innerWidth - w - 8)) + 'px';
    menuEl.style.top = (r.bottom + h + 8 > window.innerHeight ? Math.max(8, r.top - h - 4) : r.bottom + 4) + 'px';
    menuEl.querySelector('.rt-copy').addEventListener('click', () => {
      try { navigator.clipboard.writeText(fileNo); } catch (e) { /* clipboard blocked */ }
      closeMenu();
    });
    icons();
  }

  document.addEventListener('click', (e) => {
    const menuBtn = e.target.closest && e.target.closest('.rt-menu-btn');
    if (menuBtn) {
      e.preventDefault();
      e.stopPropagation();
      if (menuEl && menuEl._for === menuBtn) closeMenu(); else openMenu(menuBtn);
      return;
    }
    if (menuEl && !menuEl.contains(e.target)) closeMenu();

    const btn = e.target.closest && e.target.closest('.rt-toggle');
    if (!btn) return;
    e.preventDefault();
    e.stopPropagation();
    const tr = btn.closest('tr');
    if (!tr) return;
    if (tr._rtChild && tr._rtChild.isConnected) close(tr, btn); else open(tr, btn);
  }, true);
  // Close on a real scroll, not the one that comes with the opening click.
  window.addEventListener('scroll', (e) => {
    if (menuEl && !menuEl.contains(e.target) && Date.now() - (menuEl._openedAt || 0) > 300) closeMenu();
  }, true);
  window.addEventListener('resize', closeMenu);
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeMenu(); });

  /**
   * Add chevrons to the rows under `root` that have related / temporary files.
   * The chevron goes at the start of the row (first cell); opts.cell(tr) can pick another.
   */
  async function scan(root, opts) {
    const o = opts || {};
    if (!root) return;
    const rows = Array.from(root.querySelectorAll('tr[data-rt-fileno]'))
      .filter((tr) => norm(tr.dataset.rtFileno) !== '' && norm(tr.dataset.rtFileno) !== '-');
    if (!rows.length) return;
    await load(rows.map((tr) => tr.dataset.rtFileno));
    rows.forEach((tr) => {
      if (!tr.isConnected || tr.querySelector('.rt-toggle')) return;
      const entry = entryFor(tr);
      if (!entry) return;
      const cell = (typeof o.cell === 'function' ? o.cell(tr) : null) || tr.cells[0];
      if (!cell) return;
      cell.insertAdjacentHTML('afterbegin', chevronHtml(entry));
      cell.style.whiteSpace = 'nowrap'; // chevron and the cell's own content on one line
    });
  }

  /**
   * Supply a file's lists from data the table already has, so no lookup is made for it.
   * Temp-looking numbers given as related are moved to temporary, as on the server.
   */
  function prime(fileNo, related, temporary) {
    primed.set(norm(fileNo), { related: related || [], temporary: temporary || [] });
  }

  window.RelatedTempExpand = { scan, prime, clearCache: () => cache.clear() };
})();
