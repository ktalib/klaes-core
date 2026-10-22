/*
 * KLAES REV-M bill flow on a bill type's page (resources/views/revenue/bills/index.blade.php).
 *
 *   Generate bill  pick the file (GlobalFileNoModal) → server-rendered preview
 *                  (file details, existing bills, items) → POST → reload
 *   Generate (one)  a bill a module sent over: confirm → POST → its invoice opens
 *   View           server-rendered bill detail (Bill · Payments · Application);
 *                  its payment form posts here and Paystack opens in this tab
 */
(function () {
    function csrf() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function icons() {
        if (window.lucide && typeof window.lucide.createIcons === 'function') {
            window.lucide.createIcons();
        }
    }

    function notify(ok, message) {
        if (window.Swal) {
            return window.Swal.fire({ icon: ok ? 'success' : 'error', title: ok ? 'Done' : 'Not saved', text: message, confirmButtonColor: '#047857' });
        }
        window.alert(message);
        return Promise.resolve();
    }

    /** POST a form as JSON-expecting fetch; Laravel validation errors come back as 422. */
    function post(url, form) {
        return fetch(url, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: new FormData(form),
            credentials: 'same-origin'
        }).then(function (res) {
            return res.json().catch(function () { return {}; }).then(function (json) {
                if (res.ok && json.success !== false) {
                    return json;
                }
                var message = json.message || 'Something went wrong.';
                if (json.errors) {
                    message = Object.keys(json.errors).map(function (k) { return json.errors[k][0]; }).join('\n');
                }
                throw new Error(message);
            });
        });
    }

    window.revBills = function (config) {
        return {
            genOpen: false,
            viewOpen: false,
            loading: false,
            busy: false,
            ready: false,
            fileNumber: '',
            kind: config.kind || '',

            /** After a generate the page reloads with ?open={ref}: open that bill's card. */
            init: function () {
                var params = new URLSearchParams(window.location.search);
                var ref = params.get('open');
                if (!ref) { return; }
                params.delete('open');
                var qs = params.toString();
                window.history.replaceState(null, '', window.location.pathname + (qs ? '?' + qs : '') + window.location.hash);
                var self = this;
                this.$nextTick(function () { self.openView(ref); }); // refs exist once the children are set up
            },

            /** Reload the list (new status) and open the generated bill's card. */
            reloadAndOpen: function (ref) {
                if (!ref) { window.location.reload(); return; }
                var params = new URLSearchParams(window.location.search);
                params.set('open', ref);
                window.location.href = window.location.pathname + '?' + params.toString();
            },

            openGenerate: function (file) {
                this.genOpen = true;
                this.ready = false;
                this.fileNumber = file || '';
                if (this.fileNumber) {
                    this.loadPreview();
                } else {
                    this.$refs.genBody.innerHTML = '<div class="text-center text-gray-400 py-10">Select the file number to start.</div>';
                    this.pickFile();
                }
            },

            pickFile: function () {
                var self = this;
                if (!window.GlobalFileNoModal || typeof window.GlobalFileNoModal.open !== 'function') {
                    notify(false, 'The file number selector is not available on this page.');
                    return;
                }
                window.GlobalFileNoModal.open({
                    callback: function (data) {
                        self.fileNumber = (data && data.fileNumber) || '';
                        if (self.fileNumber) {
                            self.loadPreview();
                        }
                    }
                });
            },

            loadPreview: function () {
                var self = this;
                var params = new URLSearchParams({ file_number: this.fileNumber, kind: this.kind || '' });
                this.loading = true;
                this.ready = false;
                fetch(config.lookupUrl + '?' + params.toString(), { headers: { 'Accept': 'text/html' }, credentials: 'same-origin' })
                    .then(function (res) {
                        if (!res.ok) { throw new Error('Could not load the file (' + res.status + ').'); }
                        return res.text();
                    })
                    .then(function (html) {
                        self.$refs.genBody.innerHTML = html;
                        self.ready = !!self.$refs.genBody.querySelector('[data-rev-ready]');
                        icons();
                    })
                    .catch(function (e) { notify(false, e.message); })
                    .finally(function () { self.loading = false; });
            },

            submitGenerate: function () {
                var self = this;
                if (!this.ready || this.busy) { return; }
                this.busy = true;
                post(config.storeUrl, this.$refs.genForm)
                    .then(function (json) {
                        self.genOpen = false;
                        return notify(true, json.message || 'Bill generated.').then(function () { self.reloadAndOpen(json.ref); });
                    })
                    .catch(function (e) { notify(false, e.message); })
                    .finally(function () { self.busy = false; });
            },

            /**
             * Generate a bill a module sent over; only then does its invoice open. The
             * payer's phone (for the SMS notices) is asked for, filled from the file's
             * records when one is found.
             */
            generateBill: function (ref) {
                var self = this;
                if (!config.generateUrl || this.busy) { return; }
                var url = config.generateUrl.replace('__REF__', encodeURIComponent(ref));
                var contactUrl = url.replace(/\/generate$/, '/contact');
                var contact = fetch(contactUrl, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                    .then(function (res) { return res.ok ? res.json() : {}; })
                    .catch(function () { return {}; });
                var ask = contact.then(function (c) {
                    if (!window.Swal) {
                        return window.confirm('Generate ' + ref + '?') ? { phone: c.phone || '' } : null;
                    }
                    var esc = function (s) { return String(s || '').replace(/[&<>"']/g, function (ch) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch]; }); };
                    return window.Swal.fire({
                        icon: 'question',
                        title: 'Generate ' + ref + '?',
                        html: '<p class="text-sm text-gray-600 mb-3">The bill keeps the items and total from its module. Its invoice opens once generated.</p>'
                            + '<label class="block text-left text-xs font-semibold text-gray-600">Payer phone (SMS notices go here)</label>'
                            + '<input id="rev-gen-phone" type="tel" maxlength="30" class="swal2-input !m-0 !w-full" value="' + esc(c.phone) + '" placeholder="080…">'
                            + (c.source ? '<p class="text-left text-xs text-gray-500 mt-1">Found in ' + esc(c.source) + '. Check it with the payer.</p>' : '<p class="text-left text-xs text-gray-500 mt-1">No phone found in the file\'s records.</p>'),
                        showCancelButton: true,
                        confirmButtonText: 'Generate',
                        confirmButtonColor: '#047857',
                        preConfirm: function () { return { phone: document.getElementById('rev-gen-phone').value.trim() }; }
                    }).then(function (r) { return r.isConfirmed ? r.value : null; });
                });
                ask.then(function (answer) {
                    if (!answer) { return; }
                    self.busy = true;
                    var form = document.createElement('form');
                    var phone = document.createElement('input');
                    phone.name = 'payer_phone';
                    phone.value = answer.phone || '';
                    form.appendChild(phone);
                    post(url, form)
                        .then(function (json) {
                            self.genOpen = false;
                            return notify(true, json.message || 'Bill generated.').then(function () { self.reloadAndOpen(json.ref || ref); });
                        })
                        .catch(function (e) { notify(false, e.message); })
                        .finally(function () { self.busy = false; });
                });
            },

            openView: function (ref, tab) {
                var self = this;
                this.viewRef = ref;
                this.viewOpen = true;
                this.$refs.viewBody.innerHTML = '<div class="text-center text-sm text-gray-500 py-10">Loading…</div>';
                var url = config.showUrl.replace('__REF__', encodeURIComponent(ref)) + (tab ? '?tab=' + encodeURIComponent(tab) : '');
                fetch(url, { headers: { 'Accept': 'text/html' }, credentials: 'same-origin' })
                    .then(function (res) {
                        if (!res.ok) { throw new Error('Could not load the bill (' + res.status + ').'); }
                        return res.text();
                    })
                    .then(function (html) { self.$refs.viewBody.innerHTML = html; icons(); })
                    .catch(function (e) { self.viewOpen = false; notify(false, e.message); });
            },

            /** A form inside the View modal (record payment). */
            submitAjax: function (form) {
                var self = this;
                if (!form || !form.matches('form[data-rev-ajax]')) { return; }
                var button = form.querySelector('button[type="submit"]');
                if (button) { button.disabled = true; }
                post(form.getAttribute('action'), form)
                    .then(function (json) {
                        if (json.redirect) {
                            window.location.href = json.redirect; // Paystack checkout
                            return;
                        }
                        var ref = self.viewRef;
                        return notify(true, json.message || 'Saved.').then(function () { self.reloadAndOpen(ref); });
                    })
                    .catch(function (e) { notify(false, e.message); })
                    .finally(function () { if (button) { button.disabled = false; } });
            }
        };
    };

    /**
     * On-the-fly bill (partials/_on_the_fly_modal): the bill type's Revenue Code (one per
     * bill type) picks the Bill Type; the file comes from the global selector (details
     * backfilled, phone included); the items are entered: item, quantity, rate. For a
     * unit-rate type (Ground Rent) the quantity is the plot size.
     */
    window.revOnTheFly = function (config) {
        // qty '' (a unit-rate type with no plot size on record) leaves the box empty for the officer to fill.
        var blank = function (qty) { return { name: '', quantity: qty === undefined ? 1 : qty, rate: '' }; };
        return {
            open: false,
            busy: false,
            loadingFile: false,
            type: '',
            fixedType: config.fixedType || '',
            file: null,
            payer: { name: '', phone: '', email: '', address: '' },
            phoneSource: '',
            description: '',
            dueDate: '',
            periodStart: '',
            lines: [blank()],
            codes: config.codes || {},
            cycles: config.cycles || {},
            codeEntry: '',
            codeMsg: '',
            codeOk: false,

            /** Open the form; a type page passes its type, a row menu also its file. */
            start: function (type, file) {
                this.open = true;
                this.type = this.fixedType || type || '';
                this.file = null;
                this.payer = { name: '', phone: '', email: '', address: '' };
                this.phoneSource = '';
                this.description = '';
                this.dueDate = '';
                this.periodStart = '';
                this.codeMsg = '';
                this.typeChanged();
                if (file) { this.loadFile(file); }
            },
            isUnitRate: function () { return (config.unitRate || []).indexOf(this.type) !== -1; },
            cycleLabel: function () { return this.cycles[this.type] || ''; },
            plotSize: function () {
                var n = this.file ? parseFloat(String(this.file.plot_size || '').replace(/[^0-9.]/g, '')) : NaN;
                return isFinite(n) && n > 0 ? n : '';
            },
            /** The Bill Type shows its Revenue Code; a new type starts the items again. */
            typeChanged: function () {
                this.codeEntry = this.codes[this.type] || '';
                this.lines = [blank(this.isUnitRate() ? this.plotSize() : 1)];
            },
            /** The bill type's name as the Bill Type list shows it. */
            typeName: function (code) {
                var opt = this.$root ? this.$root.querySelector('select option[value="' + code + '"]') : null;
                return opt ? opt.textContent.replace(/^\d+\s·\s/, '').trim() : code;
            },

            /**
             * Revenue Code entered: select its bill type. Initial Bill's code is shared by
             * Primary / PuA / SuA, so the current one is kept if it is one of them,
             * otherwise the officer is asked which.
             */
            findCode: function () {
                var code = String(this.codeEntry || '').replace(/\s+/g, '');
                var self = this;
                this.codeMsg = '';
                if (!code || this.fixedType) { return; }
                var types = Object.keys(this.codes).filter(function (t) { return String(self.codes[t]) === code; });
                if (!types.length) {
                    this.codeOk = false;
                    this.codeMsg = 'No bill type has Revenue Code ' + code + '.';
                    return;
                }
                var pick = types.indexOf(this.type) !== -1 ? this.type : null;
                if (!pick && types.length > 1) {
                    var list = types.map(function (t, i) { return (i + 1) + '. ' + self.typeName(t); }).join('\n');
                    pick = types[parseInt(window.prompt('Revenue Code ' + code + ' is used by:\n' + list + '\nWhich one is this bill?', '1'), 10) - 1];
                    if (!pick) { return; }
                }
                pick = pick || types[0];
                if (pick !== this.type) {
                    var used = this.lines.some(function (l) { return String(l.name || '').trim() !== '' || l.rate !== ''; });
                    if (used && this.type && !window.confirm('Revenue Code ' + code + ' is ' + this.typeName(pick) + '. Switch the bill type? The items entered so far are cleared.')) {
                        this.codeEntry = this.codes[this.type] || '';
                        return;
                    }
                    this.type = pick;
                    this.typeChanged();
                }
                this.codeEntry = code;
                this.codeOk = true;
                this.codeMsg = code + ' → ' + this.typeName(pick);
            },

            pickFile: function () {
                var self = this;
                if (!window.GlobalFileNoModal || typeof window.GlobalFileNoModal.open !== 'function') {
                    notify(false, 'The file number selector is not available on this page.');
                    return;
                }
                window.GlobalFileNoModal.open({
                    callback: function (data) {
                        var no = (data && data.fileNumber) || '';
                        if (no) { self.loadFile(no); }
                    }
                });
            },
            /** A new file replaces every detail of the previous one (development standard 5). */
            loadFile: function (no) {
                var self = this;
                this.loadingFile = true;
                fetch(config.fileUrl + '?file_number=' + encodeURIComponent(no), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                    .then(function (res) { if (!res.ok) { throw new Error('Could not load the file (' + res.status + ').'); } return res.json(); })
                    .then(function (f) {
                        self.file = f;
                        self.payer = { name: f.payer_name || '', phone: f.payer_phone || '', email: '', address: '' };
                        self.phoneSource = f.phone_source || '';
                        // Ground rent: rate per m² x the plot size of THIS file.
                        if (self.isUnitRate()) {
                            var size = self.plotSize();
                            self.lines.forEach(function (l) { l.quantity = size; });
                        }
                    })
                    .catch(function (e) { notify(false, e.message); })
                    .finally(function () { self.loadingFile = false; });
            },
            addLine: function () { this.lines.push(blank(this.isUnitRate() ? this.plotSize() : 1)); },
            removeLine: function (i) { this.lines.splice(i, 1); if (!this.lines.length) { this.addLine(); } },
            lineAmount: function (line) {
                var q = parseFloat(line.quantity), r = parseFloat(line.rate);
                return (isFinite(q) && isFinite(r)) ? Math.round(q * r * 100) / 100 : 0;
            },
            total: function () {
                var self = this;
                return this.lines.reduce(function (s, l) { return s + self.lineAmount(l); }, 0);
            },
            money: function (v) { return '₦' + Number(v || 0).toLocaleString('en-NG', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
            submit: function () {
                var self = this;
                if (this.busy) { return; }
                if (!this.type) { notify(false, 'Enter the Revenue Code or choose the bill type.'); return; }
                if (!this.file) { notify(false, 'Select the file number.'); return; }
                var fd = new FormData();
                fd.append('bill_type', this.type);
                fd.append('file_number', this.file.file_number);
                fd.append('payer_name', this.payer.name);
                fd.append('payer_phone', this.payer.phone);
                fd.append('payer_email', this.payer.email);
                fd.append('payer_address', this.payer.address);
                fd.append('description', this.description);
                if (this.dueDate) { fd.append('due_date', this.dueDate); }
                if (this.periodStart) { fd.append('period_start', this.periodStart); }
                this.lines.forEach(function (l, i) {
                    fd.append('lines[' + i + '][name]', l.name || '');
                    fd.append('lines[' + i + '][quantity]', l.quantity);
                    fd.append('lines[' + i + '][rate]', l.rate);
                });
                this.busy = true;
                fetch(config.storeUrl, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: fd,
                    credentials: 'same-origin'
                }).then(function (res) {
                    return res.json().catch(function () { return {}; }).then(function (json) {
                        if (!res.ok || json.success === false) {
                            var m = json.message || 'Something went wrong.';
                            if (json.errors) { m = Object.keys(json.errors).map(function (k) { return json.errors[k][0]; }).join('\n'); }
                            throw new Error(m);
                        }
                        return json;
                    });
                }).then(function (json) {
                    self.open = false;
                    return notify(true, json.message || 'Bill generated.').then(function () {
                        var params = new URLSearchParams(window.location.search);
                        if (json.ref) { params.set('open', json.ref); }
                        window.location.href = window.location.pathname + '?' + params.toString();
                    });
                }).catch(function (e) { notify(false, e.message); })
                  .finally(function () { self.busy = false; });
            }
        };
    };
})();
