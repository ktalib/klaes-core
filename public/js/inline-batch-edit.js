/**
 * Inline editing for the RofO / Land Recommendation tables (every tab, and the
 * expanded batch rows).
 *
 * Mark a cell as editable:
 *   <td data-inline-edit data-rec-id="123" data-field="plot_number" data-kind="text"
 *       data-value="12A" data-printed="0">12A</td>
 *
 * kinds:
 *   text              plain input
 *   money             amount; "1,250" and "₦1,250.00" both accepted
 *   charge            amount or a word (NIL, To follow)
 *   land_use_purpose  land use + purpose dropdowns, with one "Other" purpose;
 *                     data-value is JSON {land_use_id, purpose_id, purpose_text}
 *   location          District + LGA, with one "Other" LGA; data-value is JSON
 *                     {district, lga}. The location text is rebuilt on the server.
 *
 * Put an Edit mode switch above a table:   InlineBatchEdit.toggleHtml()
 * Once per page:
 *   InlineBatchEdit.init({ url: '/land-recommendations/__ID__/inline-update', csrf, canEdit,
 *                          lookups: { landUses, purposes, lgas, districtsUrl } });
 *
 * Nothing is editable until Edit mode is on. With it on, editable cells are
 * highlighted and a click opens them:
 *   Enter        save, then open the same column on the next row
 *   Shift+Enter  save, then open the same column on the row above
 *   Tab          save and stay
 *   Esc          cancel
 * Plain fields also save when you click away; the dropdown editors have Save and
 * Cancel buttons and close without saving when you click away.
 */
(function () {
    'use strict';

    var STORE_KEY = 'klaes.inlineBatchEdit.on';
    var cfg = { url: '', csrf: '', canEdit: false, on: false, lookups: {} };
    var active = null; // { cell, original, collect(), el, pop }

    function readOn() {
        try { return window.localStorage.getItem(STORE_KEY) === '1'; } catch (e) { return false; }
    }
    function writeOn(v) {
        try { window.localStorage.setItem(STORE_KEY, v ? '1' : '0'); } catch (e) { /* private mode */ }
    }

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function injectStyles() {
        if (document.getElementById('inline-batch-edit-css')) return;
        var css = ''
            + '.ibe-toggle{display:inline-flex;align-items:center;gap:8px;font-size:11px;font-weight:800;color:#334155;cursor:pointer;user-select:none;white-space:nowrap;text-transform:none;letter-spacing:normal}'
            + '.ibe-toggle input{position:absolute;opacity:0;width:0;height:0}'
            + '.ibe-toggle .ibe-track{position:relative;width:34px;height:18px;border-radius:999px;background:#cbd5e1;transition:background .15s;flex-shrink:0}'
            + '.ibe-toggle .ibe-track::after{content:"";position:absolute;top:2px;left:2px;width:14px;height:14px;border-radius:50%;background:#fff;box-shadow:0 1px 2px rgba(0,0,0,.25);transition:transform .15s}'
            + '.ibe-toggle input:checked + .ibe-track{background:#7c3aed}'
            + '.ibe-toggle input:checked + .ibe-track::after{transform:translateX(16px)}'
            + '.ibe-toggle input:focus-visible + .ibe-track{outline:2px solid #a78bfa;outline-offset:2px}'
            + '.ibe-toggle.ibe-disabled{cursor:not-allowed;color:#94a3b8}'
            + '.ibe-toggle.ibe-disabled .ibe-track{background:#e2e8f0}'
            + '.ibe-toggle .ibe-hint{font-weight:600;color:#6d28d9;display:none}'
            + 'html.ibe-mode .ibe-toggle .ibe-hint{display:inline}'
            + 'html.ibe-mode [data-inline-edit].ibe-on{cursor:text;background:#fefce8!important;box-shadow:inset 0 -2px 0 #facc15}'
            + 'html.ibe-mode [data-inline-edit].ibe-on:hover{outline:1px dashed #7c3aed;outline-offset:-2px;background:#f5f3ff!important}'
            + '[data-inline-edit].ibe-editing{outline:2px solid #7c3aed!important;outline-offset:-2px;background:#fff!important;box-shadow:none!important}'
            + '[data-inline-edit] .ibe-input{width:100%;min-width:90px;border:0;outline:0;background:transparent;font:inherit;color:#0f172a;padding:2px}'
            + '[data-inline-edit].ibe-saving{opacity:.55;pointer-events:none}'
            + '[data-inline-edit].ibe-saved{animation:ibe-flash 1.4s ease-out}'
            + '[data-inline-edit].ibe-error{outline:2px solid #dc2626!important;outline-offset:-2px}'
            + '@keyframes ibe-flash{0%{background:#bbf7d0}100%{background:transparent}}'
            // Popover editor for the dropdown fields
            + '.ibe-pop{position:absolute;z-index:1000200;background:#fff;border:1px solid #c4b5fd;border-radius:10px;box-shadow:0 12px 30px rgba(15,23,42,.18);padding:10px;width:300px;font-size:12px;color:#0f172a;text-transform:none;letter-spacing:normal;font-weight:400;text-align:left}'
            + '.ibe-pop label{display:block;font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.06em;color:#64748b;margin:6px 0 3px}'
            + '.ibe-pop select,.ibe-pop input{width:100%;border:1px solid #cbd5e1;border-radius:6px;padding:5px 7px;font-size:12px;background:#fff;box-sizing:border-box}'
            + '.ibe-pop select:focus,.ibe-pop input:focus{outline:2px solid #a78bfa;outline-offset:0;border-color:#7c3aed}'
            + '.ibe-pop .ibe-other{margin-top:5px;background:#fffbeb;border-color:#fcd34d}'
            + '.ibe-pop .ibe-err{color:#b91c1c;font-weight:700;margin-top:6px;min-height:0}'
            + '.ibe-pop .ibe-note{color:#64748b;margin-top:6px}'
            + '.ibe-pop .ibe-actions{display:flex;justify-content:flex-end;gap:6px;margin-top:9px}'
            + '.ibe-pop .ibe-actions button{border-radius:6px;padding:5px 12px;font-size:12px;font-weight:700;border:0;cursor:pointer}'
            + '.ibe-pop .ibe-save{background:#7c3aed;color:#fff}'
            + '.ibe-pop .ibe-cancel{background:#e2e8f0;color:#334155}';
        var s = document.createElement('style');
        s.id = 'inline-batch-edit-css';
        s.textContent = css;
        document.head.appendChild(s);
    }

    function toast(icon, title, text) {
        if (window.Swal && !Swal.isVisible()) {
            Swal.fire({ toast: true, position: 'top-end', icon: icon, title: title, text: text || '',
                showConfirmButton: false, timer: icon === 'error' ? 5000 : 2600, timerProgressBar: true });
        } else if (icon === 'error' && !window.Swal) {
            alert(title + (text ? '\n' + text : ''));
        }
    }

    function display(v) {
        return v === '' || v === null || v === undefined ? '—' : v;
    }

    function parseJson(s) {
        try { var o = JSON.parse(s || '{}'); return o && typeof o === 'object' ? o : {}; } catch (e) { return {}; }
    }

    function setMode(on) {
        cfg.on = !!(on && cfg.canEdit);
        document.documentElement.classList.toggle('ibe-mode', cfg.on);
        document.querySelectorAll('input[data-ibe-toggle]').forEach(function (cb) { cb.checked = cfg.on; });
        if (!cfg.on && active) cancel();
    }

    function decorate(root) {
        root = root || document;
        root.querySelectorAll('input[data-ibe-toggle]').forEach(function (cb) { cb.checked = cfg.on; });
        if (!cfg.canEdit) return;
        root.querySelectorAll('[data-inline-edit]').forEach(function (td) {
            if (!td.classList.contains('ibe-on')) td.classList.add('ibe-on');
        });
    }

    function kindOf(cell) { return cell.getAttribute('data-kind') || 'text'; }

    // ── Plain editors (text / money / charge) ────────────────────────────────
    function openPlain(cell) {
        var original = cell.getAttribute('data-value') || '';
        cell.classList.remove('ibe-error');
        cell.classList.add('ibe-editing');
        cell.dataset.ibeHtml = cell.innerHTML;
        cell.innerHTML = '';

        var input = document.createElement('input');
        input.type = 'text';
        input.className = 'ibe-input';
        input.value = original;
        if (kindOf(cell) === 'money') input.setAttribute('inputmode', 'decimal');
        input.setAttribute('aria-label', 'Edit ' + (cell.getAttribute('data-field') || 'value'));
        cell.appendChild(input);

        active = {
            cell: cell,
            el: input,
            unchanged: function () { return input.value.replace(/\s+/g, ' ').trim() === original.replace(/\s+/g, ' ').trim(); },
            payload: function () { return { value: input.value.replace(/\s+/g, ' ').trim() }; },
            pending: function () { return input.value.replace(/\s+/g, ' ').trim(); }
        };
        input.focus();
        input.select();

        input.addEventListener('keydown', keyHandler);
        input.addEventListener('blur', function () {
            if (active && active.el === input) commit(0);
        });
    }

    function keyHandler(e) {
        if (e.key === 'Enter') {
            e.preventDefault(); e.stopPropagation();
            commit(e.shiftKey ? -1 : 1);
        } else if (e.key === 'Escape') {
            e.preventDefault(); e.stopPropagation();
            cancel();
        } else if (e.key === 'Tab' && !(active && active.pop)) {
            e.preventDefault();
            commit(0);
        }
    }

    // ── Popover editors (land use / purpose, location) ───────────────────────
    function openPopover(cell) {
        cell.classList.remove('ibe-error');
        cell.classList.add('ibe-editing');

        var kind = kindOf(cell);
        var val = parseJson(cell.getAttribute('data-value'));
        var pop = document.createElement('div');
        pop.className = 'ibe-pop';
        pop.setAttribute('role', 'dialog');

        var built = kind === 'land_use_purpose' ? buildLandUsePurpose(pop, val) : buildLocation(pop, val, cell);

        pop.insertAdjacentHTML('beforeend',
            '<div class="ibe-err" hidden></div>'
            + '<div class="ibe-actions"><button type="button" class="ibe-cancel">Cancel</button>'
            + '<button type="button" class="ibe-save">Save</button></div>');
        document.body.appendChild(pop);

        // Below the cell, kept inside the viewport.
        var r = cell.getBoundingClientRect();
        var left = Math.min(r.left + window.scrollX, window.scrollX + document.documentElement.clientWidth - pop.offsetWidth - 8);
        var top = r.bottom + window.scrollY + 4;
        if (r.bottom + pop.offsetHeight + 8 > window.innerHeight && r.top > pop.offsetHeight + 8) {
            top = r.top + window.scrollY - pop.offsetHeight - 4;
        }
        pop.style.left = Math.max(8, left) + 'px';
        pop.style.top = top + 'px';

        var errEl = pop.querySelector('.ibe-err');
        active = {
            cell: cell,
            pop: pop,
            el: pop,
            unchanged: built.unchanged,
            payload: built.payload,
            pending: built.summary,
            error: function (m) { errEl.textContent = m; errEl.hidden = !m; }
        };

        pop.addEventListener('keydown', function (e) {
            // Enter inside the "Other" text box or a select saves; Esc cancels.
            if (e.key === 'Enter' || e.key === 'Escape') keyHandler(e);
        });
        pop.querySelector('.ibe-save').addEventListener('click', function () { commit(0); });
        pop.querySelector('.ibe-cancel').addEventListener('click', function () { cancel(); });
        pop.addEventListener('click', function (e) { e.stopPropagation(); });

        var first = pop.querySelector('select, input');
        if (first) first.focus();
    }

    function optionHtml(value, label, selected) {
        return '<option value="' + esc(value) + '"' + (selected ? ' selected' : '') + '>' + esc(label) + '</option>';
    }

    function buildLandUsePurpose(pop, val) {
        var L = cfg.lookups || {};
        var landUses = L.landUses || [], purposes = L.purposes || [];
        var luId = String(val.land_use_id || '');
        var pId = val.purpose_id == null ? '' : String(val.purpose_id);
        var pText = String(val.purpose_text || '');

        // A purpose typed under "Other" earlier: reopen it there, with its words.
        var listed = purposes.some(function (p) { return String(p.id) === pId; });
        var useOther = !listed && pText !== '' && !purposes.some(function (p) {
            return String(p.land_use_id) === luId && p.name.toUpperCase() === pText.toUpperCase();
        });
        if (!listed && !useOther && pText !== '') {
            var m = purposes.filter(function (p) { return String(p.land_use_id) === luId && p.name.toUpperCase() === pText.toUpperCase(); })[0];
            if (m) pId = String(m.id);
        }

        pop.insertAdjacentHTML('beforeend',
            '<label>Land Use</label><select data-ibe="lu"><option value="">— Select —</option>'
            + landUses.map(function (l) { return optionHtml(l.id, l.name, String(l.id) === luId); }).join('')
            + '</select>'
            + '<label>Purpose</label><select data-ibe="p"></select>'
            + '<input data-ibe="po" class="ibe-other" type="text" placeholder="Specify the purpose…" hidden>');

        var lu = pop.querySelector('[data-ibe="lu"]');
        var p = pop.querySelector('[data-ibe="p"]');
        var po = pop.querySelector('[data-ibe="po"]');

        var fillPurposes = function (keep) {
            var list = purposes.filter(function (x) { return String(x.land_use_id) === lu.value; });
            p.innerHTML = '<option value="">— Select —</option>'
                + list.map(function (x) { return optionHtml(x.id, x.name, keep && String(x.id) === pId); }).join('')
                + optionHtml('other', 'Other (specify)…', keep && useOther);
            p.disabled = !lu.value;
            syncOther();
        };
        var syncOther = function () {
            var on = p.value === 'other';
            po.hidden = !on;
            po.disabled = !on;
        };

        fillPurposes(true);
        if (useOther) { po.value = pText; }
        lu.addEventListener('change', function () { fillPurposes(false); });
        p.addEventListener('change', function () { syncOther(); if (!po.hidden) po.focus(); });

        var current = function () {
            return { land_use_id: lu.value, purpose_id: p.value, purpose_other: p.value === 'other' ? po.value.replace(/\s+/g, ' ').trim() : '' };
        };
        var start = current();
        return {
            unchanged: function () { var c = current(); return c.land_use_id === start.land_use_id && c.purpose_id === start.purpose_id && c.purpose_other === start.purpose_other; },
            payload: function () {
                var c = current();
                if (!c.land_use_id) throw new Error('Pick a land use.');
                if (!c.purpose_id) throw new Error('Pick a purpose.');
                if (c.purpose_id === 'other' && !c.purpose_other) throw new Error('Specify the purpose.');
                return c;
            },
            summary: function () {
                var luName = lu.options[lu.selectedIndex] ? lu.options[lu.selectedIndex].text : '';
                var pName = p.value === 'other' ? po.value : (p.options[p.selectedIndex] ? p.options[p.selectedIndex].text : '');
                return luName + (pName ? ' (' + pName + ')' : '');
            }
        };
    }

    function buildLocation(pop, val, cell) {
        var L = cfg.lookups || {};
        var lgas = L.lgas || [];
        var district = String(val.district || '');
        var lga = String(val.lga || '');

        // Older records hold only the location text ("ZAKIRAI, DAWAKIN KUDU"), with no
        // district or LGA saved. Read them back out of it as a starting point.
        var note = '';
        if (!district && !lga) {
            var text = String(cell.getAttribute('data-location-text') || '').trim();
            var hit = '';
            lgas.forEach(function (g) {
                var re = new RegExp('(^|[\\s,])' + g.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '([\\s,]|$)', 'i');
                if (!hit && re.test(text)) hit = g;
            });
            if (hit) {
                lga = hit;
                district = text.replace(new RegExp(hit.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'i'), '')
                    .replace(/kano state/i, '').replace(/[,\s]+$/, '').replace(/^[,\s]+/, '').replace(/\s*,\s*/g, ' ').trim();
            } else {
                district = text.split(',')[0].trim();
            }
            if (text) note = 'Filled from the current location "' + text + '" — check before saving.';
        }

        var lgaListed = lgas.some(function (g) { return g.toLowerCase() === lga.toLowerCase(); });
        var listId = 'ibe-districts-' + Date.now();
        pop.insertAdjacentHTML('beforeend',
            '<label>District</label><input data-ibe="d" type="text" autocomplete="off" list="' + listId + '" placeholder="Start typing a district…">'
            + '<datalist id="' + listId + '"></datalist>'
            + '<label>LGA</label><select data-ibe="g"><option value="">— Select —</option>'
            + lgas.map(function (g) { return optionHtml(g, g, g.toLowerCase() === lga.toLowerCase()); }).join('')
            + optionHtml('other', 'Other (specify)…', lga !== '' && !lgaListed)
            + '</select>'
            + '<input data-ibe="go" class="ibe-other" type="text" placeholder="Specify the LGA…" hidden>'
            + (note ? '<div class="ibe-note">' + esc(note) + '</div>' : '')
            + '<div class="ibe-note">Location is rebuilt from District and LGA, as on the Edit form.</div>');

        var d = pop.querySelector('[data-ibe="d"]');
        var g = pop.querySelector('[data-ibe="g"]');
        var go = pop.querySelector('[data-ibe="go"]');
        var dl = pop.querySelector('datalist');
        d.value = district;
        if (lga !== '' && !lgaListed) go.value = lga;

        var syncOther = function () { var on = g.value === 'other'; go.hidden = !on; go.disabled = !on; };
        syncOther();
        g.addEventListener('change', function () { syncOther(); if (!go.hidden) go.focus(); });

        // District suggestions from the same lookup the Edit form uses.
        var timer = null;
        d.addEventListener('input', function () {
            clearTimeout(timer);
            var q = d.value.trim();
            if (q.length < 2 || !L.districtsUrl) return;
            timer = setTimeout(function () {
                fetch(L.districtsUrl + (L.districtsUrl.indexOf('?') === -1 ? '?' : '&') + 'search=' + encodeURIComponent(q),
                    { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
                    .then(function (r) { return r.json(); })
                    .then(function (j) {
                        var rows = (j && j.data) || [];
                        dl.innerHTML = rows.slice(0, 30).map(function (x) { return '<option value="' + esc(x.name) + '">'; }).join('');
                    })
                    .catch(function () { /* suggestions only */ });
            }, 250);
        });

        var current = function () {
            return {
                district: d.value.replace(/\s+/g, ' ').trim(),
                lga: (g.value === 'other' ? go.value : g.value).replace(/\s+/g, ' ').trim()
            };
        };
        var start = { district: String(val.district || ''), lga: String(val.lga || '') };
        return {
            unchanged: function () { var c = current(); return c.district === start.district && c.lga === start.lga; },
            payload: function () {
                var c = current();
                if (!c.district) throw new Error('Enter the district.');
                if (!c.lga) throw new Error(g.value === 'other' ? 'Specify the LGA.' : 'Pick an LGA.');
                return c;
            },
            summary: function () { var c = current(); return c.district + ' ' + c.lga; }
        };
    }

    // ── Shared open / close / save ───────────────────────────────────────────
    function open(cell) {
        if (!cfg.canEdit || !cfg.on || !cell || active) return;
        var k = kindOf(cell);
        if (k === 'land_use_purpose' || k === 'location') openPopover(cell);
        else openPlain(cell);
    }

    function closeActive(restoreHtml) {
        var a = active;
        active = null;
        if (!a) return null;
        a.cell.classList.remove('ibe-editing');
        if (a.pop) {
            a.pop.remove();
        } else if (restoreHtml) {
            a.cell.innerHTML = a.cell.dataset.ibeHtml || '';
        }
        delete a.cell.dataset.ibeHtml;
        return a;
    }

    function cancel() { closeActive(true); }

    function neighbour(cell, dir) {
        var field = cell.getAttribute('data-field');
        var tr = cell.closest('tr');
        while (tr) {
            tr = dir > 0 ? tr.nextElementSibling : tr.previousElementSibling;
            if (!tr) return null;
            if (tr.offsetParent === null) continue; // hidden by a filter
            var next = tr.querySelector('[data-inline-edit][data-field="' + field + '"]');
            if (next) return next;
        }
        return null;
    }

    function commit(move) {
        if (!active) return;
        var a = active;

        var goNext = function (cell) {
            if (!move) return;
            var n = neighbour(cell, move);
            if (n) { n.scrollIntoView({ block: 'nearest' }); open(n); }
        };

        if (a.unchanged()) {
            closeActive(true);
            goNext(a.cell);
            return;
        }

        var payload;
        try {
            payload = a.payload();
        } catch (err) {
            if (a.error) { a.error(err.message); return; } // popover stays open
            closeActive(true);
            a.cell.classList.add('ibe-error');
            toast('error', 'Not saved', err.message);
            return;
        }

        var cell = a.cell;
        var shownBefore = a.pop ? cell.innerHTML : (cell.dataset.ibeHtml || '');
        var pendingText = a.pending ? a.pending() : '';
        closeActive(false);
        cell.textContent = pendingText || '…';
        cell.classList.add('ibe-saving');

        payload.field = cell.getAttribute('data-field');
        var url = cfg.url.replace('__ID__', encodeURIComponent(cell.getAttribute('data-rec-id')));
        fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': cfg.csrf,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(payload)
        })
            .then(function (r) {
                return r.json().catch(function () { return {}; }).then(function (d) {
                    if (r.status === 403) d.message = d.message || 'You do not have permission to edit this record.';
                    if (r.status === 419) d.message = 'Your session has expired. Reload the page and try again.';
                    if (!r.ok || !d.success) throw new Error(d.message || ('Save failed (' + r.status + ').'));
                    return d;
                });
            })
            .then(function (d) {
                cell.classList.remove('ibe-saving');
                cell.setAttribute('data-value', d.value == null ? '' : d.value);
                if (cell.hasAttribute('data-location-text')) cell.setAttribute('data-location-text', d.display || '');
                var shown = (cell.getAttribute('data-display') === 'label' && d.display_label != null)
                    ? d.display_label
                    : (d.display != null ? d.display : d.value);
                cell.textContent = display(shown);
                cell.classList.remove('ibe-saved');
                void cell.offsetWidth;
                cell.classList.add('ibe-saved');

                var tr = cell.closest('tr');
                if (tr && tr.hasAttribute('data-hay')) {
                    tr.setAttribute('data-hay', tr.textContent.replace(/\s+/g, ' ').toLowerCase());
                }

                var printed = parseInt(d.print_count != null ? d.print_count : cell.getAttribute('data-printed'), 10) || 0;
                if (!d.unchanged && printed > 0) {
                    toast('info', 'Saved', 'Already printed ' + printed + '×. Reprint to get the change on paper.');
                }
                goNext(cell);
            })
            .catch(function (err) {
                cell.classList.remove('ibe-saving');
                cell.innerHTML = shownBefore;
                cell.classList.add('ibe-error');
                toast('error', 'Not saved', err.message || 'Network error.');
            });
    }

    // While Edit mode is on, a click on an editable cell opens it and goes no further.
    function onCellActivate(e) {
        if (!cfg.canEdit || !cfg.on) return;
        if (e.target.closest('.ibe-pop')) return;
        var cell = e.target.closest('[data-inline-edit]');
        if (!cell) return;
        if (cell.classList.contains('ibe-editing')) { e.stopPropagation(); return; }
        e.preventDefault();
        e.stopPropagation();
        if (window.getSelection) window.getSelection().removeAllRanges();
        if (active) {
            // Another cell is open: a plain one saves on blur already; a popover closes.
            if (active.pop) cancel();
            else return;
        }
        open(cell);
    }
    document.addEventListener('click', onCellActivate, true);
    document.addEventListener('dblclick', onCellActivate, true);

    // A click anywhere else closes an open popover without saving.
    document.addEventListener('mousedown', function (e) {
        if (active && active.pop && !e.target.closest('.ibe-pop') && !e.target.closest('.ibe-editing')) cancel();
    });

    document.addEventListener('change', function (e) {
        if (!e.target.matches || !e.target.matches('input[data-ibe-toggle]')) return;
        writeOn(e.target.checked);
        setMode(e.target.checked);
        if (cfg.on) toast('info', 'Edit mode on', 'Click a highlighted cell to edit. Enter saves and moves down.');
    });

    window.InlineBatchEdit = {
        init: function (opts) {
            cfg.url = opts.url || '';
            cfg.csrf = opts.csrf || (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
            cfg.canEdit = !!opts.canEdit;
            cfg.lookups = opts.lookups || {};
            injectStyles();
            setMode(readOn());
            decorate(document);
        },
        decorate: decorate,
        isOn: function () { return cfg.on; },
        canEdit: function () { return cfg.canEdit; },
        toggleHtml: function () {
            // Hidden entirely for anyone who may not edit (System Admins only for now).
            if (!cfg.canEdit) return '';
            return '<label class="ibe-toggle" title="Edit details directly in the table">'
                + '<input type="checkbox" data-ibe-toggle' + (cfg.on ? ' checked' : '') + '>'
                + '<span class="ibe-track"></span> Edit mode'
                + '<span class="ibe-hint">· click a highlighted cell · Enter saves &amp; moves down</span></label>';
        },
        // Attributes that make a JS-rendered cell editable; empty when editing is off.
        //   attrs(rec.id, 'plot_number', 'text', rec.plot_number, {printed: n})
        //   attrs(rec.id, 'location', 'location', {district, lga}, {locationText: rec.location})
        attrs: function (recId, field, kind, value, opts) {
            if (!cfg.canEdit) return '';
            opts = opts || {};
            var v = (value !== null && typeof value === 'object') ? JSON.stringify(value) : (value == null ? '' : value);
            return ' data-inline-edit data-rec-id="' + esc(recId) + '" data-field="' + esc(field) + '"'
                + ' data-kind="' + esc(kind || 'text') + '" data-value="' + esc(v) + '"'
                + ' data-printed="' + (parseInt(opts.printed, 10) || 0) + '"'
                + (opts.locationText != null ? ' data-location-text="' + esc(opts.locationText) + '"' : '');
        }
    };
})();
