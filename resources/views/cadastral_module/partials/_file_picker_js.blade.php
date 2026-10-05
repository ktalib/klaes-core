{{--
    Behaviour for partials/_file_picker. Loaded once per page, after the
    global selector's own script. See the picker partial for the contract.

    Runs after DOMContentLoaded plus a tick, like the module's other form
    scripts: the address builder (pushed later by the layout) must have built
    its Select2 boxes before they can be filled or locked.
--}}
<style>
    .survey-proto .cad-fp-row { display: flex; gap: 8px; align-items: stretch; }
    .survey-proto .cad-fp-row .cad-fp-number { flex: 1; font-weight: 700; letter-spacing: .02em; }
    .survey-proto .cad-fp-row .btn { white-space: nowrap; display: inline-flex; align-items: center; gap: 6px; }
    .survey-proto .cad-locked,
    .survey-proto input:disabled,
    .survey-proto select:disabled,
    .survey-proto textarea:disabled { background: var(--gray-100) !important; color: var(--gray-700); cursor: not-allowed; }
    .survey-proto .select2-container--disabled .select2-selection { background: var(--gray-100) !important; cursor: not-allowed; }
    .survey-proto .cad-fp-filled > label::after {
        content: 'from file'; margin-left: 6px; padding: 1px 6px; border-radius: 999px;
        background: #e2e8f0; color: #475569; font-size: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: .03em;
    }
    .survey-proto .cad-fp-status { margin-top: 8px; font-size: 13px; color: var(--gray-600); }
    .survey-proto .cad-fp-status.is-error { color: #b91c1c; }
    .survey-proto .cad-fp-card {
        margin-top: 10px; border: 1px solid var(--gray-200); border-left: 4px solid #16a34a;
        border-radius: 8px; background: #fff; padding: 12px 14px;
    }
    .survey-proto .cad-fp-card.is-refused { border-left-color: #dc2626; background: #fffafa; }
    .survey-proto .cad-fp-card-head { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-bottom: 8px; }
    .survey-proto .cad-fp-card-head strong { font-size: 15px; color: var(--gray-900); }
    .survey-proto .cad-fp-card-head .cad-fp-type { font-size: 12px; color: var(--gray-600); }
    .survey-proto .cad-fp-facts { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 6px 16px; font-size: 13px; }
    .survey-proto .cad-fp-facts span { display: block; font-size: 11px; color: var(--gray-500); text-transform: uppercase; letter-spacing: .03em; }
    .survey-proto .cad-fp-flags { display: flex; gap: 6px; flex-wrap: wrap; margin-top: 10px; font-size: 12px; }
    .survey-proto .cad-fp-refusal { margin-top: 10px; font-size: 13px; color: #991b1b; display: flex; gap: 6px; align-items: flex-start; }
    .survey-proto .cad-fp-choices { margin-top: 10px; border: 1px solid #fcd34d; background: #fffbeb; border-radius: 8px; padding: 10px 12px; }
    .survey-proto .cad-fp-choice {
        display: flex; justify-content: space-between; align-items: center; gap: 10px;
        padding: 8px 0; border-top: 1px dashed #fde68a; font-size: 13px;
    }
    .survey-proto .cad-fp-choice:first-of-type { border-top: none; }
    .survey-proto .cad-fp-choice small { display: block; color: var(--gray-600); }
</style>

<script>
(function () {
    'use strict';

    // Fill order: a select before its "specify" box, because changing the
    // select resets the box.
    var VALUE_FIELDS = ['file_title', 'plot_no', 'prop_district', 'prop_district_other', 'prop_street',
                        'prop_street_other', 'prop_lga', 'prop_state', 'prop_house', 'prop_plot'];
    var PAIRED = { prop_district_other: 'prop_district', prop_street_other: 'prop_street' };
    var LEVEL = { ok: 'completed', info: 'review', warn: 'pending', danger: 'rejected' };

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }
    function icons() { if (window.lucide && window.lucide.createIcons) window.lucide.createIcons(); }
    function $j() { return window.jQuery && window.jQuery.fn && window.jQuery.fn.select2 ? window.jQuery : null; }

    function Picker(root) {
        this.root     = root;
        this.form     = root.closest('form') || root.parentNode;
        this.scope    = root.dataset.scope || 'indexed';
        this.purpose  = root.dataset.purpose || '';
        this.url      = root.dataset.url;
        this.fixed    = root.dataset.fixed === '1';
        this.navigate = root.dataset.navigate || '';
        this.required = root.dataset.required === '1';
        this.map      = {};
        try { this.map = JSON.parse(root.dataset.map || '{}') || {}; } catch (e) { /* identity */ }
        this.number   = root.querySelector('.cad-fp-number');
        this.statusEl = root.querySelector('.cad-fp-status');
        this.choices  = root.querySelector('.cad-fp-choices');
        this.card     = root.querySelector('.cad-fp-card');
        this.clearBtn = root.querySelector('.cad-fp-clear');
        this.locked   = [];
        this.payload  = null;
        this.query    = {};
        root._picker  = this;

        var self = this;
        var open = root.querySelector('.cad-fp-open');
        if (open) open.addEventListener('click', function () { self.open(); });
        if (this.clearBtn) this.clearBtn.addEventListener('click', function () { self.clear(); });

        this.choices.addEventListener('click', function (e) {
            var b = e.target.closest('[data-fp-choose]');
            if (!b) return;
            self.resolve({ file_number: self.query.file_number || '', tab: self.query.tab || '', file_indexing_id: b.getAttribute('data-fp-choose') });
        });

        // A wizard asks each step whether it may be left; outside a wizard the
        // form's own submit is guarded.
        var step = root.closest('[data-step]');
        if (step) {
            step.addEventListener('wizard:validate', function (e) {
                var why = self.problem();
                if (why) e.detail.fail(why, self.number);
            });
        } else if (this.form && this.form.tagName === 'FORM') {
            this.form.addEventListener('submit', function (e) {
                var why = self.problem();
                if (why) { e.preventDefault(); self.status(why, true); }
            });
        }

        if (root.dataset.initial) {
            try { this.apply(JSON.parse(root.dataset.initial), true); } catch (e) { /* leave it empty */ }
        }
    }

    /** Why the form cannot go on with what is picked, or '' when it can. */
    Picker.prototype.problem = function () {
        if (!this.required || this.fixed) return '';
        var p = this.payload;
        if (p && p.status === 'ambiguous') return 'Choose which of the matching files you mean.';
        if (p && p.status !== 'ok') return p.message || 'That file cannot be used here.';
        var hidden = this.root.querySelectorAll('[data-fp-hidden]');
        if (!hidden.length) return p ? '' : 'Select the file number first.';
        for (var i = 0; i < hidden.length; i++) if (hidden[i].value) return '';
        return 'Select the file number first.';
    };

    Picker.prototype.open = function () {
        var self = this;
        if (!window.GlobalFileNoModal || typeof window.GlobalFileNoModal.open !== 'function') {
            this.status('The file number selector is not available on this page. Reload and try again.', true);
            return;
        }
        window.GlobalFileNoModal.open({
            // The selector would otherwise write the number into any field
            // called file_number on the page; this picker fills the form itself.
            autoPopulateGenericFields: false,
            targetFields: [],
            callback: function (fileData) {
                var n = fileData && fileData.fileNumber ? String(fileData.fileNumber).trim() : '';
                if (!n) return;
                if (self.navigate) {
                    window.location.href = self.navigate + (self.navigate.indexOf('?') < 0 ? '?' : '&') + 'file_number=' + encodeURIComponent(n);
                    return;
                }
                self.resolve({ file_number: n, tab: fileData.tab || '' });
            }
        });
    };

    /** Ask the server about a file. params: file_number+tab, or file_indexing_id / receipt / card. */
    Picker.prototype.resolve = function (params) {
        var self = this;
        this.query = { file_number: params.file_number || '', tab: params.tab || '' };
        if (params.file_number) this.number.value = params.file_number;

        var q = new URLSearchParams();
        Object.keys(params).forEach(function (k) { if (params[k] !== '' && params[k] != null) q.set(k, params[k]); });
        q.set('scope', this.scope);
        if (this.purpose) q.set('purpose', this.purpose);

        this.status('Looking the file up…', false);
        fetch(this.url + '?' + q.toString(), {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (r) { if (!r.ok) throw new Error(String(r.status)); return r.json(); })
            .then(function (p) { self.apply(p, false); })
            .catch(function () { self.status('The file could not be looked up. Check your connection and try again.', true); });
    };

    Picker.prototype.status = function (text, isError) {
        if (!text) { this.statusEl.style.display = 'none'; this.statusEl.textContent = ''; return; }
        this.statusEl.textContent = text;
        this.statusEl.classList.toggle('is-error', !!isError);
        this.statusEl.style.display = '';
    };

    Picker.prototype.fields = function (name) {
        var root = this.root;
        // Never a field the page keeps locked for good (the builder's composed
        // location preview): it is neither filled nor released here.
        return Array.prototype.filter.call(
            this.form.querySelectorAll('[name="' + name + '"]'),
            function (el) {
                return el.type !== 'hidden' && !root.contains(el)
                    && el.getAttribute('data-locked') !== 'always' && !el.classList.contains('ab-preview');
            }
        );
    };

    Picker.prototype.setValue = function (el, value) {
        var $ = $j();
        value = value == null ? '' : String(value);
        if (el.tagName === 'SELECT') {
            var has = Array.prototype.some.call(el.options, function (o) { return o.value === value; });
            if (value !== '' && !has) el.add(new Option(value === 'Other' ? 'Other (specify)' : value, value, true, true));
            el.value = value;
            if ($ && $(el).data('select2')) $(el).trigger('change'); else el.dispatchEvent(new Event('change', { bubbles: true }));
        } else {
            el.value = value;
            el.dispatchEvent(new Event('input', { bubbles: true }));
        }
    };

    Picker.prototype.lock = function (el, on) {
        var $ = $j();
        el.disabled = on;
        el.classList.toggle('cad-locked', on);
        var group = el.closest('.form-group');
        if (group) group.classList.toggle('cad-fp-filled', on);
        if ($ && $(el).data('select2')) $(el).prop('disabled', on);
    };

    /** Undo the last file's fills and locks, so a new pick starts clean. */
    Picker.prototype.release = function () {
        var self = this;
        this.locked.forEach(function (el) {
            self.lock(el, false);
            self.setValue(el, el.name === 'prop_state' ? 'Kano' : '');
        });
        this.locked = [];
        this.form.querySelectorAll('[data-fp-value]').forEach(function (el) {
            if ('value' in el && el.tagName !== 'DIV' && el.tagName !== 'SPAN') el.value = ''; else el.textContent = '';
        });
        this.root.querySelectorAll('[data-fp-hidden]').forEach(function (h) { h.value = ''; });
    };

    Picker.prototype.clear = function () {
        this.release();
        this.payload = null;
        this.number.value = '';
        this.card.style.display = 'none';
        this.choices.style.display = 'none';
        if (this.clearBtn) this.clearBtn.style.display = 'none';
        this.status('', false);
        this.emit(null);
    };

    Picker.prototype.emit = function (payload) {
        this.form.dispatchEvent(new CustomEvent('cadastral:file-picked', { detail: payload, bubbles: true }));
    };

    /**
     * Fill the form from a resolve payload. 'initial' is the server-rendered
     * re-display (a failed save, an edit form): the clerk's own entries in the
     * open fields are left as the server rendered them.
     */
    Picker.prototype.apply = function (p, initial) {
        var self = this;
        if (!initial) this.release();
        // Listeners can tell the server-rendered re-display from a fresh pick.
        p.initial = !!initial;
        this.payload = p;
        this.status('', false);
        this.choices.style.display = 'none';
        if (this.clearBtn) this.clearBtn.style.display = this.fixed ? 'none' : '';

        this.number.value = (p.file && p.file.file_number) || (p.query && p.query.file_number) || this.number.value;

        if (p.status === 'ambiguous') {
            this.card.style.display = 'none';
            this.renderChoices(p);
            this.emit(p);
            return;
        }
        if (p.status === 'not_found') {
            this.card.style.display = 'none';
            this.status(p.message || 'That file was not found.', true);
            this.emit(p);
            return;
        }

        var usable = p.status === 'ok' || this.fixed;

        if (usable) {
            this.root.querySelectorAll('[data-fp-hidden]').forEach(function (h) {
                var v = p.hidden && p.hidden[h.name];
                // On a re-render the posted id is already there; keep it.
                if (!initial || !h.value) h.value = v == null ? '' : v;
            });

            var values = p.values || {};
            var names = VALUE_FIELDS.concat(Object.keys(this.map).filter(function (k) { return VALUE_FIELDS.indexOf(k) < 0; }));
            var lockedNames = {};

            names.forEach(function (key) {
                var name  = self.map[key] || key;
                var value = values[key];
                var paired = PAIRED[key];
                var has   = paired ? !!lockedNames[self.map[paired] || paired] : (value != null && String(value).trim() !== '');
                if (!has) return;
                self.fields(name).forEach(function (el) {
                    self.setValue(el, value);
                    self.lock(el, true);
                    self.locked.push(el);
                });
                lockedNames[name] = true;
            });

            var show = {
                file_number: p.file && p.file.file_number, owner: p.file && p.file.owner, type: p.file && p.file.type,
                land_use: p.file && p.file.land_use, source: p.file && p.file.source, registry_label: p.file && p.file.registry_label,
                location: values.location, file_title: values.file_title, plot_no: values.plot_no
            };
            this.form.querySelectorAll('[data-fp-value]').forEach(function (el) {
                var v = show[el.getAttribute('data-fp-value')];
                v = v == null || v === '' ? '' : v;
                if (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA' || el.tagName === 'SELECT') el.value = v; else el.textContent = v || '—';
            });
        }

        this.renderCard(p, usable);
        this.emit(p);
    };

    Picker.prototype.renderChoices = function (p) {
        var rows = (p.candidates || []).map(function (c) {
            var meta = [c.registry, c.owner, c.plot ? 'Plot ' + c.plot : '', c.location].filter(Boolean).join(' · ');
            var why  = 'Matched on its ' + (c.matched_on || []).join(', ');
            return '<div class="cad-fp-choice"><div><strong>' + esc(c.file_number) + '</strong>' +
                (c.tab_match ? ' <span class="status-badge active"><span class="dot"></span>In the registry you searched</span>' : '') +
                '<small>' + esc(meta) + '</small><small>' + esc(why) + '</small></div>' +
                '<button type="button" class="btn btn-outline btn-sm" data-fp-choose="' + esc(c.id) + '">Use this file</button></div>';
        }).join('');
        this.choices.innerHTML =
            '<div style="display:flex;gap:6px;align-items:center;font-weight:600;color:#92400e;margin-bottom:4px;">' +
            '<i data-lucide="copy" style="width:16px;height:16px;"></i>' + esc(p.message) + '</div>' + rows;
        this.choices.style.display = '';
        icons();
    };

    Picker.prototype.renderCard = function (p, usable) {
        var f = p.file || {};
        var v = p.values || {};
        var fact = function (label, value) {
            return '<div><span>' + esc(label) + '</span>' + esc(value || '—') + '</div>';
        };
        var flags = (p.flags || []).map(function (fl) {
            return '<span class="status-badge ' + (LEVEL[fl[0]] || 'pending') + '"><span class="dot"></span>' + esc(fl[1]) + '</span>';
        }).join('');
        var refused = !usable || (p.status !== 'ok' && !this.fixed);
        var others = (f.other_numbers || []).length ? fact('Also numbered', f.other_numbers.join(', ')) : '';

        this.card.className = 'cad-fp-card' + (refused ? ' is-refused' : '');
        this.card.innerHTML =
            '<div class="cad-fp-card-head"><i data-lucide="' + (refused ? 'folder-x' : 'folder-check') + '" style="width:18px;height:18px;color:' + (refused ? '#dc2626' : '#16a34a') + ';"></i>' +
            '<strong>' + esc(f.file_number) + '</strong><span class="cad-fp-type">' + esc(f.type || '') + '</span></div>' +
            '<div class="cad-fp-facts">' +
                fact('Title / owner', f.owner) +
                fact('Registry', [f.registry_label, f.source && f.source !== f.registry_label ? '(' + f.source + ')' : ''].filter(Boolean).join(' ')) +
                fact('Land use', f.land_use) +
                fact('Class', f.file_class === 'conversion' ? 'Conversion' : (f.file_class ? 'Direct' : '')) +
                fact('Plot', v.plot_no) +
                fact('Location', v.location) +
                others +
            '</div>' +
            (flags ? '<div class="cad-fp-flags">' + flags + '</div>' : '') +
            (refused && p.message ? '<div class="cad-fp-refusal"><i data-lucide="octagon-alert" style="width:16px;height:16px;flex:0 0 16px;"></i><span>' + esc(p.message) + '</span></div>' : '');
        this.card.style.display = '';
        icons();
    };

    window.CadastralFilePicker = {
        /** The picker inside (or identified by) el. */
        get: function (el) {
            if (typeof el === 'string') el = document.querySelector(el);
            if (!el) return null;
            var root = el.matches && el.matches('[data-fp]') ? el : el.querySelector('[data-fp]');
            return root ? root._picker || null : null;
        },
        /** Load a known file into a picker, e.g. a table row's "Change" button: {card: 12}. */
        load: function (el, params) {
            var p = this.get(el);
            if (p) p.resolve(params || {});
            return p;
        }
    };

    document.addEventListener('DOMContentLoaded', function () {
        setTimeout(function () {
            document.querySelectorAll('[data-fp]').forEach(function (root) { if (!root._picker) new Picker(root); });
            icons();
        }, 0);
    });
})();
</script>
