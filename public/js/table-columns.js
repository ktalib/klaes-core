/* Shared column preferences. Does not replace any table's rendering or styling. */
(() => {
    'use strict';
    if (window.KlaesTableColumns) return;
    const states = new Map();
    const spans = new WeakMap();
    const user = document.querySelector('meta[name="table-columns-user"]')?.content || 'guest';
    const normal = value => String(value || '').toLowerCase().replace(/[^a-z0-9]/g, '');
    const lockedNames = new Set(['sn', 'sno', 'serialno', 'serialnumber', 'rownumber', 'fileno', 'filenumber', 'filenumbers', 'filenos', 'filenodetails', 'filedetails', 'filetitle', 'filename', 'stfileno', 'stfilenumber', 'mlppfileno', 'mlppfilenumber', 'source', 'applicant', 'applicantname', 'landuse', 'landusetype', 'purpose', 'landusepurpose', 'location', 'propertylocation', 'actions', 'action']);
    function required(cell, label) {
        return cell.hasAttribute('data-column-required') || !label || label.trim() === '#'
            || cell.querySelector('input, button') !== null
            || [label, cell.dataset.sort, cell.dataset.field].some(value => lockedNames.has(normal(value)))
            || /^(mls|kangis|newkangis|oldkangis|legacy|current|new|old|primary)file(no|number)$/.test(normal(label));
    }
    // Build a logical grid so grouped headings and row/column spans retain alignment.
    function grid(rows) {
        const occupied = [];
        const cells = [];
        Array.from(rows).forEach((row, r) => {
            occupied[r] ||= [];
            let c = 0;
            Array.from(row.cells).forEach(cell => {
                while (occupied[r][c]) c++;
                const width = spans.get(cell) || cell.colSpan || 1;
                spans.set(cell, width);
                const height = cell.rowSpan === 0 ? rows.length - r : cell.rowSpan || 1;
                const indexes = Array.from({ length: width }, (_, i) => c + i);
                cells.push({ cell, indexes });
                for (let y = r; y < r + height; y++) {
                    occupied[y] ||= [];
                    indexes.forEach(x => { occupied[y][x] = cell; });
                }
                c += width;
            });
        });
        return { cells, last: occupied[occupied.length - 1] || [] };
    }
    function apiFor(table) {
        const $ = window.jQuery;
        return $?.fn?.dataTable?.isDataTable(table) ? $(table).DataTable() : null;
    }
    function save(state) {
        try { localStorage.setItem(state.key, JSON.stringify([...state.hidden])); } catch (_) { /* Storage may be unavailable. */ }
    }
    function apply(state) {
        if (state.applying) return;
        state.applying = true;
        try {
            state.columns.forEach(column => { if (column.required) state.hidden.delete(column.key); });
            if (state.api) {
                let changed = false;
                state.columns.forEach((column, i) => {
                    const visible = !state.hidden.has(column.key);
                    if (state.api.column(i).visible() !== visible) {
                        state.api.column(i).visible(visible, false);
                        changed = true;
                    }
                });
                if (changed) state.api.columns.adjust().draw(false);
                return;
            }
            // Most tables retain all columns; avoid walking their rows on unrelated UI updates.
            if (!state.hidden.size && !state.hadHidden) return;
            state.hadHidden = state.hidden.size > 0;
            const sections = [state.table.tHead, ...state.table.tBodies, state.table.tFoot].filter(Boolean);
            sections.forEach(section => grid(section.rows).cells.forEach(({ cell, indexes }) => {
                const visible = indexes.filter(i => !state.columns[i] || !state.hidden.has(state.columns[i].key)).length;
                cell.classList.toggle('klaes-column-hidden', visible === 0);
                if (visible && cell.colSpan !== visible) cell.colSpan = visible;
            }));
        } finally { state.applying = false; }
    }
    function open(state) {
        const dialog = document.createElement('dialog');
        dialog.className = 'klaes-columns-dialog';
        const heading = document.createElement('h2');
        heading.id = 'klaes-columns-heading';
        heading.textContent = 'Choose columns';
        dialog.setAttribute('aria-labelledby', heading.id);
        const description = document.createElement('p');
        description.textContent = 'Choose which fields appear. Required fields stay visible. Choices are saved in this browser.';
        const options = document.createElement('div');
        options.className = 'klaes-columns-options';
        state.columns.forEach(column => {
            const label = document.createElement('label');
            const input = document.createElement('input');
            input.type = 'checkbox';
            input.checked = !state.hidden.has(column.key);
            input.disabled = column.required;
            input.addEventListener('change', () => {
                if (input.checked) state.hidden.delete(column.key); else state.hidden.add(column.key);
                save(state);
                apply(state);
            });
            label.append(input, document.createTextNode(column.label || 'Selection'));
            if (column.required) {
                const badge = document.createElement('small');
                badge.textContent = 'Required';
                label.append(badge);
            }
            options.append(label);
        });
        const footer = document.createElement('div');
        footer.className = 'klaes-columns-footer';
        const reset = document.createElement('button');
        reset.type = 'button';
        reset.className = 'klaes-columns-button';
        reset.textContent = 'Show all';
        reset.onclick = () => {
            state.hidden.clear();
            save(state);
            apply(state);
            options.querySelectorAll('input').forEach(input => { input.checked = true; });
        };
        const done = document.createElement('button');
        done.type = 'button';
        done.className = 'klaes-columns-button';
        done.textContent = 'Done';
        done.onclick = () => dialog.close();
        footer.append(reset, done);
        dialog.append(heading, description, options, footer);
        dialog.addEventListener('close', () => dialog.remove());
        document.body.append(dialog);
        dialog.showModal();
    }
    function attach(table, ordinal) {
        if (!table.tHead || table.closest('[data-column-picker="off"], .dataTables_scrollHead, .dataTables_scrollFoot, .dt-scroll-head, .dt-scroll-foot') || table.querySelector('table')) return;
        const api = apiFor(table);
        const previous = states.get(table);
        if (previous && previous.head === table.tHead
            && previous.settings === (api ? api.settings()[0] : null)) { apply(previous); return; }
        if (previous) {
            previous.toolbar.remove();
            table.querySelectorAll('.klaes-column-hidden').forEach(cell => cell.classList.remove('klaes-column-hidden'));
        }
        const headers = api ? api.columns().header().toArray() : grid(table.tHead.rows).last;
        if (headers.length < 2) return;
        const counts = {};
        const columns = headers.map(cell => {
            const label = (cell.dataset.columnLabel || cell.textContent).replace(/[↑↓↕▲▼]/g, '').replace(/\s+/g, ' ').trim();
            const name = cell.dataset.columnKey || normal(label) || 'selection';
            counts[name] = (counts[name] || 0) + 1;
            return { label, key: name + ':' + counts[name], required: required(cell, label) };
        });
        const identity = table.dataset.columnTableKey || table.id || `table-${ordinal}-${columns.map(c => c.key).join(',')}`;
        const key = `klaes:columns:v1:${user}:${location.pathname}:${identity}`;
        let hidden = api ? columns.filter((column, i) => !api.column(i).visible()).map(column => column.key) : [];
        try { const value = JSON.parse(localStorage.getItem(key)); if (Array.isArray(value)) hidden = value; } catch (_) { /* Use defaults. */ }
        const toolbar = document.createElement('div');
        toolbar.className = 'klaes-columns-toolbar';
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'klaes-columns-button';
        button.textContent = 'Columns';
        button.setAttribute('aria-haspopup', 'dialog');
        const state = { table, api, columns, key, hidden: new Set(hidden), toolbar,
            head: table.tHead, settings: api ? api.settings()[0] : null };
        button.onclick = () => open(state);
        toolbar.append(button);
        const wrapper = table.closest('.dataTables_wrapper, .dt-container');
        (wrapper || table).before(toolbar);
        states.set(table, state);
        if (api) {
            window.jQuery(table).off('.klaesColumns').on('draw.dt.klaesColumns column-visibility.dt.klaesColumns', () => apply(state));
        }
        apply(state);
    }
    let pending = false;
    function refresh() {
        pending = false;
        states.forEach((state, table) => {
            if (!table.isConnected) { state.toolbar.remove(); states.delete(table); }
        });
        document.querySelectorAll('table').forEach(attach);
    }
    function schedule() {
        if (!pending) { pending = true; requestAnimationFrame(refresh); }
    }
    window.KlaesTableColumns = { refresh: schedule };
    function start() {
        refresh();
        new MutationObserver(records => {
            if (records.some(record => !record.target.closest?.('.klaes-columns-dialog, .klaes-columns-toolbar'))) schedule();
        }).observe(document.body, { childList: true, subtree: true });
        if (window.jQuery) window.jQuery(document).on('init.dt.klaesColumns destroy.dt.klaesColumns', schedule);
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start); else start();
})();
