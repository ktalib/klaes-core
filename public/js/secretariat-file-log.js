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
        indexQ: '', indexOpen: false, related_file_number: '',
        receiving_officer_id: '', request_purpose_id: '', officerFilter: '', officerOpen: false,
        officer_other: '', purpose_other: '', from_other: '',
    });
    const blankSend = () => ({ row: null, to_office: '', receiving_officer_id: '', request_purpose_id: '', officerFilter: '', officerOpen: false,
        officer_other: '', purpose_other: '', notes: '' });
    // A listed choice, or "Other" with its specify box filled in (as on Quick Search).
    const chosen = (value, other) => value === 'other' ? String(other || '').trim() !== '' : !!value;
    const listed = (value) => (value && value !== 'other') ? value : null;
    const typed = (value, other) => value === 'other' ? (String(other || '').trim() || null) : null;
    const blankScan = () => ({ q: '', loading: false, message: '', matches: [] });

    return {
        offices: config.offices || [],
        urls: config.urls,
        canChooseOffice: !!config.canChooseOffice,
        canDeleteLogs: !!config.canDeleteLogs,
        requestPurposes: config.requestPurposes || [],
        officers: config.officers || [],
        myOffice: config.myOffice || null,
        nice,
        office: '',
        officeLocked: false,
        mode: 'in',
        // Always on: a scanned file is received straight away (no toggle on the page).
        autoReceive: true,
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
        indexed: { items: [], loading: false, loaded: false },
        _indexTimer: null,
        _indexSeq: 0,
        profile: { open: false, loading: false, tab: 'history', data: null, match: null },
        lightbox: { open: false, items: [], index: 0 },
        keyTimes: [],
        autoTimer: null,
        globalBuffer: '',
        globalTimes: [],
        entryTypes: [
            { value: 'file', icon: 'folder-check', label: 'Indexed file', hint: 'No tracking sheet or QR, but the file is indexed.' },
            { value: 'unindexed', icon: 'folder-x', label: 'Not indexed', hint: 'A file number that has not been indexed yet.' },
            { value: 'non_file', icon: 'mail', label: 'Not a regular file', hint: 'A letter, memo or other document — not a file folder.' },
        ],
        registerTabs: [
            { key: 'pending', label: 'Pending receipt', badge: 'bg-yellow-100 text-yellow-800' },
            { key: 'held', label: 'Held at my office', badge: 'bg-green-100 text-green-800' },
            { key: 'sent', label: 'Outgoing', badge: 'bg-gray-100 text-gray-700' },
        ],

        init() {

            // Normal users work as the office their account maps to (server-side,
            // config/file_movement.php) and cannot change it. Only a super admin
            // picks an office, remembered per user in localStorage.
            let start = '';
            if (!this.canChooseOffice) {
                start = this.myOffice ? this.myOffice.code : '';
            } else {
                const saved = store.get(officeKey) || '';
                start = this.offices.some(o => o.office_code === saved) ? saved : (this.myOffice ? this.myOffice.code : '');
            }
            if (start) {
                this.office = start;
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
            if (!this.office || !this.canChooseOffice) return;
            store.set(officeKey, this.office);
            this.officeLocked = true;
            this.closeProfile();
            this.loadLists();
            this.$nextTick(() => this.focusScan());
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
                { key: 'sent', tab: 'sent', label: 'Outgoing', value: this.lists.sent.length, icon: 'send', text: 'text-indigo-600', bg: 'bg-indigo-100', hint: 'Sent out ' + (periods[this.days] || '') },
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

        fmtDate(value) {
            if (!value) return '';
            const d = new Date(String(value).replace(' ', 'T'));
            return isNaN(d.getTime()) ? value : d.toLocaleDateString('en-GB', { day: '2-digit', month: '2-digit', year: 'numeric' });
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
                { label: 'Logged by', value: nice(t.created_by) },
            ];
            return rows.filter(x => !blank(x.value) || ['File number', 'File title'].includes(x.label));
        },

        initials(name) {
            const parts = String(name || '').trim().split(/\s+/).filter(Boolean);
            return (parts.slice(0, 2).map(p => p[0]).join('') || '?').toUpperCase();
        },

        // ── Movement timeline — same data and order as Quick Search ─────────
        // (create_file_tracker_page/quick_search.blade.php: compareMovementEntries,
        //  renderMovementRow). The status wording is deliberately the office's
        //  view, not the registry's — see qsStatus().
        historyEntries() {
            const h = this.profile.data && this.profile.data.history;
            return (h && Array.isArray(h.entries)) ? h.entries : [];
        },
        historyMeta() {
            return (this.profile.data && this.profile.data.history && this.profile.data.history.meta) || {};
        },
        movementTs(entry) {
            const parse = (date, time) => {
                const d = (date || '').toString().trim();
                if (!d) return null;
                const t = (time || '').toString().trim() || '00:00';
                const ts = Date.parse(d + ' ' + t);
                return Number.isNaN(ts) ? null : ts;
            };
            const inTs = parse(entry.log_in_date || entry.logInDate, entry.log_in_time || entry.logInTime);
            if (inTs !== null) return inTs;
            const outTs = parse(entry.log_out_date || entry.logOutDate, entry.log_out_time || entry.logOutTime);
            if (outTs !== null) return outTs;
            const created = Date.parse(entry.created_at || entry.createdAt || '');
            return Number.isNaN(created) ? Number.POSITIVE_INFINITY : created;
        },
        sortedEntries() {
            return [...this.historyEntries()].sort((a, b) => this.movementTs(a) - this.movementTs(b));
        },
        isApproval(entry) {
            return ['recommendation', 'approval'].includes(String(entry.purpose || '').toLowerCase());
        },
        movementRows() {
            return this.sortedEntries().filter(e => !this.isApproval(e));
        },
        approvalRows() {
            return this.sortedEntries().filter(e => this.isApproval(e));
        },
        showHomeRow() {
            const meta = this.historyMeta();
            return !meta.is_commissioned && !this.historyEntries().some(e => e && e._range_home);
        },
        // Status from the OFFICE's side, not the registry's. Quick Search reads
        // the log as the registry does: an office's entry says "Log-out" because
        // the file was logged out of the registry into that office. Here receiving
        // a file logs it IN to the office, and sending it on logs it OUT of the
        // office — so registry Log-in / Log-out are flipped on this page.
        qsStatus(entry) {
            const base = this.qsBaseStatus(entry);
            // A file is in one place at a time: only the latest movement can still
            // be "logged in" or "in transit". Anything followed by a later movement
            // has been logged out (or, for a hand-over, overtaken).
            if (base.key === 'in' || base.key === 'transit') {
                const rows = this.movementRows();
                if (rows.length && rows[rows.length - 1] !== entry) {
                    return base.key === 'in'
                        ? { label: 'Logged out', style: 'background:#ffedd5;color:#9a3412;border:1px solid #fed7aa;', key: 'out' }
                        : { label: 'Not received', style: 'background:#f3f4f6;color:#4b5563;border:1px solid #d1d5db;', key: 'other' };
                }
            }
            return base;
        },
        qsBaseStatus(entry) {
            const green = 'background:#d1fae5;color:#166534;border:1px solid #a7f3d0;';
            const orange = 'background:#ffedd5;color:#9a3412;border:1px solid #fed7aa;';
            const amber = 'background:#fef9c3;color:#78350f;border:1px solid #fde68a;';
            const red = 'background:#fee2e2;color:#b91c1c;border:1px solid #fecaca;';
            const grey = 'background:#f3f4f6;color:#4b5563;border:1px solid #d1d5db;';
            const indigo = 'background:#e0e7ff;color:#3730a3;border:1px solid #c7d2fe;';
            const loggedIn = { label: 'Logged in', style: green, key: 'in' };
            const loggedOut = { label: 'Logged out', style: orange, key: 'out' };
            const override = (entry.status_label || entry.statusLabel || entry.new_status || entry.newStatus || '').toString().trim();
            const raw = (entry.status || '').toString().trim().toLowerCase();
            if (override) {
                switch (override.toLowerCase().replace(/_/g, ' ')) {
                    case 'log-out': case 'log out': return loggedIn;    // out of the registry = into this office
                    case 'log-in': case 'log in': return loggedOut;     // back into the registry = out of this office
                    case 'pending acceptance': case 'in-transit': case 'in transit': return { label: 'In transit', style: amber, key: 'transit' };
                    case 'rejected': return { label: 'Rejected', style: red, key: 'other' };
                    case 'cancelled': case 'canceled': return { label: 'Cancelled', style: grey, key: 'other' };
                    default: return { label: override, style: indigo, key: 'other' };
                }
            }
            switch (raw) {
                case 'pending_acceptance': return { label: 'In transit', style: amber, key: 'transit' };
                case 'active': case 'logged_out': case 'log_out': return loggedIn;   // logged_out = back into the registry
                case 'completed': return loggedOut;
                case 'rejected': return { label: 'Rejected', style: red, key: 'other' };
                default: return { label: nice(String(entry.status || 'Completed').replace(/_/g, ' ')), style: indigo, key: 'other' };
            }
        },
        ampm(time) {
            if (!time) return '';
            const parts = time.toString().trim().split(':');
            if (parts.length < 2) return time;
            let h = parseInt(parts[0], 10);
            if (isNaN(h)) return time;
            const period = h >= 12 ? 'PM' : 'AM';
            h = h % 12 || 12;
            return h + ':' + parts[1].padStart(2, '0') + ' ' + period;
        },
        movementDate(date, time) {
            const d = (date || '').toString().trim();
            if (!d) return '—';
            return time ? d + ' ' + this.ampm(time) : d;
        },
        // One row per event, never rewritten: receiving a file adds a "Logged in"
        // row; sending it on adds a separate "Logged out" row (and leaves the
        // "Logged in" row as it was); the next office's receipt adds its own
        // "Logged in". The tracker keeps one entry per office stay — shared with
        // Log a File and Quick Search, so its format is not changed — and each
        // stay is split into its in and out events here.
        historyEvents() {
            const rows = this.movementRows();
            const parse = (date, time) => {
                const d = (date || '').toString().trim();
                if (!d) return null;
                const ts = Date.parse(d + ' ' + ((time || '').toString().trim() || '00:00'));
                return Number.isNaN(ts) ? null : ts;
            };
            const stamp = (ts) => {
                if (ts === null || ts === undefined || !isFinite(ts)) return '';
                const d = new Date(ts);
                const pad = (n) => String(n).padStart(2, '0');
                return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + ' ' + this.ampm(pad(d.getHours()) + ':' + pad(d.getMinutes()));
            };
            const created = (e) => { const t = Date.parse(e.timestamp || e.created_at || ''); return Number.isNaN(t) ? null : t; };
            const GREEN = { style: 'background:#d1fae5;color:#166534;border:1px solid #a7f3d0;', dot: '#10b981', border: '#a7f3d0' };
            const ORANGE = { style: 'background:#ffedd5;color:#9a3412;border:1px solid #fed7aa;', dot: '#f97316', border: '#fed7aa' };
            const AMBER = { style: 'background:#fef9c3;color:#78350f;border:1px solid #fde68a;', dot: '#eab308', border: '#fde68a' };
            const GREY = { style: 'background:#f3f4f6;color:#4b5563;border:1px solid #d1d5db;', dot: '#9ca3af', border: '#e5e7eb' };
            const INDIGO = { style: 'background:#e0e7ff;color:#3730a3;border:1px solid #c7d2fe;', dot: '#6366f1', border: '#e5e7eb' };
            // The entry's own purpose. A receipt logged on this page has none of its
            // own (the tracker's purpose is set later, when the file is sent on), so
            // it does not borrow one; registry entries fall back to the tracker's.
            const purposeOf = (e) => {
                const p = e.purpose && String(e.purpose).toLowerCase() !== 'received' ? e.purpose : '';
                if (p) return p;
                return e.acceptance_source === 'secretariat_receive' ? '' : (this.historyMeta().request_purpose_name || '');
            };

            const events = [];
            rows.forEach((e, i) => {
                const office = e.office_name || e.office || e.receiving_office_name || 'Unknown';
                const base = this.qsBaseStatus(e);
                const latest = i === rows.length - 1;
                const next = rows[i + 1] || null;
                const inTs = parse(e.log_in_date || e.logInDate, e.log_in_time || e.logInTime);
                const outTs = parse(e.log_out_date || e.logOutDate, e.log_out_time || e.logOutTime);
                const common = {
                    ref: e._ref || null,
                    office,
                    officer: this.qsOfficer(e) !== '-' ? this.qsOfficer(e) : '',
                    purpose: purposeOf(e),
                    delay: e.delay_reason || '',
                    notes: e.notes || '',
                };

                if (base.key === 'other') {
                    const ts = inTs ?? outTs ?? created(e);
                    events.push({ ...common, ...INDIGO, ...{ style: base.style }, label: base.label, timeLabel: 'Date', time: stamp(ts), ts, primary: true });
                    return;
                }
                if (base.key === 'transit') {
                    const ts = created(e) ?? outTs;
                    events.push(latest
                        ? { ...common, ...AMBER, label: 'In transit', timeLabel: 'Sent', time: stamp(ts), ts, primary: true, notes: 'Awaiting receipt at this office.' + (common.notes ? ' ' + common.notes : '') }
                        : { ...common, ...GREY, label: 'Not received', timeLabel: 'Sent', time: stamp(ts), ts, primary: true });
                    return;
                }

                // Received here.
                const loginTs = inTs ?? created(e);
                events.push({ ...common, ...GREEN, label: 'Logged in', timeLabel: 'Logged in', time: stamp(loginTs), ts: loginTs, primary: true,
                    by: nice(e.accepted_by_name || '') || '' });

                // Left here, if it has: stored as completed, or followed by a later
                // movement. A log-out stamped before the log-in is Log a File's
                // pre-stamp, not a departure — use when the next movement began.
                const left = base.key === 'out' || !latest;
                if (left) {
                    let leftTs = (outTs !== null && (loginTs === null || outTs >= loginTs)) ? outTs : null;
                    if (leftTs === null && next) leftTs = created(next) ?? this.movementTs(next);
                    events.push({ ...common, ...ORANGE, label: 'Logged out', timeLabel: 'Logged out', time: stamp(leftTs), ts: leftTs ?? loginTs,
                        to: next ? (next.office_name || next.office || next.receiving_office_name || '') : '',
                        purpose: next ? purposeOf(next) : common.purpose,
                        officer: '', notes: e.completion_notes || '' });
                }
            });

            // Chronological; a stay's in-row always precedes its out-row (stable sort).
            return events
                .map((ev, idx) => ({ ...ev, _i: idx }))
                .sort((a, b) => ((a.ts ?? Infinity) - (b.ts ?? Infinity)) || (a._i - b._i));
        },

        // Log In = when the file was received at this office.
        qsIn(entry) {
            const key = this.qsStatus(entry).key;
            if (key === 'transit') return 'Awaiting receipt';
            const date = entry.log_in_date || entry.logInDate;
            return date ? this.movementDate(date, entry.log_in_time || entry.logInTime) : '—';
        },
        // Log Out = when the file left this office. A file still held here has
        // not left, even if Log a File pre-stamped a log-out date at creation.
        qsOut(entry) {
            const key = this.qsStatus(entry).key;
            if (key === 'in') return 'Still here';
            if (key === 'transit') return '—';
            const date = entry.log_out_date || entry.logOutDate;
            return date ? this.movementDate(date, entry.log_out_time || entry.logOutTime) : '—';
        },
        // Colour for the Log In / Log Out rows: green for in, orange for out,
        // muted when there is no time to show.
        qsCellStyle(value, kind) {
            if (!value || value === '—' || value === '-') return 'color:#9ca3af;';
            if (value === 'Still here') return 'background:#ecfdf5;color:#047857;border:1px solid #a7f3d0;';
            if (value === 'Awaiting receipt') return 'background:#fef9c3;color:#78350f;border:1px solid #fde68a;';
            return kind === 'in'
                ? 'background:#d1fae5;color:#166534;border:1px solid #a7f3d0;'
                : 'background:#ffedd5;color:#9a3412;border:1px solid #fed7aa;';
        },
        qsOfficer(entry) {
            return nice(entry.receiving_officer_name || entry.receivingOfficerName || entry.accepted_by_name || '-');
        },
        qsTimeline() {
            const meta = this.historyMeta();
            const styles = {
                green: 'background:#d1fae5;color:#166534;border:1px solid #a7f3d0;',
                amber: 'background:#fef9c3;color:#78350f;border:1px solid #fde68a;',
                red: 'background:#fee2e2;color:#b91c1c;border:1px solid #fecaca;',
                pending: 'background:#e2e8f0;color:#475569;border:1px solid #cbd5e1;',
            };
            if (!meta.timeline_status || !styles[meta.timeline_status]) return null;
            if (meta.timeline_status === 'pending') return { label: 'Pending', style: styles.pending };
            const days = meta.days_until_deadline;
            let label;
            if (days === null || days === undefined) label = { green: 'On Track', amber: 'Due Soon', red: 'Overdue' }[meta.timeline_status];
            else if (days > 0) label = days + ' day' + (days === 1 ? '' : 's') + ' left';
            else if (days === 0) label = 'Due today';
            else label = Math.abs(days) + ' day' + (Math.abs(days) === 1 ? '' : 's') + ' overdue';
            return { label, style: styles[meta.timeline_status] };
        },
        profileRegistry() {
            const f = (this.profile.data && this.profile.data.file) || {};
            const regs = { 1: 'Registry 1', 2: 'Registry 2', 3: 'Registry 3' };
            const name = regs[f.registry] || nice(f.registry) || 'Registry / Archive';
            return name + (f.shelf_location ? ' — Shelf/Rack ' + f.shelf_location : '');
        },
        qsExpectedReturn() {
            const value = this.historyMeta().deadline;
            if (!value) return '—';
            const d = new Date(value);
            if (Number.isNaN(d.getTime())) return String(value).slice(0, 10);
            return String(d.getDate()).padStart(2, '0') + '/' + String(d.getMonth() + 1).padStart(2, '0') + '/' + d.getFullYear();
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

        // Indexed files: a searchable dropdown of file_indexings (no selector modal).
        openIndexed() {
            this.manual.indexOpen = true;
            if (!this.indexed.loaded) this.searchIndexed(true);
        },
        searchIndexed(now) {
            this.manual.indexOpen = true;
            clearTimeout(this._indexTimer);
            const run = async () => {
                const q = (this.manual.indexQ || '').trim();
                const seq = ++this._indexSeq;
                this.indexed.loading = true;
                try {
                    const res = await fetch(this.urls.indexed + '?q=' + encodeURIComponent(q), { headers: { Accept: 'application/json' } });
                    const data = await res.json();
                    if (seq !== this._indexSeq) return;   // a newer search is under way
                    this.indexed.items = data.success ? data.files : [];
                    this.indexed.loaded = true;
                } catch (e) {
                    if (seq === this._indexSeq) this.indexed.items = [];
                } finally {
                    if (seq === this._indexSeq) this.indexed.loading = false;
                }
            };
            if (now) run(); else this._indexTimer = setTimeout(run, 250);
        },
        pickIndexed(f) {
            this.manual.file_number = f.file_number;
            this.manual.file_title = f.file_title || '';
            this.manual.indexQ = f.file_number;
            this.manual.indexOpen = false;
        },

        manualTypeChanged() {
            this.manual.file_number = '';
            this.manual.related_file_number = '';
            this.manual.indexQ = '';
            this.manual.indexOpen = false;
            // An indexed file's title comes from its record, never typed.
            if (this.manual.entry_type === 'file') this.manual.file_title = '';
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
            // Not-indexed files only: the selector opens on Manual Entry alone.
            GlobalFileNoModal.open({
                manualOnly: true,
                callback: (data) => {
                    this.manual.file_number = data.fileNumber || '';
                    if (!this.manual.file_title && data.file_title) this.manual.file_title = data.file_title;
                },
            });
        },

        manualValid() {
            const m = this.manual;
            if (!chosen(m.receiving_officer_id, m.officer_other) || !chosen(m.request_purpose_id, m.purpose_other)) return false;
            if (m.from_office === 'other' && !m.from_other.trim()) return false;
            if (m.entry_type === 'non_file') return m.file_title.trim() !== '';
            return m.file_number.trim() !== '';
        },

        async receiveManual() {
            const m = this.manual;
            await this.receive({
                entry_type: m.entry_type,
                file_number: m.entry_type === 'non_file' ? null : m.file_number,
                related_file_number: m.entry_type === 'non_file' ? (m.related_file_number || null) : null,
                receiving_officer_id: listed(m.receiving_officer_id),
                receiving_officer_other: typed(m.receiving_officer_id, m.officer_other),
                request_purpose_id: listed(m.request_purpose_id),
                request_purpose_other: typed(m.request_purpose_id, m.purpose_other),
                from_office_other: typed(m.from_office, m.from_other),
                file_title: m.file_title,
                sender: m.sender,
                reference: m.reference,
                from_office: listed(m.from_office),
                notes: m.notes,
                received_via: 'manual',
            }, (data) => {
                this.manual = blankManual();
                this.profile.match = { file_number: data.tracker.file_number, file_title: data.tracker.file_title, tracker: { id: data.tracker.id } };
                this.loadProfile({ tracker_id: data.tracker.id, file_number: data.tracker.file_number || '' });
            });
        },

        // ── Send ──────────────────────────────────────────────────────────
        pickOfficer(o) {
            this.send.receiving_officer_id = o.id;
            this.send.officerFilter = nice(o.name);
            this.send.officerOpen = false;
        },

        // Officers in the destination office's department first, then everyone.
        // Once an officer is picked the box shows their name; list everyone again.
        officerGroups() {
            return this.groupOfficers(this.send, this.send.to_office);
        },
        // Manual log: the officer at my office who took the file; my department first.
        manualOfficerGroups() {
            return this.groupOfficers(this.manual, this.office);
        },
        pickManualOfficer(o) {
            this.manual.receiving_officer_id = o.id;
            this.manual.officerFilter = nice(o.name);
            this.manual.officerOpen = false;
        },
        groupOfficers(state, officeCode) {
            const f = state.receiving_officer_id ? '' : (state.officerFilter || '').trim().toLowerCase();
            const list = f ? this.officers.filter(o => (String(o.name) + ' ' + String(o.username || '')).toLowerCase().includes(f)) : this.officers;
            const to = this.offices.find(o => o.office_code === officeCode);
            const dept = String((to && to.department) || '').toLowerCase().replace(/\s*department\s*$/, '').trim();
            const inDept = (o) => {
                const d = String(o.department || '').toLowerCase();
                return dept && d && (d === dept || d.includes(dept) || dept.includes(d));
            };
            const mine = dept ? list.filter(inDept) : [];
            const rest = list.filter(o => !mine.includes(o));
            const groups = [];
            if (mine.length) groups.push({ label: nice(to.department) + ' department', items: mine });
            if (rest.length) groups.push({ label: mine.length ? 'All other officers' : 'Officers', items: rest });
            return groups;
        },

        startSendFromProfile() {
            const d = this.profile.data;
            if (!d || !d.tracker) return;
            this.send = { ...blankSend(), row: { id: d.tracker.id, file_number: d.file.file_number, file_title: d.file.file_title } };
        },

        sendValid() {
            const s = this.send;
            return !!(s.row && s.to_office && chosen(s.receiving_officer_id, s.officer_other) && chosen(s.request_purpose_id, s.purpose_other));
        },

        async forward() {
            if (!this.sendValid()) return;
            this.busy = true;
            try {
                const data = await this.request(this.urls.forward, {
                    method: 'POST',
                    body: JSON.stringify({
                        office: this.office,
                        tracker_id: this.send.row.id,
                        to_office: this.send.to_office,
                        request_purpose_id: listed(this.send.request_purpose_id),
                        request_purpose_other: typed(this.send.request_purpose_id, this.send.purpose_other),
                        receiving_officer_id: listed(this.send.receiving_officer_id),
                        receiving_officer_other: typed(this.send.receiving_officer_id, this.send.officer_other),
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

        // ── Admin: delete one stored log ─────────────────────────────────
        async deleteLog(ev) {
            if (!this.canDeleteLogs || !ev.ref) return;
            const where = nice(ev.office) + (ev.time ? ' — ' + ev.time : '');
            const ask = await Swal.fire({
                icon: 'warning',
                title: 'Delete this log?',
                html: '<div style="text-align:left;font-size:14px">'
                    + '<p><strong>' + where.replace(/</g, '&lt;') + '</strong></p>'
                    + '<p style="margin-top:6px">This removes the whole step at this office (its Logged in and Logged out rows). '
                    + 'If it is the newest step, the step before it becomes the file\'s current location again.</p>'
                    + '<p style="margin-top:6px;color:#6b7280">A copy of the log is kept in the audit file.</p></div>',
                input: 'textarea',
                inputPlaceholder: 'Reason for deleting (required)',
                inputValidator: (v) => (!v || !v.trim()) ? 'Give a reason.' : undefined,
                showCancelButton: true,
                confirmButtonText: 'Delete log',
                confirmButtonColor: '#dc2626',
            });
            if (!ask.isConfirmed) return;
            this.busy = true;
            try {
                const data = await this.request(this.urls.deleteLog, {
                    method: 'POST',
                    body: JSON.stringify({ tracker_id: ev.ref.tracker_id, index: ev.ref.index, key: ev.ref.key, reason: ask.value.trim() }),
                });
                this.notify('success', data.message);
                await this.loadLists();
                await this.reloadProfile();
            } catch (e) {
                this.notify('error', e.message);
            } finally {
                this.busy = false;
            }
        },

        // Admin: delete a whole tracker (every log), as on the main Log a File page.
        async deleteTracker(row) {
            if (!this.canDeleteLogs || !row || !row.id) return;
            const label = (row.file_number || row.related_file_number || '') + (row.file_title ? ' — ' + nice(row.file_title) : '');
            const ask = await Swal.fire({
                icon: 'warning',
                title: 'Delete entire tracker?',
                html: '<div style="text-align:left;font-size:14px">'
                    + '<p>This permanently deletes <strong>' + (label || 'this tracker').replace(/</g, '&lt;') + '</strong> and all of its log entries.</p>'
                    + '<p style="margin-top:6px">Earlier trackers for the same file are kept, so its history before this tracker still shows.</p>'
                    + '<p style="margin-top:6px;color:#6b7280">A full copy is kept in the audit file.</p></div>',
                input: 'textarea',
                inputPlaceholder: 'Reason for deleting (required)',
                inputValidator: (v) => (!v || !v.trim()) ? 'Give a reason.' : undefined,
                showCancelButton: true,
                confirmButtonText: 'Yes, delete tracker',
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#6b7280',
                reverseButtons: true,
            });
            if (!ask.isConfirmed) return;
            this.busy = true;
            try {
                const data = await this.request(this.urls.deleteTracker, {
                    method: 'POST',
                    body: JSON.stringify({ tracker_id: row.id, reason: ask.value.trim() }),
                });
                this.notify('success', data.message);
                const shown = this.profile.data && this.profile.data.tracker;
                if (shown && Number(shown.id) === Number(row.id)) this.closeProfile();
                await this.loadLists();
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
