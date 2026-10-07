/**
 * File Movement (Department) — Alpine component for
 * resources/views/secretariat_file_log/index.blade.php.
 *
 * One scan station in two modes. Receive: scan any KLAES QR (resolved
 * server-side), see the file profile, receive. Send: scan a held file and
 * track it to the next office. A barcode/QR scanner is detected by its typing
 * speed, so a scan looks itself up with no button press — whether or not the
 * scan box has focus. The secretary's office is chosen once and remembered per
 * user in localStorage (there is no user -> office link in the database).
 */
window.secretariatFileLog = function (config) {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const userKey = config.userId || 'anon';
    const officeKey = 'secretariatFileLog.office.' + userKey;
    const autoKey = 'secretariatFileLog.autoReceive.' + userKey;

    // A scanner "types" a whole code in a few milliseconds per character; a
    // person cannot. These thresholds separate the two.
    const SCAN_MAX_GAP_MS = 45;
    const SCAN_MIN_LENGTH = 4;
    const SCAN_IDLE_MS = 160;

    // <i data-lucide="log-in" class="w-4 h-4"> -> inline SVG, for this root only.
    const renderLucide = (root) => {
        if (!window.lucide || !lucide.icons || !lucide.createElement) return;
        root.querySelectorAll('i[data-lucide]').forEach((el) => {
            const name = el.getAttribute('data-lucide');
            const key = String(name || '').replace(/(^|-)([a-z0-9])/g, (m, d, c) => c.toUpperCase());
            const node = lucide.icons[key];
            if (!node) return;
            const svg = lucide.createElement(node);
            svg.setAttribute('class', ['lucide', 'lucide-' + name, el.getAttribute('class') || ''].join(' ').trim());
            el.replaceWith(svg);
        });
    };

    // Many records are stored in capitals ("SAGIR SANI IBRAHIM", "PIECE OF LAND").
    // Title-case an all-caps value for display; leave codes alone (anything with a
    // digit, slash or hyphen — file numbers, TP numbers, tracking IDs).
    const SMALL_WORDS = ['of', 'and', 'the', 'in', 'on', 'at', 'to', 'for', 'by', 'a', 'an'];
    const nice = (value) => {
        if (value === null || value === undefined) return value;
        const text = String(value).trim();
        if (!/[A-Z]/.test(text) || text !== text.toUpperCase()) return text;
        return text.split(/(\s+)/).map((word, i) => {
            if (/^\s+$/.test(word) || /[\d\/\-]/.test(word)) return word;
            if (/^\(?[A-Z]{1,4}\)?$/.test(word) && ['LGA', 'GRA', 'FCT', 'TP', 'OSS', 'II', 'III', 'IV'].includes(word.replace(/[()]/g, ''))) return word;
            const lower = word.toLowerCase();
            if (i > 0 && SMALL_WORDS.includes(lower)) return lower;
            return lower.replace(/(^|[(.'])([a-z])/g, (m, p1, c) => p1 + c.toUpperCase());
        }).join('');
    };
    // A stored "nothing": 0, 0.00, '-', 'N/A'.
    const blank = (value) => value === null || value === undefined || /^\s*(0+(\.0+)?|-|n\/a|null)?\s*$/i.test(String(value));

    const store = {
        get(key) { try { return localStorage.getItem(key); } catch (e) { return null; } },
        set(key, value) { try { localStorage.setItem(key, value); } catch (e) { /* private mode */ } },
    };

    const blankManual = () => ({
        open: false, entry_type: 'file', file_number: '', file_title: '',
        sender: '', reference: '', from_office: '', notes: '',
    });
    const blankSend = () => ({ row: null, to_office: '', purpose: '', notes: '' });
    const blankScan = () => ({ q: '', loading: false, message: '', matches: [] });

    return {
        offices: config.offices || [],
        urls: config.urls,
        nice,
        office: '',
        officeLocked: false,
        mode: 'in',
        autoReceive: false,
        busy: false,
        tab: 'pending',
        days: 7,
        filter: '',
        listsLoading: false,
        lists: { pending: [], held: [], sent: [] },
        scan: blankScan(),
        scanFocused: false,
        send: blankSend(),
        manual: blankManual(),
        profile: { open: false, loading: false, tab: 'history', data: null, match: null },
        lightbox: { open: false, items: [], index: 0 },
        keyTimes: [],
        autoTimer: null,
        globalBuffer: '',
        globalTimes: [],
        entryTypes: [
            { value: 'file', icon: 'folder-check', label: 'Indexed file', hint: 'No tracking sheet or QR, but the file is indexed.' },
            { value: 'unindexed', icon: 'folder-x', label: 'Not indexed', hint: 'A file number that has not been indexed yet.' },
            { value: 'non_file', icon: 'mail', label: 'Not a regular file', hint: 'A letter, memo or other document with no file number.' },
        ],
        registerTabs: [
            { key: 'pending', label: 'Pending receipt', badge: 'bg-yellow-100 text-yellow-800' },
            { key: 'held', label: 'Held at my office', badge: 'bg-green-100 text-green-800' },
            { key: 'sent', label: 'Sent', badge: 'bg-gray-100 text-gray-700' },
        ],

        init() {
            const saved = store.get(officeKey) || '';
            this.autoReceive = store.get(autoKey) === '1';
            if (saved && this.offices.some(o => o.office_code === saved)) {
                this.office = saved;
                this.officeLocked = true;
                this.loadLists();
                this.$nextTick(() => this.focusScan());
            }

            // Render icons Alpine adds later (lists, profile, tabs). Not via
            // lucide.createIcons(): this Lucide build keeps data-lucide on the SVGs
            // it makes, so every call rebuilds every icon on the page — and those
            // mutations would re-trigger this observer. Only this page's
            // unrendered <i> tags are touched here.
            let pending = false;
            const root = this.$el;
            const render = () => { pending = false; renderLucide(root); };
            new MutationObserver(() => {
                if (pending || !root.querySelector('i[data-lucide]')) return;
                pending = true;
                requestAnimationFrame(render);
            }).observe(root, { childList: true, subtree: true });
            renderLucide(root);

            document.addEventListener('keydown', (e) => this.onGlobalKeydown(e));
        },

        // ── Office ────────────────────────────────────────────────────────
        officeGroups() {
            const groups = [];
            const byDept = {};
            this.offices.forEach(o => {
                const d = o.department || 'Other';
                if (!byDept[d]) { byDept[d] = { department: d, items: [] }; groups.push(byDept[d]); }
                byDept[d].items.push(o);
            });
            return groups;
        },

        officeName(code) {
            const o = this.offices.find(i => i.office_code === code);
            return o ? o.office_name.trim() + ' (' + o.office_code + ')' : code;
        },

        lockOffice() {
            if (!this.office) return;
            store.set(officeKey, this.office);
            this.officeLocked = true;
            this.closeProfile();
            this.loadLists();
            this.$nextTick(() => this.focusScan());
        },

        saveAutoReceive() {
            store.set(autoKey, this.autoReceive ? '1' : '0');
            this.focusScan();
        },

        setMode(mode) {
            this.mode = mode;
            this.scan = blankScan();
            this.send = blankSend();
            this.focusScan();
        },

        focusScan() {
            if (this.$refs.scanBox && !this.manual.open && !this.send.row) this.$refs.scanBox.focus();
        },

        // ── HTTP ──────────────────────────────────────────────────────────
        async request(url, options = {}) {
            const res = await fetch(url, {
                credentials: 'same-origin',
                ...options,
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                    ...(options.body ? { 'Content-Type': 'application/json' } : {}),
                },
            });
            let data = {};
            try { data = await res.json(); } catch (e) { data = {}; }
            if (!res.ok || data.success === false) {
                const errors = data.errors ? Object.values(data.errors).flat().join(' ') : '';
                throw new Error(data.message || errors || ('Request failed (' + res.status + ')'));
            }
            return data;
        },

        notify(icon, text) {
            if (window.Swal) {
                Swal.fire({ icon, text, toast: true, position: 'top-end', timer: icon === 'error' ? 6000 : 3000, showConfirmButton: false });
            } else {
                alert(text);
            }
        },

        // ── Dashboard / register ──────────────────────────────────────────
        async loadLists() {
            if (!this.office) return;
            this.listsLoading = true;
            try {
                const data = await this.request(this.urls.lists + '?' + new URLSearchParams({ office: this.office, days: this.days }));
                this.lists = { pending: data.pending || [], held: data.held || [], sent: data.sent || [] };
            } catch (e) {
                this.notify('error', e.message);
            } finally {
                this.listsLoading = false;
            }
        },

        isToday(value) {
            if (!value) return false;
            const d = new Date(String(value).replace(' ', 'T'));
            return !isNaN(d.getTime()) && d.toDateString() === new Date().toDateString();
        },

        cards() {
            const inTransit = this.lists.sent.filter(r => r.state === 'In transit').length;
            const periods = { 1: 'today', 7: 'in the last 7 days', 30: 'in the last 30 days', 90: 'in the last 90 days' };
            return [
                { key: 'pending', tab: 'pending', label: 'Pending Receipt', value: this.lists.pending.length, icon: 'inbox', text: 'text-yellow-600', bg: 'bg-yellow-100', hint: 'Sent to this office, not yet received' },
                { key: 'held', tab: 'held', label: 'Held Here', value: this.lists.held.length, icon: 'folder-open', text: 'text-green-600', bg: 'bg-green-100', hint: 'Received and not yet sent on' },
                { key: 'today', tab: 'held', label: 'Received Today', value: this.lists.held.filter(r => this.isToday(r.since)).length, icon: 'calendar-check', text: 'text-blue-600', bg: 'bg-blue-100', hint: 'Still held, received today' },
                { key: 'transit', tab: 'sent', label: 'In Transit', value: inTransit, icon: 'truck', text: 'text-orange-600', bg: 'bg-orange-100', hint: 'Sent out, awaiting receipt' },
                { key: 'sent', tab: 'sent', label: 'Sent', value: this.lists.sent.length, icon: 'send', text: 'text-indigo-600', bg: 'bg-indigo-100', hint: 'Sent ' + (periods[this.days] || '') },
            ];
        },

        visibleRows() {
            const rows = this.lists[this.tab] || [];
            const f = this.filter.trim().toLowerCase();
            if (!f) return rows;
            return rows.filter(r => [r.file_number, r.file_title, r.tracking_id, r.from, r.to, r.purpose, r.reference]
                .some(v => v && String(v).toLowerCase().includes(f)));
        },

        fmt(value) {
            if (!value) return '—';
            const d = new Date(String(value).replace(' ', 'T'));
            if (isNaN(d.getTime())) return value;
            return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) + ' ' +
                d.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
        },

        // ── Scanner detection ─────────────────────────────────────────────
        isScannerBurst(times) {
            if (times.length < SCAN_MIN_LENGTH - 1) return false;
            const avg = times.reduce((a, b) => a + b, 0) / times.length;
            return avg <= SCAN_MAX_GAP_MS;
        },

        onScanKeydown(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                clearTimeout(this.autoTimer);
                this.keyTimes = [];
                this.resolve();
                return;
            }
            if (e.key.length !== 1) return;
            const now = performance.now();
            if (this.lastKeyAt && now - this.lastKeyAt <= 120) {
                this.keyTimes.push(now - this.lastKeyAt);
            } else {
                this.keyTimes = [];
            }
            this.lastKeyAt = now;
        },

        // Scanners that send no Enter suffix: look up once the burst goes quiet.
        onScanInput() {
            clearTimeout(this.autoTimer);
            this.autoTimer = setTimeout(() => {
                if (this.scan.q.trim().length >= SCAN_MIN_LENGTH && this.isScannerBurst(this.keyTimes)) {
                    this.keyTimes = [];
                    this.resolve();
                }
            }, SCAN_IDLE_MS);
        },

        onScanPaste() {
            clearTimeout(this.autoTimer);
            setTimeout(() => this.resolve(), 30);
        },

        // A scan while focus is elsewhere on the page still lands in the scan box.
        onGlobalKeydown(e) {
            if (!this.officeLocked || this.lightbox.open || this.manual.open) return;
            const t = e.target;
            const tag = (t && t.tagName) || '';
            if (['INPUT', 'TEXTAREA', 'SELECT'].includes(tag) || (t && t.isContentEditable)) return;
            if (document.getElementById('global-fileno-modal')?.classList.contains('flex')) return;

            const now = performance.now();
            if (e.key === 'Enter') {
                const buffer = this.globalBuffer;
                const burst = this.isScannerBurst(this.globalTimes);
                this.globalBuffer = '';
                this.globalTimes = [];
                if (buffer.length >= SCAN_MIN_LENGTH && burst) {
                    e.preventDefault();
                    this.scan.q = buffer;
                    this.resolve();
                    this.focusScan();
                }
                return;
            }
            if (e.key.length !== 1 || e.ctrlKey || e.metaKey || e.altKey) return;
            if (this.globalLastAt && now - this.globalLastAt <= 120) {
                this.globalTimes.push(now - this.globalLastAt);
            } else {
                this.globalBuffer = '';
                this.globalTimes = [];
            }
            this.globalLastAt = now;
            this.globalBuffer += e.key;
        },

        // ── Scan → profile ────────────────────────────────────────────────
        async resolve() {
            const q = this.scan.q.trim();
            if (!q || this.scan.loading) return;
            this.scan.loading = true;
            this.scan.message = '';
            this.scan.matches = [];
            this.send = blankSend();
            try {
                const data = await this.request(this.urls.resolve + '?' + new URLSearchParams({ q }));
                this.scan.matches = data.matches || [];
                this.scan.message = data.message || '';
                if (this.scan.matches.length === 1) {
                    await this.selectMatch(this.scan.matches[0], true);
                } else if (!this.scan.matches.length) {
                    this.closeProfile();
                }
            } catch (e) {
                this.scan.message = e.message;
            } finally {
                this.scan.loading = false;
            }
        },

        async selectMatch(m, fromScan = false) {
            this.profile.match = m;
            await this.loadProfile({ file_number: m.file_number, tracker_id: m.tracker ? m.tracker.id : '' });
            if (!fromScan || !this.profile.data) return;

            if (this.mode === 'in') {
                if (this.autoReceive && this.canReceive()) {
                    await this.receiveProfile();
                }
                this.scan.q = '';
                this.focusScan();
            } else {
                if (this.heldHere()) {
                    this.startSendFromProfile();
                    this.scan.q = '';
                } else {
                    this.scan.message = 'This file is not held at your office — receive it first.';
                }
            }
        },

        async loadProfile(params) {
            this.profile.open = true;
            this.profile.loading = true;
            this.profile.tab = 'history';
            try {
                const clean = Object.fromEntries(Object.entries(params).filter(([, v]) => v !== null && v !== undefined && v !== ''));
                this.profile.data = await this.request(this.urls.profile + '?' + new URLSearchParams(clean));
            } catch (e) {
                this.profile.data = null;
                this.notify('error', e.message);
            } finally {
                this.profile.loading = false;
            }
        },

        async reloadProfile() {
            if (!this.profile.open || !this.profile.data) return;
            const t = this.profile.data.tracker;
            await this.loadProfile({ file_number: this.profile.data.file.file_number || '', tracker_id: t ? t.id : '' });
        },

        openRow(row, startSend = false) {
            this.profile.match = { file_number: row.file_number, tracking_id: row.tracking_id, file_title: row.file_title, tracker: { id: row.id } };
            this.send = blankSend();
            this.loadProfile({ tracker_id: row.id, file_number: row.file_number || '' }).then(() => {
                if (startSend && this.heldHere()) this.startSendFromProfile();
            });
            this.$nextTick(() => window.scrollTo({ top: 0, behavior: 'smooth' }));
        },

        closeProfile() {
            this.profile = { open: false, loading: false, tab: 'history', data: null, match: null };
            this.send = blankSend();
        },

        heldHere() {
            const t = this.profile.data && this.profile.data.tracker;
            return !!(t && t.current_code === this.office && t.last_status === 'active');
        },

        canReceive() {
            const d = this.profile.data;
            if (!d || this.profile.loading) return false;
            if (!d.tracker) return !!d.file.file_number;
            return !this.heldHere();
        },

        canSend() {
            return this.heldHere() && !this.send.row;
        },

        profileHeading() {
            const d = this.profile.data;
            if (d && d.file.file_number) return d.file.file_number;
            if (this.profile.match && this.profile.match.file_number) return this.profile.match.file_number;
            return (d && d.file.file_title) || 'Document';
        },

        profileTitle() {
            const d = this.profile.data;
            return nice((d && d.file.file_title) || (this.profile.match && this.profile.match.file_title) || '');
        },

        profileLocation() {
            const f = this.profile.data && this.profile.data.file;
            if (!f) return '—';
            // "Plot 12" for a plot number; a descriptive value ("PIECE OF LAND") stands alone.
            const plot = blank(f.plot_number) ? null : (/\d/.test(f.plot_number) ? 'Plot ' + f.plot_number : nice(f.plot_number));
            return [plot, nice(f.district), nice(f.lga)].filter(Boolean).join(', ') || '—';
        },

        profileBadges() {
            const d = this.profile.data;
            if (!d) return [];
            const b = [];
            if (d.file.non_file) b.push({ label: 'Not a regular file', cls: 'bg-blue-100 text-blue-700' });
            else b.push(d.file.indexed ? { label: 'Indexed', cls: 'bg-green-100 text-green-700' } : { label: 'Not indexed', cls: 'bg-orange-100 text-orange-700' });
            if (d.file.decommissioned) b.push({ label: 'Decommissioned' + (d.file.successor ? ' → ' + d.file.successor : ''), cls: 'bg-red-100 text-red-700' });
            if (this.heldHere()) b.push({ label: 'Held at your office', cls: 'bg-emerald-100 text-emerald-700' });
            else if (d.tracker && d.tracker.last_status === 'pending_acceptance') b.push({ label: 'In transit', cls: 'bg-yellow-100 text-yellow-800' });
            return b;
        },

        profileDetails() {
            const d = this.profile.data;
            if (!d) return [];
            const f = d.file;
            const t = d.tracker || {};
            const regs = { 1: 'Registry 1', 2: 'Registry 2', 3: 'Registry 3' };
            const rows = [
                { label: 'File number', value: f.file_number },
                { label: 'File title', value: nice(f.file_title) },
                { label: 'Plot no.', value: nice(f.plot_number) },
                { label: 'Plot size', value: f.plot_size },
                { label: 'TP no.', value: f.tp_no },
                { label: 'Land use', value: nice(f.land_use) },
                { label: 'District', value: nice(f.district) },
                { label: 'LGA', value: nice(f.lga) },
                { label: 'Registry', value: regs[f.registry] || nice(f.registry) },
                { label: 'Shelf location', value: f.shelf_location },
                { label: 'Batch no.', value: f.batch_no },
                { label: 'Title status', value: nice(f.title_status) },
                { label: 'Sender', value: nice(f.sender) },
                { label: 'Reference', value: f.reference },
                { label: 'Tracking ID', value: t.tracking_id },
                { label: 'Logged by', value: nice(t.created_by) },
            ];
            return rows.filter(x => !blank(x.value) || ['File number', 'File title', 'Tracking ID'].includes(x.label));
        },

        initials(name) {
            const parts = String(name || '').trim().split(/\s+/).filter(Boolean);
            return (parts.slice(0, 2).map(p => p[0]).join('') || '?').toUpperCase();
        },

        statusLabel(s) {
            return { active: 'Received', pending_acceptance: 'In transit', completed: 'Moved on', log_out: 'Logged out', pending: 'Queued' }[s] || (s || '—');
        },
        statusPill(s) {
            return { active: 'bg-green-100 text-green-700', pending_acceptance: 'bg-yellow-100 text-yellow-800' }[s] || 'bg-gray-100 text-gray-600';
        },
        statusDot(s) {
            return { active: 'bg-green-500', pending_acceptance: 'bg-yellow-400' }[s] || 'bg-gray-300';
        },

        // ── Receive ───────────────────────────────────────────────────────
        async receiveProfile() {
            const d = this.profile.data;
            if (!d) return;
            await this.receive({
                entry_type: d.file.non_file ? 'non_file' : (d.file.indexed ? 'file' : 'unindexed'),
                tracker_id: d.tracker ? d.tracker.id : null,
                file_number: d.file.file_number,
                file_title: d.file.file_title,
                received_via: this.scan.matches.length ? 'scan' : 'manual',
            });
        },

        async receivePending(row) {
            await this.receive({
                entry_type: row.file_type === 'NON_FILE' ? 'non_file' : 'file',
                tracker_id: row.id,
                file_number: row.file_number,
                file_title: row.file_title,
                received_via: 'manual',
            });
        },

        async receive(payload, onDone) {
            this.busy = true;
            try {
                const data = await this.request(this.urls.receive, {
                    method: 'POST',
                    body: JSON.stringify({ office: this.office, ...payload }),
                });
                this.notify(data.action === 'already_here' ? 'info' : 'success', data.message);
                if (onDone) onDone(data);
                await this.loadLists();
                if (this.profile.open) {
                    if (!this.profile.data || !this.profile.data.tracker) {
                        await this.loadProfile({ tracker_id: data.tracker.id, file_number: data.tracker.file_number || '' });
                    } else {
                        await this.reloadProfile();
                    }
                }
                this.focusScan();
            } catch (e) {
                this.notify('error', e.message);
            } finally {
                this.busy = false;
            }
        },

        // ── Manual ────────────────────────────────────────────────────────
        openManual() {
            this.manual = { ...blankManual(), open: true };
        },

        manualTypeChanged() {
            this.manual.file_number = '';
            if (this.manual.entry_type !== 'non_file') {
                this.manual.sender = '';
                this.manual.reference = '';
            }
        },

        pickFileNumber() {
            if (!window.GlobalFileNoModal) {
                this.notify('error', 'The file number selector failed to load. Refresh the page.');
                return;
            }
            GlobalFileNoModal.open({
                callback: (data) => {
                    this.manual.file_number = data.fileNumber || '';
                    if (!this.manual.file_title && data.file_title) this.manual.file_title = data.file_title;
                    // The selector returns a record only for an indexed/known file.
                    if (data.record && this.manual.entry_type === 'unindexed') this.manual.entry_type = 'file';
                    if (!data.record && this.manual.entry_type === 'file') this.manual.entry_type = 'unindexed';
                },
            });
        },

        manualValid() {
            if (this.manual.entry_type === 'non_file') return this.manual.file_title.trim() !== '';
            return this.manual.file_number.trim() !== '';
        },

        async receiveManual() {
            const m = this.manual;
            await this.receive({
                entry_type: m.entry_type,
                file_number: m.entry_type === 'non_file' ? null : m.file_number,
                file_title: m.file_title,
                sender: m.sender,
                reference: m.reference,
                from_office: m.from_office,
                notes: m.notes,
                received_via: 'manual',
            }, (data) => {
                this.manual = blankManual();
                this.profile.match = { file_number: data.tracker.file_number, file_title: data.tracker.file_title, tracker: { id: data.tracker.id } };
                this.loadProfile({ tracker_id: data.tracker.id, file_number: data.tracker.file_number || '' });
            });
        },

        // ── Send ──────────────────────────────────────────────────────────
        startSendFromProfile() {
            const d = this.profile.data;
            if (!d || !d.tracker) return;
            this.send = { row: { id: d.tracker.id, file_number: d.file.file_number, file_title: d.file.file_title }, to_office: '', purpose: '', notes: '' };
        },

        async forward() {
            if (!this.send.row || !this.send.to_office) return;
            this.busy = true;
            try {
                const data = await this.request(this.urls.forward, {
                    method: 'POST',
                    body: JSON.stringify({
                        office: this.office,
                        tracker_id: this.send.row.id,
                        to_office: this.send.to_office,
                        purpose: this.send.purpose,
                        notes: this.send.notes,
                    }),
                });
                this.notify('success', data.message);
                this.send = blankSend();
                await this.loadLists();
                await this.reloadProfile();
                this.focusScan();
            } catch (e) {
                this.notify('error', e.message);
            } finally {
                this.busy = false;
            }
        },

        // ── Lightbox ──────────────────────────────────────────────────────
        openLightbox(items, index) {
            this.lightbox = { open: true, items, index };
        },
        lightboxItem() {
            return this.lightbox.items[this.lightbox.index] || null;
        },
        lightboxStep(step) {
            const n = this.lightbox.items.length;
            if (n) this.lightbox.index = (this.lightbox.index + step + n) % n;
        },
        lightboxCaption() {
            const p = this.lightboxItem();
            if (!p) return '';
            const label = [nice(p.type), nice(p.subtype)].filter(Boolean).join(' — ') || 'Page';
            return label + (p.code ? ' · ' + p.code : '') + (this.lightbox.items.length > 1 ? '  (' + (this.lightbox.index + 1) + ' / ' + this.lightbox.items.length + ')' : '');
        },
    };
};
