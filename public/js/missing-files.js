/**
 * Missing Files
 * Capture a file (via the global file-number modal), record its shelf/rack &
 * archive/registry location, persist it, and render the missing-files table.
 */

(function () {
    'use strict';

    const cfg = window.MissingFilesConfig || {};
    const routes = cfg.routes || {};
    const CSRF = cfg.csrf || (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

    const state = {
        page: (cfg.initialPagination && cfg.initialPagination.current_page) || 1,
        search: '',
        duplicate: false,
        transit: false,
    };

    document.addEventListener('DOMContentLoaded', function () {
        initTabs();
        initFileNoModal();
        initFullLabelSync();
        bindActions();
        renderRows(Array.isArray(cfg.initial) ? cfg.initial : []);
        renderPagination(cfg.initialPagination || null);
        refreshIcons();
    });

    /* ---------------------------------------------------------------- Tabs */

    function initTabs() {
        const tabs = document.querySelectorAll('[data-mf-tab]');
        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                switchTab(tab.getAttribute('data-mf-tab'));
            });
        });
    }

    function switchTab(name) {
        document.querySelectorAll('[data-mf-tab]').forEach(function (tab) {
            const active = tab.getAttribute('data-mf-tab') === name;
            tab.classList.toggle('active', active);
            tab.classList.toggle('border-rose-500', active);
            tab.classList.toggle('text-rose-600', active);
            tab.classList.toggle('border-transparent', !active);
            tab.classList.toggle('text-gray-500', !active);
        });
        const capture = document.getElementById('mf-panel-capture');
        const list = document.getElementById('mf-panel-list');
        if (capture) capture.classList.toggle('hidden', name !== 'capture');
        if (list) list.classList.toggle('hidden', name !== 'list');
        refreshIcons();
    }

    /* ---------------------------------------------------------------- File selector */

    function initFileNoModal() {
        const btn = document.getElementById('mf-fileno-selector-btn');
        if (!btn) return;

        if (typeof GlobalFileNoModal === 'undefined') {
            console.error('GlobalFileNoModal is not loaded');
            return;
        }

        GlobalFileNoModal.init();

        btn.addEventListener('click', function () {
            GlobalFileNoModal.open({
                targetFields: ['mf-file-no'],
                callback: handleFileNumberSelection,
            });
        });
    }

    function handleFileNumberSelection(data) {
        if (!data || !data.fileNumber) return;

        setValue('mf-file-no', data.fileNumber);
        checkDuplicate(data.fileNumber);

        const record = data.record || {};
        const trackingId = record.tracking_id || record.trackingId || '';
        if (trackingId) {
            setValue('mf-tracking-id', trackingId);
        } else {
            fetchTrackingId(data.fileNumber);
        }
    }

    function fetchTrackingId(fileNumber) {
        if (!fileNumber) return;
        fetch('/api/file-indexings/lookup-by-number?file_number=' + encodeURIComponent(fileNumber))
            .then((r) => r.json())
            .then((res) => {
                if (res && res.success && res.data && res.data.tracking_id) {
                    setValue('mf-tracking-id', res.data.tracking_id);
                }
            })
            .catch((err) => console.warn('Tracking ID lookup failed', err));
    }

    /* ---------------------------------------------------------------- Duplicate check */

    function checkDuplicate(fileNumber) {
        clearDuplicateWarning();
        clearTransitNotice();
        if (!fileNumber || !routes.check) return;

        const url = new URL(routes.check, window.location.origin);
        url.searchParams.set('file_number', fileNumber);

        fetch(url.toString(), { headers: { 'Accept': 'application/json' } })
            .then(parseJson)
            .then(function (res) {
                // Ignore a stale response if the user has since picked another file.
                if (getValue('mf-file-no').trim() !== fileNumber) return;
                if (res && res.success && res.exists) {
                    showDuplicateWarning(res.message);
                }
                if (res && res.success && res.in_transit) {
                    showTransitNotice(res.transit_location, res.transit_tracking_id);
                }
                // Auto-detect the Archive / Registry from config/file_ranges.php
                // (resolved server-side from the file's prefix + year).
                if (res && res.success) {
                    applyDetectedRegistry(res.registry);
                }
            })
            .catch(function () { /* the unique constraint still guards the save */ });
    }

    function showTransitNotice(location, trackingId) {
        const box = document.getElementById('mf-transit-notice');
        const text = document.getElementById('mf-transit-notice-text');
        if (text) {
            let msg = 'The file is currently in-transit';
            if (location) msg += ' at ' + location;
            msg += '. It may not be truly missing.';
            text.textContent = msg;
        }
        if (box) {
            box.classList.remove('hidden');
            box.classList.add('flex');
        }
        // Block capturing a file that is simply logged out — it isn't missing.
        state.transit = true;
        setBusy(document.getElementById('mf-submit-btn'), true);
        refreshIcons();
    }

    function clearTransitNotice() {
        const box = document.getElementById('mf-transit-notice');
        if (box) {
            box.classList.add('hidden');
            box.classList.remove('flex');
        }
        state.transit = false;
        // Only re-enable the button if a duplicate warning isn't also holding it disabled.
        setBusy(document.getElementById('mf-submit-btn'), state.duplicate);
    }

    function showDuplicateWarning(message) {
        const err = document.getElementById('mf-file-no-error');
        const text = document.getElementById('mf-file-no-error-text');
        const input = document.getElementById('mf-file-no');

        if (text && message) text.textContent = message;
        if (err) {
            err.classList.remove('hidden');
            err.classList.add('flex');
        }
        if (input) {
            input.classList.remove('border-gray-300');
            input.classList.add('border-red-500', 'bg-red-50', 'text-red-700');
        }
        state.duplicate = true;
        setBusy(document.getElementById('mf-submit-btn'), true);
        refreshIcons();
    }

    function clearDuplicateWarning() {
        const err = document.getElementById('mf-file-no-error');
        const input = document.getElementById('mf-file-no');

        if (err) {
            err.classList.add('hidden');
            err.classList.remove('flex');
        }
        if (input) {
            input.classList.remove('border-red-500', 'bg-red-50', 'text-red-700');
            input.classList.add('border-gray-300');
        }
        state.duplicate = false;
        setBusy(document.getElementById('mf-submit-btn'), false);
    }

    /* ------------------------------------------------------------ Registry auto-detect */

    // Pre-select the Archive / Registry dropdown from the range resolved server-side
    // (config/file_ranges.php). The field stays editable so the user can override, but a
    // matched registry drives the default instead of the static "Registry 1".
    function applyDetectedRegistry(registry) {
        const sel = document.getElementById('mf-archive-registry');
        if (!sel) return;

        const value = (registry || '').trim();
        if (!value) return;

        // Only apply when the resolved registry is one of the dropdown's options.
        const match = Array.prototype.find.call(sel.options, function (opt) {
            return opt.value === value;
        });
        if (!match) return;

        sel.value = value;

        // Brief highlight so the user sees the field was auto-filled for them.
        sel.classList.add('ring-2', 'ring-rose-300');
        setTimeout(function () {
            sel.classList.remove('ring-2', 'ring-rose-300');
        }, 1200);
    }

    /* ---------------------------------------------------------------- Full label */

    function initFullLabelSync() {
        ['mf-rack-primary', 'mf-shelf-number'].forEach(function (id) {
            const el = document.getElementById(id);
            if (el) el.addEventListener('change', updateFullLabel);
        });
        updateFullLabel();
    }

    function updateFullLabel() {
        const rack = getValue('mf-rack-primary') || '';
        const shelf = getValue('mf-shelf-number') || '';
        setValue('mf-full-label', (rack + shelf).toUpperCase());
    }

    /* ---------------------------------------------------------------- Actions */

    function bindActions() {
        on('mf-submit-btn', 'click', submitMissingFile);
        on('mf-reset-btn', 'click', resetForm);
        on('mf-refresh-btn', 'click', function () { reloadTable(state.search, state.page); });
        on('mf-prev-btn', 'click', function () {
            if (state.page > 1) reloadTable(state.search, state.page - 1);
        });
        on('mf-next-btn', 'click', function () {
            reloadTable(state.search, state.page + 1);
        });

        const search = document.getElementById('mf-search');
        if (search) {
            search.addEventListener('input', debounce(function () {
                reloadTable(search.value.trim(), 1);
            }, 300));
        }

        // Delegated row actions.
        const body = document.getElementById('mf-table-body');
        if (body) {
            body.addEventListener('click', function (e) {
                const foundBtn = e.target.closest('[data-action="found"]');
                const delBtn = e.target.closest('[data-action="delete"]');
                if (foundBtn) markFound(foundBtn.getAttribute('data-id'));
                if (delBtn) deleteRecord(delBtn.getAttribute('data-id'));
            });
        }
    }

    function submitMissingFile() {
        const fileNumber = getValue('mf-file-no').trim();
        if (!fileNumber) {
            toast('Please select or enter a file number.', 'error');
            return;
        }
        if (state.duplicate) {
            toast('This file has already been recorded as missing.', 'error');
            return;
        }
        if (state.transit) {
            toast('This file is currently in-transit — it is logged out, not missing.', 'error');
            return;
        }

        const payload = {
            file_number: fileNumber,
            tracking_id: getValue('mf-tracking-id').trim(),
            archive_registry: getValue('mf-archive-registry'),
            rack_primary: getValue('mf-rack-primary'),
            rack_secondary: getValue('mf-rack-secondary'),
            shelf_number: getValue('mf-shelf-number'),
            full_label: getValue('mf-full-label'),
        };

        const btn = document.getElementById('mf-submit-btn');
        setBusy(btn, true);

        fetch(routes.store, {
            method: 'POST',
            headers: jsonHeaders(),
            body: JSON.stringify(payload),
        })
            .then(function (response) {
                return parseJson(response).then(function (res) {
                    return { status: response.status, body: res };
                });
            })
            .then(function (out) {
                const res = out.body;

                if (res && res.success) {
                    toast(res.message || 'File recorded as missing.', 'success');
                    resetForm();
                    reloadTable(state.search, 1);
                    switchTab('list');
                    return;
                }

                const duplicateMessage = res && (
                    (res.errors && res.errors.file_number && res.errors.file_number[0]) ||
                    (res.duplicate && res.message)
                );

                if (out.status === 422 && duplicateMessage) {
                    showDuplicateWarning(duplicateMessage);
                    toast(duplicateMessage, 'error');
                    return;
                }

                toast((res && res.message) || 'Failed to record the missing file.', 'error');
            })
            .catch(function () {
                toast('Something went wrong while saving.', 'error');
            })
            .finally(function () {
                setBusy(btn, state.duplicate || state.transit);
            });
    }

    function markFound(id) {
        if (!id) return;
        fetch(routes.found + '/' + id + '/found', {
            method: 'PATCH',
            headers: jsonHeaders(),
        })
            .then(parseJson)
            .then(function (res) {
                if (res && res.success) {
                    toast(res.message || 'File marked as found.', 'success');
                    // Found files auto-drop from the list: fade the row out, then refresh
                    // so pagination / counts settle (the server no longer returns Found rows).
                    fadeOutRow(id, function () { reloadTable(state.search, state.page); });
                } else {
                    toast((res && res.message) || 'Action failed.', 'error');
                }
            })
            .catch(function () { toast('Action failed.', 'error'); });
    }

    function deleteRecord(id) {
        if (!id) return;
        if (!window.confirm('Delete this missing-file record?')) return;
        fetch(routes.destroy + '/' + id, {
            method: 'DELETE',
            headers: jsonHeaders(),
        })
            .then(parseJson)
            .then(function (res) {
                if (res && res.success) {
                    toast(res.message || 'Record deleted.', 'success');
                    reloadTable(state.search, state.page);
                } else {
                    toast((res && res.message) || 'Delete failed.', 'error');
                }
            })
            .catch(function () { toast('Delete failed.', 'error'); });
    }

    function reloadTable(search, page) {
        state.search = search || '';
        state.page = page || 1;

        const url = new URL(routes.data, window.location.origin);
        if (state.search) url.searchParams.set('search', state.search);
        url.searchParams.set('page', state.page);

        fetch(url.toString(), { headers: { 'Accept': 'application/json' } })
            .then(parseJson)
            .then(function (res) {
                if (res && res.success) {
                    renderRows(res.data || []);
                    renderPagination(res.pagination || null);
                }
            })
            .catch(function () { /* keep existing table */ });
    }

    /* ---------------------------------------------------------------- Rendering */

    function renderRows(rows) {
        const body = document.getElementById('mf-table-body');
        if (!body) return;

        if (!rows.length) {
            body.innerHTML = '<tr id="mf-empty-row"><td colspan="9" class="px-4 py-10 text-center text-gray-400">' +
                '<i data-lucide="inbox" class="h-8 w-8 mx-auto mb-2 text-gray-300"></i>No missing files recorded yet.</td></tr>';
            refreshIcons();
            return;
        }

        body.innerHTML = rows.map(rowHtml).join('');
        refreshIcons();
    }

    function transitFlag(r) {
        if (!r.in_transit) return '';
        const loc = r.transit_location ? ' → ' + esc(r.transit_location) : '';
        const title = r.transit_tracking_id ? ' title="' + esc(r.transit_tracking_id) + '"' : '';
        return '<div class="mt-1"><span' + title + ' class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-semibold text-amber-700 border border-amber-200">' +
            '<i data-lucide="truck" class="h-3 w-3"></i>In-Transit' + loc + '</span></div>';
    }

    function rowHtml(r) {
        const rack = [r.rack_primary, r.rack_secondary].filter(Boolean).join(' / ') || '—';
        return '' +
            '<tr class="hover:bg-gray-50" data-row-id="' + r.id + '">' +
                '<td class="px-4 py-3 font-semibold text-gray-900">' + esc(r.file_number) + transitFlag(r) + '</td>' +
                '<td class="px-4 py-3 text-gray-700">' + esc(r.archive_registry || '—') + '</td>' +
                '<td class="px-4 py-3 text-gray-700">' + esc(rack) + '</td>' +
                '<td class="px-4 py-3 text-gray-700">' + esc(r.shelf_number || '—') + '</td>' +
                '<td class="px-4 py-3"><span class="inline-flex items-center rounded-md bg-indigo-50 px-2 py-0.5 text-xs font-semibold text-indigo-700 border border-indigo-100">' + esc(r.full_label || '—') + '</span></td>' +
                '<td class="px-4 py-3 text-gray-700">' + esc(r.reported_by_name || '—') + '</td>' +
                '<td class="px-4 py-3 text-gray-500 whitespace-nowrap">' + esc(formatDate(r.created_at)) + '</td>' +
                '<td class="px-4 py-3">' + statusBadge(r.status) + '</td>' +
                '<td class="px-4 py-3 text-right whitespace-nowrap">' +
                    (r.status === 'Found' ? '' :
                        '<button type="button" data-action="found" data-id="' + r.id + '" title="Mark as found" ' +
                        'class="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium text-green-700 bg-green-50 border border-green-200 hover:bg-green-100 mr-1">' +
                        '<i data-lucide="check" class="h-3.5 w-3.5"></i></button>') +
                    '<button type="button" data-action="delete" data-id="' + r.id + '" title="Delete" ' +
                    'class="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium text-red-700 bg-red-50 border border-red-200 hover:bg-red-100">' +
                    '<i data-lucide="trash-2" class="h-3.5 w-3.5"></i></button>' +
                '</td>' +
            '</tr>';
    }

    function renderPagination(pagination) {
        const countEl = document.getElementById('mf-count');
        const tabCountEl = document.getElementById('mf-tab-count');
        const fromEl = document.getElementById('mf-page-from');
        const toEl = document.getElementById('mf-page-to');
        const totalEl = document.getElementById('mf-page-total');
        const currentEl = document.getElementById('mf-page-current');
        const lastEl = document.getElementById('mf-page-last');
        const prevBtn = document.getElementById('mf-prev-btn');
        const nextBtn = document.getElementById('mf-next-btn');

        const p = pagination || { current_page: 1, last_page: 1, total: 0, per_page: 25 };
        state.page = p.current_page || 1;

        const from = p.total === 0 ? 0 : ((p.current_page - 1) * p.per_page) + 1;
        const to = Math.min(p.current_page * p.per_page, p.total);

        if (countEl) countEl.textContent = p.total;
        if (tabCountEl) tabCountEl.textContent = p.total;
        if (fromEl) fromEl.textContent = from;
        if (toEl) toEl.textContent = to;
        if (totalEl) totalEl.textContent = p.total;
        if (currentEl) currentEl.textContent = p.current_page;
        if (lastEl) lastEl.textContent = p.last_page;
        if (prevBtn) prevBtn.disabled = p.current_page <= 1;
        if (nextBtn) nextBtn.disabled = p.current_page >= p.last_page;
    }

    function statusBadge(status) {
        const s = String(status || 'Missing');
        let cls = 'bg-rose-50 text-rose-700 border-rose-200';
        if (s === 'Found') cls = 'bg-green-50 text-green-700 border-green-200';
        else if (s === 'Searching') cls = 'bg-amber-50 text-amber-700 border-amber-200';
        return '<span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold border ' + cls + '">' + esc(s) + '</span>';
    }

    /* ---------------------------------------------------------------- Helpers */

    function fadeOutRow(id, done) {
        const row = document.querySelector('[data-row-id="' + id + '"]');
        if (!row) { if (done) done(); return; }
        row.style.transition = 'opacity .35s, background-color .35s';
        row.style.backgroundColor = '#f0fdf4'; // green-50 flash on success
        row.style.opacity = '0';
        setTimeout(function () { if (done) done(); }, 380);
    }

    function resetForm() {
        setValue('mf-file-no', '');
        setValue('mf-tracking-id', '');
        clearDuplicateWarning();
        clearTransitNotice();
        const reg = document.getElementById('mf-archive-registry');
        if (reg) reg.value = 'Registry 1';
        const rp = document.getElementById('mf-rack-primary');
        if (rp) rp.selectedIndex = 0;
        const rs = document.getElementById('mf-rack-secondary');
        if (rs) rs.value = '';
        const sh = document.getElementById('mf-shelf-number');
        if (sh) sh.selectedIndex = 0;
        updateFullLabel();
    }

    function jsonHeaders() {
        return {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': CSRF,
            'X-Requested-With': 'XMLHttpRequest',
        };
    }

    function parseJson(response) {
        return response.json().catch(function () { return null; });
    }

    function setBusy(btn, busy) {
        if (!btn) return;
        btn.disabled = busy;
        btn.classList.toggle('opacity-60', busy);
        btn.classList.toggle('cursor-not-allowed', busy);
    }

    function on(id, evt, fn) {
        const el = document.getElementById(id);
        if (el) el.addEventListener(evt, fn);
    }

    function getValue(id) {
        const el = document.getElementById(id);
        return el ? (el.value || '') : '';
    }

    function setValue(id, val) {
        const el = document.getElementById(id);
        if (el) el.value = val == null ? '' : val;
    }

    function esc(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function formatDate(value) {
        if (!value) return '—';
        const parsed = Date.parse(value);
        if (Number.isNaN(parsed)) return String(value);
        const d = new Date(parsed);
        try {
            return d.toLocaleString(undefined, { year: 'numeric', month: 'short', day: '2-digit', hour: '2-digit', minute: '2-digit', hour12: false });
        } catch (e) {
            return d.toISOString().replace('T', ' ').slice(0, 16);
        }
    }

    function debounce(fn, wait) {
        let t;
        return function () {
            const args = arguments, ctx = this;
            clearTimeout(t);
            t = setTimeout(function () { fn.apply(ctx, args); }, wait);
        };
    }

    function refreshIcons() {
        if (typeof lucide !== 'undefined') {
            try { lucide.createIcons(); } catch (e) { /* noop */ }
        }
    }

    function toast(message, type) {
        const container = document.getElementById('mf-toast-container');
        if (!container) { console.log(message); return; }

        const palette = {
            success: 'bg-green-600',
            error: 'bg-red-600',
            info: 'bg-slate-800',
        };
        const el = document.createElement('div');
        el.className = 'pointer-events-auto px-4 py-2.5 rounded-lg shadow-lg text-white text-sm font-medium ' + (palette[type] || palette.info);
        el.textContent = message;
        container.appendChild(el);
        setTimeout(function () {
            el.style.transition = 'opacity .3s';
            el.style.opacity = '0';
            setTimeout(function () { el.remove(); }, 300);
        }, 2600);
    }
})();
