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
    const autoRefreshKey = 'secretariatFileLog.autoRefresh.' + userKey;
    const AUTO_REFRESH_SECONDS = 30;
    // Blank signature columns on the printed register (PDF only; empty in a CSV).
    const SIGN_SENDER = 'Sending Officer (Sign)';
    const SIGN_RECEIVER = 'Receiving Officer (Sign)';

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
        sender: '', sender_type: '', sender_mda: '', sender_lga: '', sender_dept: '', document_kind: '', kind_other: '',
        sender_person: '', sender_name: '', sender_rank: '', sender_rank_other: '',
        from_department: '', send_to_office: '', reference: '', from_office: '', notes: '',
        indexQ: '', indexOpen: false, related_file_number: '',
        receiving_officer_id: '', request_purpose_id: '', officerFilter: '', officerOpen: false,
        officer_other: '', purpose_other: '', from_other: '', timeline_days: '',
    });
    const blankSend = () => ({ row: null, to_office: '', receiving_officer_id: '', request_purpose_id: '', officerFilter: '', officerOpen: false,
        officer_other: '', purpose_other: '', notes: '' });
    // A listed choice, or "Other" with its specify box filled in (as on Quick Search).
    const chosen = (value, other) => value === 'other' ? String(other || '').trim() !== '' : !!value;
    const listed = (value) => (value && value !== 'other') ? value : null;
    const typed = (value, other) => value === 'other' ? (String(other || '').trim() || null) : null;
    const blankScan = () => ({ q: '', loading: false, message: '', matches: [] });
    // Batch mode: files collected by scan (or picked from the register), then
    // received or sent together. Each file goes through the same single-file
    // endpoint, so it is checked and logged exactly as one scan would be.
    const blankBatchAll = () => ({ to_office: '', receiving_officer_id: '', officer_other: '', request_purpose_id: '', purpose_other: '' });
    const blankBatch = (on = false) => ({ on, items: [], running: false, notes: '', pick: '', all: blankBatchAll() });
    const blankEdit = () => ({ open: false, ref: null, office: '', status: '', statusLabel: '', initial: {},
        log_in_date: '', log_in_time: '', log_out_date: '', log_out_time: '',
        receiving_officer_id: '', officer_other: '', request_purpose_id: '', purpose_other: '',
        notes: '', completion_notes: '', reason: '', office_code: '', isNewest: false });

    return {
        offices: config.offices || [],
        urls: config.urls,
        canChooseOffice: !!config.canChooseOffice,
        canDeleteLogs: !!config.canDeleteLogs,
        canEditLogs: !!config.canEditLogs,
        requestPurposes: config.requestPurposes || [],
        officers: config.officers || [],
        officerRanks: config.officerRanks || [],
        lgas: config.lgas || [],
        userId: config.userId || null,
        userName: config.userName || '',
        externalSenders: config.externalSenders || [],
        myOffice: config.myOffice || null,
        nice,
        office: '',
        officeLocked: false,
        mode: 'in',
        // Always on: a scanned file is received straight away (no toggle on the page).
        autoReceive: true,
        busy: false,
        tab: 'held',
        // Both tabs split into Incoming (waiting for receipt + held here) and Outgoing.
        sub: 'incoming',
        days: 7,
        // Date range (Y-m-d). Filters every tab by the row's date; on Outgoing it
        // also replaces the "Last N days" window on the server.
        dateFrom: '',
        dateTo: '',
        exportOpen: false,
        filter: '',
        listsLoading: false,
        autoRefresh: false,
        autoRefreshSeconds: AUTO_REFRESH_SECONDS,
        lastRefreshed: null,
        _autoTimer: null,
        lists: { pending: [], held: [], sent: [] },
        // Mails tab (key 'memo'): '' all, or one document kind (mail, memo, file, other).
        memoFilter: '',
        // Incoming is split into two inner tabs (user, 2026-10-16): 'pending' = Yet to
        // Receive (awaiting receipt), 'held' = Received (held here). Rows carry the bucket.
        incomingView: 'pending',
        incomingViews: [
            { key: 'pending', label: 'Yet to Receive' },
            { key: 'held', label: 'Received' },
        ],
        // Register pagination, client-side over visibleRows(); Export still takes them all.
        page: 1,
        perPage: 25,
        perPageOptions: [10, 25, 50, 100],
        subTabs: [
            { key: 'incoming', label: 'Incoming' },
            { key: 'outgoing', label: 'Outgoing' },
        ],
        scan: blankScan(),
        scanFocused: false,
        send: blankSend(),
        edit: blankEdit(),
        batch: blankBatch(),
        manual: blankManual(),
        indexed: { items: [], loading: false, loaded: false },
        _indexTimer: null,
        _indexSeq: 0,
        profile: { open: false, loading: false, tab: 'history', data: null, match: null },
        sheetBusy: false,
        lightbox: { open: false, items: [], index: 0 },
        keyTimes: [],
        autoTimer: null,
        globalBuffer: '',
        globalTimes: [],
        // Each entry type has its own colour (inline, so the Tailwind v2 CDN cannot drop it).
        entryTypes: [
            { value: 'file', icon: 'folder-check', label: 'Indexed file', hint: 'No tracking sheet or QR, but the file is indexed.',
              solid: '#16a34a', bg: '#f0fdf4', tint: '#dcfce7', text: '#166534', border: '#bbf7d0' },
            { value: 'unindexed', icon: 'folder-x', label: 'Not indexed', hint: 'A file number that has not been indexed yet.',
              solid: '#ea580c', bg: '#fff7ed', tint: '#ffedd5', text: '#9a3412', border: '#fed7aa' },
            { value: 'non_file', icon: 'mail', label: 'Not a regular Mail/Letter/Memo/File', hint: 'A letter, memo or other document — not a file folder.',
              solid: '#2563eb', bg: '#eff6ff', tint: '#dbeafe', text: '#1e40af', border: '#bfdbfe' },
        ],
        // Each kind has its own colour: solid when chosen, tinted otherwise and on badges.
        // Inline, so the Tailwind v2 CDN cannot drop them.
        documentKinds: [
            { value: 'mail', icon: 'mail', label: 'Mail', solid: '#2563eb', bg: '#dbeafe', text: '#1e40af', border: '#bfdbfe' },
            { value: 'letter', icon: 'mail-open', label: 'Letter', solid: '#db2777', bg: '#fce7f3', text: '#9d174d', border: '#fbcfe8' },
            { value: 'memo', icon: 'sticky-note', label: 'Memo', solid: '#d97706', bg: '#fef3c7', text: '#92400e', border: '#fde68a' },
            { value: 'file', icon: 'folder', label: 'File', solid: '#16a34a', bg: '#dcfce7', text: '#166534', border: '#bbf7d0' },
            { value: 'other', icon: 'file-question', label: 'Other', solid: '#7c3aed', bg: '#ede9fe', text: '#5b21b6', border: '#ddd6fe' },
        ],
        senderTypes: ['Internal', 'External', 'Other'],
        registerTabs: [
            { key: 'held', label: 'Files', badge: 'bg-green-100 text-green-800' },
            { key: 'memo', label: 'Mails', badge: 'bg-blue-100 text-blue-700' },
        ],

        init() {
            // Anything that changes which rows are listed goes back to page 1.
            ['tab', 'sub', 'incomingView', 'filter', 'memoFilter', 'dateFrom', 'dateTo', 'perPage']
                .forEach((key) => this.$watch(key, () => { this.page = 1; }));

            // With no date range, the Filter box also searches Outgoing beyond "Last N days"
            // (server side), so a file sent weeks ago is still found.
            this.$watch('filter', () => {
                clearTimeout(this._searchTimer);
                this._searchTimer = setTimeout(() => {
                    if (this.dateFrom || this.dateTo) return;
                    const q = this.outgoingSearch();
                    if (q !== this._lastSearch) this.loadLists(true);
                }, 400);
            });

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

            // Auto refresh: per-viewer choice, remembered in this browser.
            this.autoRefresh = store.get(autoRefreshKey) === '1';
            this.startAutoRefresh();
            document.addEventListener('visibilitychange', () => {
                if (!document.hidden && this.autoRefresh && this.canAutoRefresh()) this.loadLists(true);
            });
        },

        // ── Auto refresh ──────────────────────────────────────────────────
        toggleAutoRefresh() {
            this.autoRefresh = !this.autoRefresh;
            store.set(autoRefreshKey, this.autoRefresh ? '1' : '0');
            this.startAutoRefresh();
            if (this.autoRefresh) this.loadLists(true);
        },

        startAutoRefresh() {
            if (this._autoTimer) { clearInterval(this._autoTimer); this._autoTimer = null; }
            if (!this.autoRefresh) return;
            this._autoTimer = setInterval(() => {
                if (!document.hidden && this.canAutoRefresh()) this.loadLists(true);
            }, this.autoRefreshSeconds * 1000);
        },

        // Skip a tick while the page is busy or the user is mid-form, so nothing
        // they are typing or sending is disturbed.
        canAutoRefresh() {
            return !!this.office && !this.listsLoading && !this.manual.open && !this.edit.open
                && !this.send.row && !this.batch.running;
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
            if (this.batch.running) return;
            this.mode = mode;
            // A receive batch and a send batch hold different files.
            if (this.batch.on) this.batch = blankBatch(true);
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
                const err = new Error(data.message || errors || ('Request failed (' + res.status + ')'));
                err.data = data;
                throw err;
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
        // `silent`: an automatic refresh, so a failure is not shown as a pop-up every tick.
        async loadLists(silent = false) {
            if (!this.office) return;
            this.listsLoading = true;
            try {
                const params = { office: this.office, days: this.days };
                if (this.dateFrom) params.from = this.dateFrom;
                if (this.dateTo) params.to = this.dateTo;
                const q = this.dateFrom || this.dateTo ? '' : this.outgoingSearch();
                if (q) params.q = q;
                this._lastSearch = q;
                const data = await this.request(this.urls.lists + '?' + new URLSearchParams(params));
                const pending = (data.pending || []).map(r => ({ ...r, bucket: 'pending' }));
                const held = (data.held || []).map(r => ({ ...r, bucket: 'held' }));
                // Memo: the letters, memos and other non-files at this office, waiting or held.
                const memo = [...pending, ...held].filter(r => r.file_type === 'NON_FILE');
                this.lists = { pending, held, sent: data.sent || [], memo };
                this.lastRefreshed = new Date();
            } catch (e) {
                if (silent) console.warn('[File Movement] auto refresh failed:', e.message);
                else this.notify('error', e.message);
            } finally {
                this.listsLoading = false;
            }
        },

        isToday(value) {
            if (!value) return false;
            const d = new Date(String(value).replace(' ', 'T'));
            return !isNaN(d.getTime()) && d.toDateString() === new Date().toDateString();
        },

        // Each card counts files and memos together, and says how many of each,
        // so it adds up with the Files and Mails tabs.
        cards() {
            const periods = { 1: 'today', 7: 'in the last 7 days', 30: 'in the last 30 days', 90: 'in the last 90 days' };
            if (this.dateFrom || this.dateTo) periods[this.days] = this.rangeLabel().toLowerCase();
            // Incoming cards open the matching inner tab (Yet to Receive / Received).
            const buckets = { pending: 'pending', held: 'held', today: 'held' };
            const card = (key, sub, label, rows, icon, text, bg, hint) => {
                const memo = rows.filter(r => r.file_type === 'NON_FILE').length;
                return { key, tab: 'held', sub, bucket: buckets[key] || null, label, value: rows.length, files: rows.length - memo, memo, icon, text, bg, hint };
            };
            return [
                card('pending', 'incoming', 'Pending Receipt', this.lists.pending, 'inbox', 'text-yellow-600', 'bg-yellow-100', 'Sent to this office, not yet received'),
                card('held', 'incoming', 'Held Here', this.lists.held, 'folder-open', 'text-green-600', 'bg-green-100', 'Received and not yet sent on'),
                card('today', 'incoming', 'Received Today', this.lists.held.filter(r => this.isToday(r.since)), 'calendar-check', 'text-blue-600', 'bg-blue-100', 'Still held, received today'),
                card('transit', 'outgoing', 'In Transit', this.lists.sent.filter(r => r.state === 'In transit'), 'truck', 'text-orange-600', 'bg-orange-100', 'Sent out, awaiting receipt'),
                card('sent', 'outgoing', 'Outgoing', this.lists.sent, 'send', 'text-indigo-600', 'bg-indigo-100', 'Sent out ' + (periods[this.days] || '')),
            ];
        },

        // Rows for a tab (held = regular files, memo = Not a regular File/Mail) and
        // sub-tab. Incoming mixes files waiting for receipt (Receive) with files held
        // here (Send); each row's bucket says which.
        tabRows(tab, sub) {
            const isMemo = (r) => r.file_type === 'NON_FILE';
            const want = tab === 'memo' ? isMemo : (r) => !isMemo(r);
            if (sub === 'outgoing') return (this.lists.sent || []).filter(want);
            return [...(this.lists.pending || []), ...(this.lists.held || [])].filter(want);
        },

        // 'incoming' or 'sent' (Outgoing).
        view() {
            return this.sub === 'outgoing' ? 'sent' : 'incoming';
        },

        tabCount(key) {
            // The tab's total: Incoming + Outgoing (each sub-tab shows its own count); follows the Filter box.
            return this.subCount(key, 'incoming') + this.subCount(key, 'outgoing');
        },
        subCount(tab, sub) {
            return this.searched(this.tabRows(tab, sub)).length;
        },

        // Incoming inner-tab count (Yet to Receive / Received); follows the Filter box like subCount().
        incomingCount(tab, bucket) {
            return this.searched(this.tabRows(tab, 'incoming')).filter(r => this.rowBucket(r) === bucket).length;
        },

        openCard(card) {
            if (!card.tab) return;
            this.tab = card.tab;
            this.sub = card.sub || 'incoming';
            if (card.bucket) this.incomingView = card.bucket;
        },
        cardActive(card) {
            return !!card.tab && this.tab === card.tab && this.sub === (card.sub || 'incoming')
                && (!card.bucket || this.sub !== 'incoming' || this.incomingView === card.bucket);
        },

        visibleRows() {
            let rows = this.inDateRange(this.searched(this.tabRows(this.tab, this.sub)));
            if (this.view() === 'incoming') rows = rows.filter(r => this.rowBucket(r) === this.incomingView);
            return this.tab === 'memo' && this.memoFilter ? rows.filter(r => this.memoMatches(r, this.memoFilter)) : rows;
        },

        // ── Pagination ────────────────────────────────────────────────────
        pageCount() {
            return Math.max(1, Math.ceil(this.visibleRows().length / this.perPage));
        },
        // Clamped, so a refresh that shrinks the list never leaves an empty page.
        currentPage() {
            return Math.min(Math.max(1, this.page), this.pageCount());
        },
        pageOffset() {
            return (this.currentPage() - 1) * this.perPage;
        },
        pagedRows() {
            return this.visibleRows().slice(this.pageOffset(), this.pageOffset() + this.perPage);
        },
        goToPage(n) {
            this.page = Math.min(Math.max(1, n), this.pageCount());
        },
        // First, last, and up to two either side of the current page; null marks a gap.
        pageNumbers() {
            const total = this.pageCount();
            const cur = this.currentPage();
            const out = [];
            for (let n = 1; n <= total; n++) {
                if (n === 1 || n === total || Math.abs(n - cur) <= 2) {
                    out.push(n);
                } else if (out[out.length - 1] !== null) {
                    out.push(null);
                }
            }
            return out;
        },

        // ── Date range ────────────────────────────────────────────────────
        // A row's date is `since` (received / sent / logged); compared as Y-m-d.
        rowDay(r) {
            const v = String(r.since || '').trim();
            if (!v) return '';
            const m = v.match(/^(\d{4})-(\d{2})-(\d{2})/);
            if (m) return m[0];
            const d = new Date(v);
            if (isNaN(d.getTime())) return '';
            const pad = (n) => String(n).padStart(2, '0');
            return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
        },
        inDateRange(rows) {
            if (!this.dateFrom && !this.dateTo) return rows;
            return rows.filter(r => {
                const day = this.rowDay(r);
                if (!day) return false;
                return (!this.dateFrom || day >= this.dateFrom) && (!this.dateTo || day <= this.dateTo);
            });
        },
        onDateRangeChange() {
            if (this.dateFrom && this.dateTo && this.dateFrom > this.dateTo) {
                [this.dateFrom, this.dateTo] = [this.dateTo, this.dateFrom];
            }
            this.loadLists();
        },
        clearDateRange() {
            this.dateFrom = '';
            this.dateTo = '';
            this.loadLists();
        },
        rangeLabel() {
            const f = (v) => {
                const [y, m, d] = v.split('-');
                return d + '/' + m + '/' + y;
            };
            if (this.dateFrom && this.dateTo) return this.dateFrom === this.dateTo ? 'On ' + f(this.dateFrom) : 'From ' + f(this.dateFrom) + ' to ' + f(this.dateTo);
            if (this.dateFrom) return 'From ' + f(this.dateFrom);
            if (this.dateTo) return 'Up to ' + f(this.dateTo);
            return '';
        },

        // The Filter box text sent to the server for the all-time Outgoing search
        // (3+ characters, so a stray letter does not pull every old send).
        outgoingSearch() {
            const q = String(this.filter || '').trim();
            return q.length >= 3 ? q.slice(0, 100) : '';
        },

        // The Filter box: case-insensitive, over everything the row shows (and its ids), on every
        // tab and sub-tab. Several words must all match, in any column.
        searched(rows) {
            const words = this.filter.trim().toLowerCase().split(/\s+/).filter(Boolean);
            if (!words.length) return rows;
            return rows.filter(r => {
                const text = this.rowSearchText(r);
                return words.every(w => text.includes(w));
            });
        },
        // Export what the register shows now (tab, sub-tab, Filter box, Memo type, date range)
        // as a CSV that opens in Excel (UTF-8 with BOM), or as a PDF.
        exportData() {
            const rows = this.visibleRows();
            const sent = this.view() === 'sent';
            // No Notes, Reference, Tracking ID or Log ID in the export (user, 2026-10-16).
            // To: office before officer, as the table shows it. Purpose follows Status, and the
            // sending / receiving officers sign after From / To (Office) on the PDF (user, 2026-10-22).
            const head = ['S/N', 'File Number', 'Title', 'Type', 'Status', 'Purpose', 'From', SIGN_SENDER, 'To (Office)', SIGN_RECEIVER,
                'To (Officer)', sent ? 'Sent' : 'Date', 'Sender', 'Sender Name'];
            const status = (r) => sent ? (r.state || '')
                : [r.bucket === 'pending' ? 'Awaiting receipt' : 'Held here', r.legacy ? 'Legacy log' : '', r.not_indexed ? 'Not indexed' : '',
                   r.request_type === 'SUBMITTED' ? 'Submitted Request' : ''].filter(Boolean).join('; ');
            const type = (r) => r.file_type === 'NON_FILE' ? (r.document_kind ? this.kindText(r.document_kind, r.mail_type) : 'Mail') : 'File';
            const body = rows.map((r, i) => [
                i + 1, r.file_number || r.related_file_number || '', String(r.file_title || '').trim().toUpperCase(), type(r), status(r),
                nice(r.purpose) || '', nice(r.from) || '', '', nice(r.to) || '', '', nice(r.to_officer) || '', r.since ? this.fmt(r.since) : '',
                [r.sender_type && r.sender_type !== 'Other' ? r.sender_type : '', r.sender || ''].filter(Boolean).join(' — '),
                [nice(r.sender_name) || '', r.sender_rank ? '(' + r.sender_rank + ')' : ''].filter(Boolean).join(' '),
            ]);
            const tabName = this.tab === 'memo' ? 'memo' : 'held';
            const stamp = new Date();
            const pad = (n) => String(n).padStart(2, '0');
            const fileBase = ['file-movement', this.office || 'office', tabName, this.sub,
                stamp.getFullYear() + '-' + pad(stamp.getMonth() + 1) + '-' + pad(stamp.getDate()) + '_' + pad(stamp.getHours()) + pad(stamp.getMinutes())].join('_');
            const officeRow = (this.offices || []).find(o => o.office_code === this.office) || {};
            const officeName = officeRow.office_name || this.office || '';
            const tabLabel = (this.tab === 'memo' ? 'Mails' : 'Files') + ' — '
                + (this.sub === 'outgoing' ? 'Outgoing' : 'Incoming — ' + (this.incomingView === 'held' ? 'Received' : 'Yet to Receive'));
            const period = this.rangeLabel() || (sent ? ({ 1: 'Today', 7: 'Last 7 days', 30: 'Last 30 days', 90: 'Last 90 days' }[this.days] || '') : '');
            return { rows, head, body, fileBase, officeName, tabLabel, period, stamp };
        },

        download(blob, name) {
            const a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = name;
            document.body.appendChild(a);
            a.click();
            setTimeout(() => { URL.revokeObjectURL(a.href); a.remove(); }, 0);
        },

        exportRows(format = 'csv') {
            this.exportOpen = false;
            if (!this.visibleRows().length) { this.notify('info', 'Nothing to export.'); return; }
            return format === 'pdf' ? this.exportPdf() : this.exportCsv();
        },

        exportCsv() {
            const data = this.exportData();
            const { rows, fileBase } = data;
            // The signature columns are for the printed page only.
            const keep = data.head.map((h, i) => i).filter(i => ![SIGN_SENDER, SIGN_RECEIVER].includes(data.head[i]));
            const head = keep.map(i => data.head[i]);
            const body = data.body.map(line => keep.map(i => line[i]));
            // Quote every cell; a leading = + - @ is prefixed so Excel does not run it as a formula.
            const cell = (v) => {
                let t = String(v ?? '');
                if (/^[=+\-@]/.test(t)) t = "'" + t;
                return '"' + t.replace(/"/g, '""') + '"';
            };
            const csv = [head, ...body].map(line => line.map(cell).join(',')).join('\r\n');
            this.download(new Blob([String.fromCharCode(0xFEFF) + csv], { type: 'text/csv;charset=utf-8;' }), fileBase + '.csv');
            this.notify('success', 'Exported ' + rows.length + ' row' + (rows.length === 1 ? '' : 's') + '.');
        },

        // jsPDF + autotable, loaded on first use (the same CDN builds other KLAES pages use).
        loadPdfLibs() {
            const ready = () => window.jspdf && window.jspdf.jsPDF;
            if (ready() && window.jspdf.jsPDF.API.autoTable) return Promise.resolve();
            const load = (src) => new Promise((resolve, reject) => {
                const s = document.createElement('script');
                s.src = src;
                s.onload = resolve;
                s.onerror = () => reject(new Error('Could not load the PDF library. Check the connection and try again.'));
                document.head.appendChild(s);
            });
            const base = ready() ? Promise.resolve() : load('https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js');
            return base.then(() => window.jspdf.jsPDF.API.autoTable ? null
                : load('https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.7.1/jspdf.plugin.autotable.min.js'));
        },

        async exportPdf() {
            const { rows, head, body, fileBase, officeName, tabLabel, period, stamp } = this.exportData();
            try {
                await this.loadPdfLibs();
            } catch (e) {
                this.notify('error', e.message);
                return;
            }
            // The PDF leaves out the system ids so the table fits a landscape page; the CSV keeps them.
            const keep = head.map((h, i) => i).filter(i => !['Sender Name'].includes(head[i]));
            const doc = new window.jspdf.jsPDF({ orientation: 'landscape', unit: 'pt', format: 'a4' });
            const pageW = doc.internal.pageSize.getWidth();
            doc.setFontSize(13);
            doc.setFont(undefined, 'bold');
            doc.text('File Movement Register', 30, 32);
            doc.setFont(undefined, 'normal');
            doc.setFontSize(9);
            doc.text([officeName, tabLabel, period].filter(Boolean).join('   |   '), 30, 48);
            doc.text('Generated ' + stamp.toLocaleString() + '   |   ' + rows.length + ' record' + (rows.length === 1 ? '' : 's'), pageW - 30, 48, { align: 'right' });
            doc.autoTable({
                head: [keep.map(i => head[i])],
                body: body.map(line => keep.map(i => String(line[i] ?? ''))),
                startY: 60,
                margin: { left: 30, right: 30, bottom: 30 },
                styles: { fontSize: 7, cellPadding: 3, overflow: 'linebreak', valign: 'top' },
                headStyles: { fillColor: [79, 70, 229], textColor: 255, fontStyle: 'bold' },
                alternateRowStyles: { fillColor: [245, 247, 250] },
                // Signature columns: wide and tall enough to sign in.
                columnStyles: keep.reduce((styles, col, pos) => {
                    if ([SIGN_SENDER, SIGN_RECEIVER].includes(head[col])) styles[pos] = { cellWidth: 62, minCellHeight: 24 };
                    return styles;
                }, { 0: { cellWidth: 26, halign: 'right' } }),
                didDrawPage: () => {
                    const h = doc.internal.pageSize.getHeight();
                    doc.setFontSize(8);
                    doc.text('Page ' + doc.internal.getNumberOfPages(), pageW - 30, h - 14, { align: 'right' });
                },
            });
            doc.save(fileBase + '.pdf');
            this.notify('success', 'Exported ' + rows.length + ' row' + (rows.length === 1 ? '' : 's') + ' to PDF.');
        },

        rowSearchText(r) {
            const bucket = r.bucket === 'pending' ? 'awaiting receipt' : (r.bucket === 'held' ? 'held here' : '');
            return [
                r.file_number, r.related_file_number, r.file_title, r.tracking_id, r.log_id,
                r.file_type === 'NON_FILE' ? 'not a regular mail letter memo file' : '',
                this.kindText(r.document_kind, r.mail_type), r.mail_type,
                r.not_indexed ? 'not indexed' : '', r.legacy ? 'legacy log' : '', r.request_type === 'SUBMITTED' ? 'submitted request' : '',
                r.from, r.to, r.to_officer, r.now_at, r.state, bucket,
                r.since, r.since ? this.fmt(r.since) : '',
                r.purpose, r.notes, r.reference, r.sender, r.sender_name, r.sender_rank,
            ].filter(v => v !== null && v !== undefined && v !== '').map(v => nice(String(v))).join(' | ').toLowerCase();
        },

        memoMatches(r, want) {
            return r.document_kind === want;
        },
        memoCount(want) {
            return this.searched(this.tabRows('memo', this.sub)).filter(r => !want || this.memoMatches(r, want)).length;
        },

        // ── Correct a "Not a regular File/Mail" entry: Mail/File and mail type ──
        canEditDetails() {
            const d = this.profile.data;
            return !!(this.canEditLogs && d && d.file.non_file && d.tracker);
        },
        async editDetails() {
            const d = this.profile.data;
            if (!this.canEditDetails() || !window.Swal) return;
            const esc = (v) => String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
            const kind = d.file.document_kind || '';
            const current = kind === 'other' ? (d.file.mail_type || '') : '';
            const field = 'width:100%;border:1px solid #d1d5db;border-radius:8px;padding:8px 10px;font-size:14px;margin-top:4px;';
            const label = 'display:block;font-size:12px;font-weight:600;color:#4b5563;margin-top:12px;';
            const html = '<div style="text-align:left">'
                + '<label style="' + label + 'margin-top:0;">Title / subject *</label><input id="ed-title" maxlength="255" style="' + field + '" value="' + esc(d.file.file_title || '') + '">'
                + '<label style="' + label + '">What is it? *</label><select id="ed-kind" style="' + field + '"><option value="">— Select —</option>'
                + this.documentKinds.map(k => '<option value="' + k.value + '"' + (k.value === kind ? ' selected' : '') + '>' + k.label + (k.value === 'other' ? ' (specify)…' : '') + '</option>').join('') + '</select>'
                + '<input id="ed-type-other" maxlength="100" placeholder="Specify what it is *" style="' + field + '" value="' + esc(current) + '">'
                + '<label style="' + label + '">Reference no.</label><input id="ed-ref" maxlength="255" style="' + field + '" value="' + esc(d.file.reference || '') + '">'
                + '<label style="' + label + '">File number <span style="font-weight:400;color:#9ca3af">(optional — if the document relates to a file)</span></label>'
                + '<input id="ed-related" maxlength="255" style="' + field + '" value="' + esc(d.file.related_file_number || '') + '">'
                + '<label style="' + label + '">Reason for the change *</label><textarea id="ed-reason" rows="2" maxlength="500" style="' + field + '" placeholder="Why is this being corrected?"></textarea>'
                + '</div>';
            const ask = await Swal.fire({
                title: 'Edit details', html, showCancelButton: true, confirmButtonText: 'Save', cancelButtonText: 'Cancel',
                confirmButtonColor: '#2563eb', cancelButtonColor: '#6b7280', reverseButtons: true, focusConfirm: false,
                didOpen: (el) => {
                    const k = el.querySelector('#ed-kind'), o = el.querySelector('#ed-type-other');
                    const sync = () => { o.style.display = k.value === 'other' ? '' : 'none'; };
                    k.addEventListener('change', sync); sync();
                },
                preConfirm: () => {
                    const el = Swal.getPopup();
                    const v = (id) => String(el.querySelector('#' + id).value || '').trim();
                    const k = v('ed-kind');
                    if (!k) { Swal.showValidationMessage('Choose Mail, Letter, Memo, File or Other.'); return false; }
                    const type = k === 'other' ? v('ed-type-other') : null;
                    if (k === 'other' && !type) { Swal.showValidationMessage('Specify what it is.'); return false; }
                    if (!v('ed-title')) { Swal.showValidationMessage('Give the title / subject.'); return false; }
                    if (!v('ed-reason')) { Swal.showValidationMessage('Give a reason for the change.'); return false; }
                    return { document_kind: k, mail_type: type, file_title: v('ed-title'), reference: v('ed-ref') || null,
                        related_file_number: v('ed-related') || null, reason: v('ed-reason') };
                },
            });
            if (!ask.isConfirmed) return;
            this.busy = true;
            try {
                const data = await this.request(this.urls.editDetails, { method: 'POST', body: JSON.stringify({ tracker_id: d.tracker.id, ...ask.value }) });
                this.notify('success', data.message);
                await this.reloadProfile();
                await this.loadLists();
            } catch (e) {
                this.notify('error', e.message);
            } finally {
                this.busy = false;
            }
        },

        fmt(value) {
            if (!value && value !== 0) return '—';
            const d = typeof value === 'number' ? new Date(value) : new Date(String(value).replace(' ', 'T'));
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
                if (this.batch.on) {
                    if (this.scan.matches.length === 1) {
                        this.addToBatch(this.scan.matches[0], 'scan');
                        this.scan = blankScan();
                    }
                    return;
                }
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
            if (this.batch.on) {
                this.addToBatch(m, 'scan');
                this.scan = blankScan();
                this.focusScan();
                return;
            }
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
            // A legacy hand-over to this office (logged before File Movement) counts as held.
            return !!(t && ((t.current_code === this.office && t.last_status === 'active') || t.legacy_held_at === this.office));
        },

        // Which register a row belongs to; the Memo tab mixes waiting and held ones.
        rowBucket(row) {
            return row.bucket || this.view();
        },

        // "Mail · Letter", "File".
        // Entry-type card: its colour border and tint when picked, a light tint otherwise.
        entryCardStyle(opt, picked) {
            return picked
                ? 'border-color:' + opt.solid + ';background:' + opt.tint + ';box-shadow:0 0 0 3px ' + opt.border + ';'
                : 'border-color:' + opt.border + ';background:' + opt.bg + ';';
        },

        // Badge colours for a kind; unknown kinds stay grey.
        kindStyle(kind) {
            const k = this.documentKinds.find(i => i.value === kind);
            return k ? 'background:' + k.bg + ';color:' + k.text + ';border:1px solid ' + k.border + ';' : 'background:#f3f4f6;color:#374151;';
        },
        // The choice pill: solid when picked, tinted otherwise.
        kindPillStyle(k, picked) {
            return picked
                ? 'background:' + k.solid + ';color:#fff;border-color:' + k.solid + ';'
                : 'background:' + k.bg + ';color:' + k.text + ';border-color:' + k.border + ';';
        },

        // "Memo"; for Other, the text typed ("Circular").
        kindText(kind, other) {
            return kind === 'other' && other ? nice(other) : this.docKindLabel(kind);
        },

        docKindLabel(kind) {
            const k = this.documentKinds.find(i => i.value === kind);
            return k ? k.label : '';
        },

        // A new sender type starts with nothing chosen or typed.
        senderTypeChanged() {
            // Leaving Internal: the department it gave is no longer chosen, so ask again.
            if (this.manual.sender_type !== 'Internal' && this.manual.from_synced) {
                this.manual.from_department = '';
                this.fromDepartmentChanged();
            }
            this.manual.from_synced = this.manual.sender_type === 'Internal';
            this.manual.sender = '';
            this.manual.sender_mda = '';
            this.manual.sender_lga = '';
            this.manual.sender_dept = '';
            this.manual.sender_person = '';
            this.manual.sender_name = '';
            this.manual.sender_rank = '';
            this.manual.sender_rank_other = '';
            if (this.manual.sender_type === 'Internal') this.selfAsSender();
        },

        // Internal sender: the logged-in user by default, with their office's department
        // and their account's rank. Everything stays editable.
        selfAsSender() {
            const m = this.manual;
            const mine = this.offices.find(o => o.office_code === this.office);
            if (mine && mine.department) m.sender_dept = mine.department;
            const me = this.officers.find(o => String(o.id) === String(this.userId));
            if (me) {
                m.sender_person = String(me.id);
                this.pickSenderPerson();
            } else if (this.userName) {
                m.sender_person = 'other';
                m.sender_name = this.userName;
            }
            this.syncFromDepartment();
        },

        // Internal sender names: staff of the chosen department first, then everyone.
        senderPeople() {
            const dept = String(this.manual.sender_dept || '').toLowerCase().replace(/\s*department\s*$/, '').trim();
            if (!dept || dept === 'other') return this.officers;
            const inDept = this.officers.filter(o => {
                const d = String(o.department || '').toLowerCase();
                return d && (d === dept || d.includes(dept) || dept.includes(d));
            });
            const list = inDept.length ? inDept : this.officers;
            // The person already chosen (e.g. the logged-in user) always stays in the list.
            const picked = this.officers.find(o => String(o.id) === String(this.manual.sender_person));
            return picked && !list.includes(picked) ? [picked, ...list] : list;
        },
        // Picking a person fills their rank from their account when it is a listed rank.
        pickSenderPerson() {
            const m = this.manual;
            m.sender_name = '';
            m.sender_rank = '';
            m.sender_rank_other = '';
            const o = this.officers.find(i => String(i.id) === String(m.sender_person));
            if (!o || !o.rank) return;
            const r = this.officerRanks.find(x => x.toLowerCase() === String(o.rank).toLowerCase());
            if (r) m.sender_rank = r;
            else { m.sender_rank = 'other'; m.sender_rank_other = o.rank; }
        },
        senderNameValue() {
            const m = this.manual;
            if (m.sender_type === 'Internal') {
                if (m.sender_person === 'other') return m.sender_name.trim();
                const o = this.officers.find(i => String(i.id) === String(m.sender_person));
                return o ? nice(o.name) : '';
            }
            return m.sender_name.trim();
        },

        // KLAES departments, as the offices table groups them.
        departments() {
            return this.officeGroups().map(g => g.department);
        },
        // Offices other than mine, narrowed to one department when one is chosen.
        officesIn(dept) {
            return this.offices.filter(o => o.office_code !== this.office && (!dept || (o.department || 'Other') === dept));
        },
        // An Internal sender's department is usually where it was received from too.
        pickSenderDept() {
            this.manual.sender = '';
            this.syncFromDepartment();
        },
        // An Internal sender's department is where it was received from: one dropdown,
        // not two. The office list follows it. Other (typed department) means an office typed too.
        // An External sender (MDA / LGA / other organisation) is itself where it came from.
        senderIsExternal() {
            return this.manual.entry_type === 'non_file' && this.manual.sender_type === 'External';
        },
        senderGivesDepartment() {
            return this.manual.entry_type === 'non_file' && this.manual.sender_type === 'Internal';
        },
        syncFromDepartment() {
            if (!this.senderGivesDepartment()) return;
            const d = this.manual.sender_dept;
            const from = d === 'other' ? 'other' : (d || '');
            if (this.manual.from_department !== from) {
                this.manual.from_department = from;
                this.fromDepartmentChanged();
            }
        },
        // A new department clears the office; outside KLAES there is no office to pick, so Other is typed.
        fromDepartmentChanged() {
            this.manual.from_other = '';
            this.manual.from_office = this.manual.from_department === 'other' ? 'other' : '';
        },

        senderLabel(type, name, person, rank) {
            const who = [nice(person) || '', rank ? '(' + rank + ')' : ''].filter(Boolean).join(' ');
            return [type && type !== 'Other' ? type : '', nice(name) || '', who].filter(Boolean).join(' — ');
        },

        // Date and time stamps for the file overview: first logged, and the latest movement.
        profileStamps() {
            const d = this.profile.data;
            if (!d || !d.tracker) return [];
            const out = [];
            if (d.tracker.created_at) out.push({ label: 'First logged', value: this.fmt(d.tracker.created_at) });
            const events = this.historyEvents().filter(e => e.ts !== null && e.ts !== undefined && isFinite(e.ts));
            const last = events.length ? events[events.length - 1] : null;
            if (last) out.push({ label: 'Last movement', value: this.fmt(last.ts), note: last.label });
            else if (d.tracker.last_at) out.push({ label: 'Last movement', value: this.fmt(d.tracker.last_at) });
            return out;
        },

        canReceive() {
            const d = this.profile.data;
            if (!d || this.profile.loading) return false;
            if (!d.tracker) return !!d.file.file_number;
            if (this.sentFromHere()) return false;
            return !this.heldHere();
        },

        // This office sent it on and it is on its way to another office: nothing to receive here.
        sentFromHere() {
            const t = this.profile.data && this.profile.data.tracker;
            if (!t || t.last_status !== 'pending_acceptance' || !t.pending_to || t.pending_to === this.office) return false;
            return t.sent_from ? t.sent_from === this.office : t.current_code === this.office;
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
            if (d.file.non_file) {
                b.push({ label: 'Not a regular Mail/Letter/Memo/File', cls: 'bg-gray-100 text-gray-700' });
                if (d.file.document_kind) b.push({ label: this.kindText(d.file.document_kind, d.file.mail_type), style: this.kindStyle(d.file.document_kind) });
            }
            else b.push(d.file.indexed ? { label: 'Indexed', cls: 'bg-green-100 text-green-700' } : { label: 'Not indexed', cls: 'bg-orange-100 text-orange-700' });
            if (d.tracker && d.tracker.request_type === 'SUBMITTED') b.push({ label: 'Submitted Request', cls: 'bg-purple-100 text-purple-700' });
            if (d.file.decommissioned) b.push({ label: 'Decommissioned' + (d.file.successor ? ' → ' + d.file.successor : ''), cls: 'bg-red-100 text-red-700' });
            if (this.heldHere()) b.push({ label: 'Held at your office', cls: 'bg-emerald-100 text-emerald-700' });
            if (d.tracker && d.tracker.legacy_held_at === this.office) b.push({ label: 'Legacy log', cls: 'bg-gray-100 text-gray-700' });
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
                { label: 'Document type', value: this.kindText(f.document_kind, f.mail_type) },
                { label: 'Sender', value: this.senderLabel(f.sender_type, f.sender, f.sender_name, f.sender_rank) },
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
                    entry: e,
                    office,
                    officer: this.qsOfficer(e) !== '-' ? this.qsOfficer(e) : '',
                    purpose: purposeOf(e),
                    delay: e.delay_reason || '',
                    logId: e.log_id || '',
                    notes: e.notes || '',
                };

                if (base.key === 'other') {
                    const ts = inTs ?? outTs ?? created(e);
                    events.push({ ...common, ...INDIGO, ...{ style: base.style }, label: base.label, timeLabel: 'Date', time: stamp(ts), ts, primary: true });
                    return;
                }
                if (base.key === 'transit') {
                    const ts = created(e) ?? outTs;
                    const t = this.profile.data && this.profile.data.tracker;
                    if (latest && t && t.legacy_held_at && t.legacy_held_at === (e.receiving_office_code || e.office_code)) {
                        events.push({ ...common, ...GREEN, label: 'Held (legacy log)', timeLabel: 'Sent', time: stamp(ts), ts, primary: true,
                            notes: 'Logged before File Movement and never received; counted as held at this office.' + (common.notes ? ' ' + common.notes : '') });
                        return;
                    }
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
            return this.timelineBadge(this.historyMeta());
        },
        // Green/Amber/Red timeline badge from {timeline_status, days_until_deadline}:
        // the profile's history meta, or a register row (the tab tables).
        timelineBadge(meta) {
            meta = meta || {};
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
        // A row's Expected Return Date (Y-m-d) as DD/MM/YYYY.
        dueDate(value) {
            const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(value || ''));
            return m ? m[3] + '/' + m[2] + '/' + m[1] : '';
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
            }, null, { purpose: this.historyMeta().request_purpose_name, label: d.file.file_number || d.file.file_title });
        },

        async receivePending(row) {
            await this.receive({
                entry_type: row.file_type === 'NON_FILE' ? 'non_file' : 'file',
                tracker_id: row.id,
                file_number: row.file_number,
                file_title: row.file_title,
                received_via: 'manual',
            }, null, { purpose: row.purpose, officer: row.to_officer, label: row.file_number || row.file_title });
        },

        // Files logged before the request purpose / receiving officer existed have
        // neither, and the server asks for them (`needs`). A small card collects
        // them and the receipt is sent again. Resolves to the extra fields, or null
        // when cancelled. With `forBatch`, offers to reuse the answer for the rest.
        async askReceiptDetails(needs, hint = {}, forBatch = false) {
            if (!window.Swal) return null;
            const esc = (v) => String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
            const lower = (v) => String(v || '').trim().toLowerCase();
            const wantPurpose = needs.includes('purpose');
            const wantOfficer = needs.includes('officer');
            const hintPurpose = lower(hint.purpose) === 'received' ? '' : String(hint.purpose || '').trim();
            const hintOfficer = String(hint.officer || '').trim();
            const pickedPurpose = this.requestPurposes.find(p => hintPurpose && lower(p.name) === lower(hintPurpose));
            // Receiving: the logged-in user is the receiving officer by default.
            const pickedOfficer = this.officers.find(o => String(o.id) === String(this.userId))
                || this.officers.find(o => hintOfficer && lower(o.name) === lower(hintOfficer));
            const field = 'width:100%;border:1px solid #d1d5db;border-radius:8px;padding:8px 10px;font-size:14px;margin-top:4px;';
            const label = 'display:block;font-size:12px;font-weight:600;color:#4b5563;margin-top:12px;';
            let html = '<div style="text-align:left">';
            if (hint.label) html += '<p style="font-size:13px;color:#6b7280">' + esc(hint.label) + '</p>';
            if (wantPurpose) {
                html += '<label style="' + label + '">Request purpose *</label><select id="rd-purpose" style="' + field + '"><option value="">— Select purpose —</option>'
                    + this.requestPurposes.map(p => '<option value="' + esc(p.id) + '"' + (pickedPurpose && pickedPurpose.id === p.id ? ' selected' : '') + '>' + esc(nice(p.name)) + '</option>').join('')
                    + '<option value="other"' + (hintPurpose && !pickedPurpose ? ' selected' : '') + '>Other</option></select>'
                    + '<input id="rd-purpose-other" placeholder="Specify the purpose" style="' + field + 'display:none" value="' + esc(pickedPurpose ? '' : hintPurpose) + '">';
            }
            if (wantOfficer) {
                html += '<label style="' + label + '">Receiving officer *</label><select id="rd-officer" style="' + field + '"><option value="">— Select officer —</option>'
                    + this.officers.map(o => '<option value="' + esc(o.id) + '"' + (pickedOfficer && pickedOfficer.id === o.id ? ' selected' : '') + '>' + esc(nice(o.name) + (o.username ? ' (' + o.username + ')' : '')) + '</option>').join('')
                    + '<option value="other"' + (hintOfficer && !pickedOfficer ? ' selected' : '') + '>Other (not listed)…</option></select>'
                    + '<input id="rd-officer-other" placeholder="Officer\'s name" style="' + field + 'display:none" value="' + esc(pickedOfficer ? '' : hintOfficer) + '">';
            }
            if (forBatch) {
                html += '<label style="display:flex;gap:8px;align-items:center;font-size:13px;color:#374151;margin-top:14px"><input type="checkbox" id="rd-rest" checked> Use for the other files in this batch that need it</label>';
            }
            html += '</div>';

            const ask = await Swal.fire({
                title: 'Details needed to receive',
                html,
                showCancelButton: true,
                confirmButtonText: 'Continue',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#16a34a',
                cancelButtonColor: '#6b7280',
                reverseButtons: true,
                focusConfirm: false,
                didOpen: (el) => {
                    [['rd-purpose', 'rd-purpose-other'], ['rd-officer', 'rd-officer-other']].forEach(([sel, other]) => {
                        const s = el.querySelector('#' + sel);
                        const o = el.querySelector('#' + other);
                        if (!s || !o) return;
                        const sync = () => { o.style.display = s.value === 'other' ? '' : 'none'; };
                        s.addEventListener('change', sync);
                        sync();
                    });
                },
                preConfirm: () => {
                    const el = Swal.getPopup();
                    const val = (id) => { const n = el.querySelector('#' + id); return n ? String(n.value || '').trim() : ''; };
                    const out = {};
                    if (wantPurpose) {
                        const v = val('rd-purpose');
                        if (!v || (v === 'other' && !val('rd-purpose-other'))) { Swal.showValidationMessage('Choose a request purpose, or choose Other and specify it.'); return false; }
                        if (v === 'other') out.request_purpose_other = val('rd-purpose-other'); else out.request_purpose_id = Number(v);
                    }
                    if (wantOfficer) {
                        const v = val('rd-officer');
                        if (!v || (v === 'other' && !val('rd-officer-other'))) { Swal.showValidationMessage('Choose a receiving officer, or choose Other and specify the name.'); return false; }
                        if (v === 'other') out.receiving_officer_other = val('rd-officer-other'); else out.receiving_officer_id = Number(v);
                    }
                    const rest = el.querySelector('#rd-rest');
                    return { fields: out, forRest: !!(rest && rest.checked) };
                },
            });
            return ask.isConfirmed ? ask.value : null;
        },

        async receive(payload, onDone, hint = {}) {
            this.busy = true;
            try {
                let data;
                try {
                    data = await this.request(this.urls.receive, {
                        method: 'POST',
                        body: JSON.stringify({ office: this.office, ...payload }),
                    });
                } catch (e) {
                    const needs = e.data && e.data.needs;
                    if (!needs || !needs.length) throw e;
                    this.busy = false;
                    const extra = await this.askReceiptDetails(needs, hint);
                    if (!extra) return;
                    this.busy = true;
                    data = await this.request(this.urls.receive, {
                        method: 'POST',
                        body: JSON.stringify({ office: this.office, ...payload, ...extra.fields }),
                    });
                }
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
            if (this.mode === 'in') this.selfAsOfficer(this.manual);
        },

        // On Receive the person logged in is the one receiving the file. Someone
        // outside the officer list (non-MLPP staff) goes in by name.
        selfAsOfficer(state) {
            const me = this.officers.find(o => String(o.id) === String(this.userId));
            if (me) {
                state.receiving_officer_id = me.id;
                state.officerFilter = nice(me.name);
            } else {
                state.receiving_officer_id = 'other';
                state.officer_other = this.userName;
                state.officerFilter = this.userName;
            }
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
                this.manual.sender_type = '';
                this.manual.sender_mda = '';
                this.manual.sender_lga = '';
                this.manual.document_kind = '';
                this.manual.kind_other = '';
                this.manual.sender_person = '';
                this.manual.sender_name = '';
                this.manual.sender_rank = '';
                this.manual.sender_rank_other = '';
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
            // A request purpose is optional; "Other" must still be typed.
            const purposeOk = m.request_purpose_id !== 'other' || m.purpose_other.trim() !== '';
            if (!chosen(m.receiving_officer_id, m.officer_other) || !purposeOk) return false;
            // Timeline (Days) is optional; when given, a whole number 0–365 (as on Log a File).
            if (this.manualTimelineDays() === false) return false;
            // Send: where it goes.
            if (this.mode === 'out' && (!m.send_to_office || m.send_to_office === this.office)) return false;
            // Where it came from is required: a department, then an office (or Other, typed).
            if (!this.senderIsExternal() && (!m.from_department || !chosen(m.from_office, m.from_other))) return false;
            if (m.entry_type === 'non_file') {
                if (!m.document_kind) return false;
                if (m.document_kind === 'other' && !m.kind_other.trim()) return false;
                // A chosen sender needs a name; a rank is optional but Other must be typed.
                if (m.sender_type && !this.senderNameValue()) return false;
                if (m.sender_rank === 'other' && !m.sender_rank_other.trim()) return false;
                if (m.sender_type === 'Other' && !m.sender.trim()) return false;
                if (m.sender_type === 'External' && (m.sender_mda === 'lga' ? !m.sender_lga : !chosen(m.sender_mda, m.sender))) return false;
                if (m.sender_type === 'Internal' && !chosen(m.sender_dept, m.sender)) return false;
                return m.file_title.trim() !== '';
            }
            return m.file_number.trim() !== '';
        },

        // Timeline (Days): null when blank, false when not a whole number 0–365.
        manualTimelineDays() {
            const raw = String(this.manual.timeline_days ?? '').trim();
            if (raw === '') return null;
            const n = Number(raw);
            return /^\d+$/.test(raw) && n <= 365 ? n : false;
        },
        // Expected Return Date and the Green/Amber preview it would give, as Log a
        // File shows them (due by the end of the day; 1 day or less left = Due Soon).
        manualTimelinePreview() {
            const days = this.manualTimelineDays();
            if (days === null || days === false) return null;
            const due = new Date();
            due.setDate(due.getDate() + days);
            due.setHours(23, 59, 59, 0);
            const amber = (due.getTime() - Date.now()) / 86400000 <= 1;
            return {
                date: String(due.getDate()).padStart(2, '0') + '/' + String(due.getMonth() + 1).padStart(2, '0') + '/' + due.getFullYear(),
                days: days === 0 ? 'Due today' : days + ' day' + (days === 1 ? '' : 's') + ' from today',
                label: amber ? 'Due Soon' : 'On Track',
                style: amber ? 'background:#fef9c3;color:#78350f;border:1px solid #fde68a;' : 'background:#d1fae5;color:#166534;border:1px solid #a7f3d0;',
            };
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
                timeline_days: typeof this.manualTimelineDays() === 'number' ? this.manualTimelineDays() : null,
                from_office_other: this.senderIsExternal() ? null : typed(m.from_office, m.from_other),
                file_title: m.file_title,
                document_kind: m.entry_type === 'non_file' ? m.document_kind : null,
                // The mail_type column holds the text typed for an "Other" kind.
                mail_type: m.entry_type === 'non_file' && m.document_kind === 'other' ? m.kind_other.trim() : null,
                sender_name: m.entry_type === 'non_file' && m.sender_type ? this.senderNameValue() : null,
                sender_rank: m.entry_type === 'non_file' && m.sender_type ? (listed(m.sender_rank) || typed(m.sender_rank, m.sender_rank_other)) : null,
                sender_type: m.entry_type === 'non_file' ? (m.sender_type || null) : null,
                // External: the listed MDA, or the one typed under Other.
                // External: the listed MDA; Internal: the department; either may be typed under Other.
                sender: m.entry_type !== 'non_file' ? null
                    : (m.sender_type === 'External' ? (m.sender_mda === 'lga' ? (m.sender_lga ? m.sender_lga + ' Local Government' : null) : (listed(m.sender_mda) || typed(m.sender_mda, m.sender)))
                        : (m.sender_type === 'Internal' ? (listed(m.sender_dept) || typed(m.sender_dept, m.sender)) : m.sender)),
                sender_lga: m.entry_type === 'non_file' && m.sender_type === 'External' && m.sender_mda === 'lga' ? (m.sender_lga || null) : null,
                // Internal sender with a typed department: that department; otherwise Other is outside KLAES.
                send_to_office: this.mode === 'out' ? m.send_to_office : null,
                from_department: this.senderIsExternal() ? null : m.from_department === 'other'
                    ? (this.senderGivesDepartment() && m.sender_dept === 'other' && m.sender.trim() ? m.sender.trim() : 'Outside KLAES')
                    : (m.from_department || null),
                manual_form: true,
                reference: m.reference,
                from_office: this.senderIsExternal() ? null : listed(m.from_office),
                notes: m.notes,
                received_via: 'manual',
            }, (data) => {
                // In Send mode the file is logged at this office first, then the
                // send form opens so it can go straight on to the next office.
                // Send mode has already sent it on (one step); the profile just shows where it went.
                this.manual = blankManual();
                if (data.action === 'sent') this.sub = 'outgoing';
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
        // Receive: the officer here. Send: the officer at the office it goes to, that office's people first.
        manualOfficerGroups() {
            return this.groupOfficers(this.manual, this.mode === 'out' ? (this.manual.send_to_office || this.office) : this.office);
        },
        pickManualOfficer(o) {
            // Send: pre-fill "Send to" with the office the officer's account maps to (editable).
            if (this.mode === 'out' && !this.manual.send_to_office && o.office_code && o.office_code !== this.office) {
                this.manual.send_to_office = o.office_code;
            }
            this.manual.receiving_officer_id = o.id;
            this.manual.officerFilter = nice(o.name);
            this.manual.officerOpen = false;
        },
        // Officers who can receive a file sent from here: everyone but the sender,
        // since a file cannot be sent to yourself.
        sendOfficers() {
            return this.officers.filter(o => String(o.id) !== String(this.userId));
        },
        groupOfficers(state, officeCode) {
            const f = state.receiving_officer_id ? '' : (state.officerFilter || '').trim().toLowerCase();
            const all = this.sendOfficers();
            const list = f ? all.filter(o => (String(o.name) + ' ' + String(o.username || '')).toLowerCase().includes(f)) : all;
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
            // A request purpose is optional; "Other" must still be typed.
            const purposeOk = s.request_purpose_id !== 'other' || s.purpose_other.trim() !== '';
            return !!(s.row && s.to_office && chosen(s.receiving_officer_id, s.officer_other) && purposeOk);
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

        // ── Batch mode ───────────────────────────────────────────────────
        async toggleBatch() {
            if (this.batch.running) return;
            const live = this.batch.items.filter(i => i.state !== 'done').length;
            if (this.batch.on && live) {
                const ask = await Swal.fire({
                    icon: 'warning',
                    title: 'Leave batch mode?',
                    text: live + ' file(s) not yet ' + (this.mode === 'in' ? 'received' : 'sent') + ' will be removed from the batch. Nothing is logged for them.',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, leave',
                    cancelButtonText: 'Stay in batch',
                    confirmButtonColor: '#4f46e5',
                    cancelButtonColor: '#6b7280',
                    reverseButtons: true,
                });
                if (!ask.isConfirmed) return;
            }
            this.batch = blankBatch(!this.batch.on);
            if (this.batch.on) {
                this.closeProfile();
                this.manual.open = false;
            }
            this.scan = blankScan();
            this.$nextTick(() => this.focusScan());
        },
        batchHas(trackerId, fileNumber) {
            const number = String(fileNumber || '').trim().toUpperCase();
            return this.batch.items.some(i => (trackerId && Number(i.tracker_id) === Number(trackerId))
                || (number && String(i.file_number || '').trim().toUpperCase() === number));
        },
        // A scanned match ({file_number, file_title, indexed, tracker:{id, office_code, office_name, status}})
        // or a register row ({id, file_number, file_title, file_type, from}).
        addToBatch(m, via) {
            if (!m || this.batch.running) return;
            const t = m.tracker || null;
            const trackerId = t ? t.id : (m.id || null);
            const label = m.file_number || nice(m.file_title) || 'This document';
            if (!trackerId && !m.file_number) {
                this.notify('error', 'This code does not name a file.');
                return;
            }
            if (this.batchHas(trackerId, m.file_number)) {
                this.notify('info', label + ' is already in the batch.');
                return;
            }
            const heldHere = via === 'held' || !!(t && t.office_code === this.office && String(t.status || '').toLowerCase() === 'active')
                || this.lists.held.some(r => String(r.id) === String(trackerId));   // includes legacy hand-overs
            if (this.mode === 'in' && heldHere) {
                this.notify('info', label + ' is already held at your office.');
                return;
            }
            if (this.mode === 'out' && !heldHere) {
                this.notify('error', label + ' is not held at your office — receive it first.');
                return;
            }
            this.batch.items.push({
                tracker_id: trackerId,
                file_number: m.file_number || null,
                file_title: m.file_title || null,
                non_file: m.file_type === 'NON_FILE',
                indexed: m.indexed !== false,
                where: (t && (t.office_name || t.office_code)) || m.from || '',
                purpose: m.purpose || '',
                to_officer: m.to_officer || '',
                via,
                state: 'ready',
                message: '',
                ...blankBatchAll(),
            });
        },
        // Register rows that can be added: pending ones to receive, held ones to send.
        batchPickable() {
            const rows = this.mode === 'in' ? this.lists.pending : this.lists.held;
            return (rows || []).filter(r => !this.batchHas(r.id, r.file_number));
        },
        addPicked() {
            const row = this.batchPickable().find(r => String(r.id) === String(this.batch.pick));
            if (row) this.addToBatch(row, this.mode === 'in' ? 'pending' : 'held');
            this.batch.pick = '';
        },
        addAllPickable() {
            this.batchPickable().forEach(r => this.addToBatch(r, this.mode === 'in' ? 'pending' : 'held'));
        },
        removeFromBatch(item) {
            if (this.batch.running) return;
            this.batch.items = this.batch.items.filter(i => i !== item);
        },
        clearBatchDone() {
            this.batch.items = this.batch.items.filter(i => i.state !== 'done');
        },
        batchPending() {
            return this.batch.items.filter(i => i.state !== 'done');
        },
        batchCount(state) {
            return this.batch.items.filter(i => i.state === state).length;
        },
        applyToAll() {
            const a = this.batch.all;
            this.batchPending().forEach((i) => {
                i.to_office = a.to_office;
                i.receiving_officer_id = a.receiving_officer_id;
                i.officer_other = a.officer_other;
                i.request_purpose_id = a.request_purpose_id;
                i.purpose_other = a.purpose_other;
                if (i.state === 'error') { i.state = 'ready'; i.message = ''; }
            });
        },
        batchItemValid(i) {
            if (this.mode === 'in') return true;
            // A request purpose is optional; "Other" must still be typed.
            const purposeOk = i.request_purpose_id !== 'other' || String(i.purpose_other || '').trim() !== '';
            return !!(i.to_office && i.to_office !== this.office
                && chosen(i.receiving_officer_id, i.officer_other) && purposeOk);
        },
        batchReady() {
            const todo = this.batchPending();
            return todo.length > 0 && !this.batch.running && todo.every(i => this.batchItemValid(i));
        },
        // One file at a time, through the same endpoint a single scan uses; a
        // failure is shown on that file and the rest carry on.
        async runBatch() {
            if (!this.batchReady()) return;
            const sending = this.mode === 'out';
            this.batch.running = true;
            this.busy = true;
            let reuse = null; // purpose / officer chosen once "for the rest of this batch"
            for (const i of this.batchPending()) {
                i.state = 'working';
                i.message = '';
                try {
                    const body = sending
                        ? {
                            office: this.office,
                            tracker_id: i.tracker_id,
                            to_office: i.to_office,
                            request_purpose_id: listed(i.request_purpose_id),
                            request_purpose_other: typed(i.request_purpose_id, i.purpose_other),
                            receiving_officer_id: listed(i.receiving_officer_id),
                            receiving_officer_other: typed(i.receiving_officer_id, i.officer_other),
                            notes: this.batch.notes,
                        }
                        : {
                            office: this.office,
                            entry_type: i.non_file ? 'non_file' : (i.indexed ? 'file' : 'unindexed'),
                            tracker_id: i.tracker_id,
                            file_number: i.file_number,
                            file_title: i.file_title,
                            notes: this.batch.notes,
                            received_via: i.via === 'scan' ? 'scan' : 'manual',
                        };
                    let data;
                    try {
                        data = await this.request(sending ? this.urls.forward : this.urls.receive, { method: 'POST', body: JSON.stringify(body) });
                    } catch (e) {
                        const needs = !sending && e.data && e.data.needs;
                        if (!needs || !needs.length) throw e;
                        let extra = reuse && needs.every(n => reuse.has.includes(n)) ? reuse : null;
                        if (!extra) {
                            const answer = await this.askReceiptDetails(needs, { purpose: i.purpose, officer: i.to_officer, label: i.file_number || i.file_title }, true);
                            if (!answer) throw new Error('Skipped — no request purpose / receiving officer given.');
                            extra = { fields: answer.fields, has: needs };
                            if (answer.forRest) reuse = extra;
                        }
                        data = await this.request(this.urls.receive, { method: 'POST', body: JSON.stringify({ ...body, ...extra.fields }) });
                    }
                    i.state = 'done';
                    i.message = data.message || '';
                } catch (e) {
                    i.state = 'error';
                    i.message = e.message;
                }
            }
            this.batch.running = false;
            this.busy = false;
            const done = this.batchCount('done');
            const failed = this.batchCount('error');
            this.notify(failed ? 'warning' : 'success',
                done + ' file(s) ' + (sending ? 'sent' : 'received') + (failed ? ', ' + failed + ' failed — see the batch list.' : '.'));
            await this.loadLists();
            this.focusScan();
        },

        // ── Edit one stored log ──────────────────────────────────────────
        // Admins edit any log; anyone else with the module's edit action only
        // logs at their own office (the server checks both again). The old
        // entry is kept in a hidden history on the server.
        canEditEvent(ev) {
            if (!this.canEditLogs || !ev || !ev.ref || !ev.entry) return false;
            if (this.canChooseOffice) return true;
            const code = ev.entry.receiving_office_code || ev.entry.office_code || '';
            return !!(this.myOffice && code === this.myOffice.code);
        },
        today() {
            const d = new Date();
            const pad = (n) => String(n).padStart(2, '0');
            return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
        },
        openEdit(ev) {
            if (!this.canEditEvent(ev)) return;
            const e = ev.entry;
            const date = (v) => /^\d{4}-\d{2}-\d{2}$/.test(String(v || '').trim()) ? String(v).trim() : '';
            const time = (v) => {
                const m = String(v || '').trim().match(/^(\d{1,2}):(\d{2})/);
                return m ? m[1].padStart(2, '0') + ':' + m[2] : '';
            };
            const status = String(e.status || '').toLowerCase();
            // Pre-select the stored officer and purpose: a listed one by id (or by
            // name), otherwise "Other" with the stored text.
            const officerName = String(e.receiving_officer_name || '').trim();
            const listedOfficer = this.officers.find(o => e.receiving_officer_id && Number(o.id) === Number(e.receiving_officer_id))
                || (!e.receiving_officer_id && officerName ? this.officers.find(o => String(o.name || '').trim().toLowerCase() === officerName.toLowerCase()) : null);
            // The entry's own purpose; for a tracker's newest step it is often kept
            // only on the tracker row, as the register shows it.
            const ref = ev.ref || {};
            const ownPurpose = (e.purpose && String(e.purpose).toLowerCase() !== 'received') ? String(e.purpose).trim() : '';
            const purposeName = ownPurpose || String(ref.tracker_purpose || '').trim();
            const listedPurpose = (!ownPurpose && ref.tracker_purpose_id && this.requestPurposes.find(p => Number(p.id) === Number(ref.tracker_purpose_id)))
                || (purposeName ? this.requestPurposes.find(p => String(p.name || '').trim().toLowerCase() === purposeName.toLowerCase()) : null);
            const officeCode = e.receiving_office_code || e.office_code || '';
            const values = {
                office_code: this.offices.some(o => o.office_code === officeCode) ? officeCode : '',
                log_in_date: date(e.log_in_date || e.logInDate),
                log_in_time: time(e.log_in_time || e.logInTime),
                log_out_date: date(e.log_out_date || e.logOutDate),
                log_out_time: time(e.log_out_time || e.logOutTime),
                notes: e.notes || '',
                completion_notes: e.completion_notes || '',
                receiving_officer_id: listedOfficer ? String(listedOfficer.id) : (officerName ? 'other' : ''),
                officer_other: listedOfficer ? '' : officerName,
                request_purpose_id: listedPurpose ? String(listedPurpose.id) : (purposeName ? 'other' : ''),
                purpose_other: listedPurpose ? '' : purposeName,
            };
            this.edit = {
                ...blankEdit(),
                ...values,
                open: true,
                ref: ev.ref,
                office: ev.office,
                status,
                statusLabel: status === 'pending_acceptance' ? 'In transit' : (status === 'completed' ? 'Logged out' : 'Logged in'),
                initial: { ...values },
                isNewest: this.historyEntries().filter(x => x && x._ref && Number(x._ref.tracker_id) === Number(ref.tracker_id))
                    .every(x => x._ref.index <= ref.index),
            };
        },
        closeEdit() {
            if (this.busy) return;
            this.edit = blankEdit();
        },
        editChanges() {
            const ed = this.edit;
            const out = {};
            // Log-in date and time are shown but locked; they are never sent.
            ['office_code', 'log_out_date', 'log_out_time', 'notes', 'completion_notes'].forEach((k) => {
                if (String(ed[k] || '').trim() !== String(ed.initial[k] || '').trim()) out[k] = String(ed[k] || '').trim();
            });
            // The officer and purpose start as stored; only a changed one is sent.
            const same = (a, b) => String(ed[a] || '').trim() === String(ed.initial[a] || '').trim()
                && String(ed[b] || '').trim().toLowerCase() === String(ed.initial[b] || '').trim().toLowerCase();
            if (!same('receiving_officer_id', 'officer_other')) {
                if (ed.receiving_officer_id === 'other') {
                    if (String(ed.officer_other || '').trim()) out.receiving_officer_other = ed.officer_other.trim();
                } else if (ed.receiving_officer_id) {
                    out.receiving_officer_id = Number(ed.receiving_officer_id);
                }
            }
            if (!same('request_purpose_id', 'purpose_other')) {
                if (ed.request_purpose_id === 'other') {
                    if (String(ed.purpose_other || '').trim()) out.request_purpose_other = ed.purpose_other.trim();
                } else if (ed.request_purpose_id) {
                    out.request_purpose_id = Number(ed.request_purpose_id);
                }
            }
            return out;
        },
        editValid() {
            const ed = this.edit;
            if (!ed.open || !String(ed.reason || '').trim()) return false;
            if (!ed.office_code) return false;
            if (ed.receiving_officer_id === 'other' && !String(ed.officer_other || '').trim()) return false;
            if (ed.request_purpose_id === 'other' && !String(ed.purpose_other || '').trim()) return false;
            return Object.keys(this.editChanges()).length > 0;
        },
        async saveEdit() {
            if (!this.editValid()) return;
            const changes = this.editChanges();
            // A cleared date or time means "leave as stored"; only notes can be emptied.
            ['log_out_date', 'log_out_time'].forEach((k) => {
                if (k in changes && !changes[k]) delete changes[k];
            });
            this.busy = true;
            try {
                const data = await this.request(this.urls.editLog, {
                    method: 'POST',
                    body: JSON.stringify({
                        tracker_id: this.edit.ref.tracker_id,
                        index: this.edit.ref.index,
                        key: this.edit.ref.key,
                        reason: this.edit.reason.trim(),
                        ...changes,
                    }),
                });
                this.notify('success', data.message);
                this.busy = false;
                this.closeEdit();
                await this.loadLists();
                await this.reloadProfile();
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

        // ── Tracking sheet ────────────────────────────────────────────────
        // The sheet is the main Log a File page's own (printFileTrackerDetails in
        // create_file_tracker_page/partials/js.blade.php), so both pages print the
        // same document. As on the MLS commissioning screen, that page is loaded in
        // a hidden same-origin frame where its script can initialise, and the
        // tracker comes from its search endpoint, decorated the way its sheet expects.
        sheetFrame() {
            if (!this._sheetFrame) {
                this._sheetFrame = new Promise((resolve) => {
                    const frame = document.createElement('iframe');
                    frame.setAttribute('aria-hidden', 'true');
                    frame.style.cssText = 'position:fixed;left:-10000px;top:0;width:1160px;height:900px;border:0;visibility:hidden;';
                    frame.src = this.urls.trackingPage;
                    document.body.appendChild(frame);
                    const started = Date.now();
                    const poll = setInterval(() => {
                        let win = null;
                        try { win = frame.contentWindow; } catch (e) { win = null; }
                        if (win && typeof win.printFileTrackerDetails === 'function' && typeof win.transformApiTracker === 'function') {
                            clearInterval(poll);
                            resolve(win);
                        } else if (Date.now() - started > 30000) {
                            clearInterval(poll);
                            frame.remove();
                            resolve(null);
                        }
                    }, 150);
                });
                // A failed load is not cached, so the next click tries again.
                this._sheetFrame.then((win) => { if (!win) this._sheetFrame = null; });
            }
            return this._sheetFrame;
        },

        async printTrackingSheet() {
            const t = this.profile.data && this.profile.data.tracker;
            if (!t || !t.tracking_id || this.sheetBusy) return;
            // Open the window now, inside the click, or the browser blocks it.
            const out = window.open('', '_blank');
            if (!out) {
                this.notify('error', 'Allow pop-ups for this site to print the tracking sheet.');
                return;
            }
            out.document.write('<p style="font:14px Arial,sans-serif;padding:24px;color:#555">Preparing tracking sheet…</p>');
            this.sheetBusy = true;
            try {
                const [win, res] = await Promise.all([
                    this.sheetFrame(),
                    fetch(this.urls.trackerSearch + '?' + new URLSearchParams({ query: t.tracking_id }), {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin',
                    }).then((r) => r.json()),
                ]);
                if (!win) throw new Error('The tracking sheet could not be prepared. Try again.');
                const raw = res && res.success && res.data && res.data.tracker;
                if (!raw || Number(raw.id) !== Number(t.id)) throw new Error('Tracker not found for printing.');
                const tracker = win.transformApiTracker(raw);
                const html = tracker && win.printFileTrackerDetails(tracker, { returnHtml: true });
                if (!html) throw new Error('The tracking sheet could not be built.');

                out.document.open();
                out.document.write(html);
                out.document.close();
                try {
                    out.document.querySelectorAll('.print-container').forEach((root) => {
                        if (typeof win.paginateTrackingSheet === 'function') win.paginateTrackingSheet(out.document, root);
                    });
                } catch (e) { console.error('Tracking sheet pagination failed:', e); }
                const go = () => setTimeout(() => { try { out.focus(); out.print(); } catch (e) { /* closed */ } }, 150);
                const qr = out.document.querySelector('.qr-wrapper img');
                if (qr && !qr.complete) {
                    qr.addEventListener('load', go, { once: true });
                    qr.addEventListener('error', go, { once: true });
                } else {
                    go();
                }
            } catch (e) {
                try { out.close(); } catch (x) { /* already closed */ }
                this.notify('error', e.message || 'Could not print the tracking sheet.');
            } finally {
                this.sheetBusy = false;
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

/**
 * Type-to-search dropdown over a hidden native <select x-ref="sel">. The select
 * keeps its x-model, @change and x-for options, so it stays the source of truth:
 * a pick sets its value and fires `change`, exactly as choosing it natively would.
 * Use: <div x-data="fileLogPickList(() => model.value)"> <select x-ref="sel" class="hidden">…
 * "Other" options always stay at the foot of the list, whatever is typed.
 */
window.fileLogPickList = function (getValue) {
    return {
        psOpen: false,
        psQ: '',
        psHi: 0,
        psItems: [],
        psRead() {
            return Array.from(this.$refs.sel.options)
                .filter(o => o.value !== '')
                .map(o => ({
                    value: o.value,
                    text: o.text.trim(),
                    group: o.parentElement && o.parentElement.tagName === 'OPTGROUP' ? o.parentElement.label : '',
                    other: o.value === 'other',
                }));
        },
        // The chosen option's text; re-evaluated whenever the bound model changes.
        psLabel() {
            const v = getValue();
            if (v === '' || v === null || v === undefined) return '';
            const o = Array.from(this.$refs.sel.options).find(i => i.value === String(v));
            return o ? o.text.trim() : '';
        },
        psShow() {
            if (this.$refs.sel.disabled) return;
            if (!this.psOpen) { this.psItems = this.psRead(); this.psQ = ''; }
            this.psOpen = true;
            this.psHi = 0;
        },
        psClose() {
            this.psOpen = false;
            this.psQ = '';
        },
        psToggle() {
            if (this.psOpen) { this.psClose(); return; }
            this.$refs.psInput.focus();
            this.psShow();
        },
        psFiltered() {
            const words = this.psQ.toLowerCase().split(/\s+/).filter(Boolean);
            const hit = it => words.every(w => (it.text + ' ' + it.group).toLowerCase().includes(w));
            return this.psItems.filter(it => !it.other && hit(it)).concat(this.psItems.filter(it => it.other));
        },
        // Group headings only where the select uses optgroups.
        psRows() {
            const rows = [];
            let last = null;
            this.psFiltered().forEach((it, i) => {
                if (it.group && it.group !== last && !it.other) rows.push({ key: 'g-' + it.group, heading: it.group });
                if (!it.other) last = it.group;
                rows.push({ key: 'o-' + it.value, item: it, index: i });
            });
            return rows;
        },
        psMove(step) {
            const n = this.psFiltered().length;
            if (!this.psOpen) { this.psShow(); return; }
            if (n) this.psHi = (this.psHi + step + n) % n;
            this.$nextTick(() => {
                const el = this.$refs.psList && this.$refs.psList.querySelector('[data-ps-hi="1"]');
                if (el) el.scrollIntoView({ block: 'nearest' });
            });
        },
        psEnter() {
            const it = this.psFiltered()[this.psHi];
            if (this.psOpen && it) this.psPick(it);
        },
        psPick(it) {
            const sel = this.$refs.sel;
            sel.value = it.value;
            sel.dispatchEvent(new Event('change', { bubbles: true }));
            this.psClose();
            this.$refs.psInput.blur();
        },
    };
};
