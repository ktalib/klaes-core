
        // Each of the three tabs is served by its own paginated endpoint:
        //   • Requested Files  → /api/file-tracker-dashboard/requested
        //   • In Transit       → /api/file-tracker-dashboard/in-transit
        //   • Not in Transit   → /api/file-tracker-dashboard/not-in-transit
        // They replaced the single /overview-commissioner call, which capped at
        // 500 rows and silently truncated whichever tab sorted last.
        let officeData = {};
        let currentDetailsTracker = null;
        let filteredTrackers = [];
        let priorityChart = null;
        let officeChart = null;
        let chartsVisible = false;
        let currentTab = 'requested';

        // The movement entry where the file currently sits: the last office that has not
        // yet logged the file back out (no logOutDate). Falls back to the last entry.
        function getCurrentOutEntry(tracker) {
            const entries = tracker.logEntries || [];
            if (entries.length === 0) return null;
            for (let i = entries.length - 1; i >= 0; i--) {
                if (!entries[i].logOutDate) return entries[i];
            }
            return entries[entries.length - 1];
        }

        // ---------------------------------------------------------------------
        // "In Transit" tab — grouped by requester's office, the same way the
        // Requested tab is grouped by department:
        //   • /api/file-tracker-dashboard/in-transit-offices → one page of groups
        //   • /api/file-tracker-dashboard/in-transit         → one group's rows
        // Search, priority and office filters are applied server-side so the
        // counts cover the whole set rather than one capped page.
        // ---------------------------------------------------------------------
        const transitState = {
            page: 1,
            perPage: 25,
            search: '',
            priority: 'ALL',
            office: 'ALL',
            total: 0,
            lastPage: 1,
            from: 0,
            to: 0,
            // A "page" here is 25 offices, not 25 rows — the tab lists groups and
            // pulls an office's rows only when it is expanded.
            unit: 'offices',
            offices: [],
            rowsByOffice: {},
            loadingOffices: new Set(),
            // Offices start collapsed; only the ones opened by the user are listed.
            expandedOffices: new Set(),
            stats: { total: 0, high: 0, medium: 0, low: 0, offices: 0 },
            officeDistribution: [],
            loaded: false,
            loading: false,
            requestId: 0
        };
        let transitSearchTimer = null;

        function buildTransitQuery(page, perPage) {
            const params = new URLSearchParams({
                page: page,
                per_page: perPage,
                priority: transitState.priority
            });
            if (transitState.search) params.set('search', transitState.search);
            return params;
        }

        // Load one page of requester-office groups (counts only — no rows).
        async function loadInTransit(page = transitState.page) {
            const requestId = ++transitState.requestId;
            transitState.loading = true;

            const loadingEl = document.getElementById('transit-loading');
            const noResults = document.getElementById('no-results');
            const pagination = document.getElementById('transit-pagination');

            loadingEl?.classList.remove('hidden');
            noResults?.classList.add('hidden');
            pagination?.classList.add('hidden');

            const params = buildTransitQuery(page, transitState.perPage);
            // The dropdown narrows the tab to a single office group.
            if (transitState.office && transitState.office !== 'ALL') {
                params.set('office', transitState.office);
            }

            try {
                const response = await fetch(`/api/file-tracker-dashboard/in-transit-offices?${params.toString()}`);
                const result = await response.json();

                // A newer request has already been fired — discard this response.
                if (requestId !== transitState.requestId) return;

                if (!result.success) throw new Error(result.message || 'Request failed');

                transitState.offices = result.data.offices || [];
                // Every office, even when the dropdown is narrowed to one, so the
                // dropdown keeps its other options.
                transitState.officeDistribution = result.data.officeDistribution || [];
                transitState.stats = result.data.stats;
                transitState.page = result.data.pagination.page;
                transitState.lastPage = result.data.pagination.lastPage;
                transitState.total = result.data.pagination.total;
                transitState.from = result.data.pagination.from;
                transitState.to = result.data.pagination.to;
                transitState.loaded = true;
                // Cached rows belong to the previous filter/search, so drop them.
                transitState.rowsByOffice = {};

                updateDashboardStats();
                populateOfficeFilter();
                renderTransitTable();
                renderPagination('transit', transitState, target => {
                    loadInTransit(target);
                    document.getElementById('panel-transit')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                });
                if (chartsVisible) updateCharts();
            } catch (error) {
                if (requestId !== transitState.requestId) return;
                console.error('Error loading files in transit:', error);
                document.getElementById('files-table-body').innerHTML = '';
                noResults?.classList.remove('hidden');
            } finally {
                if (requestId === transitState.requestId) {
                    transitState.loading = false;
                    loadingEl?.classList.add('hidden');
                }
            }
        }

        // Fetch (and cache) every in-transit file for one requester office.
        async function loadOfficeRows(officeCode) {
            if (transitState.rowsByOffice[officeCode]
                || transitState.loadingOffices.has(officeCode)) {
                return;
            }

            transitState.loadingOffices.add(officeCode);

            const params = buildTransitQuery(1, 1000);
            params.set('office', officeCode);

            try {
                const response = await fetch(`/api/file-tracker-dashboard/in-transit?${params.toString()}`);
                const result = await response.json();
                const rows = result.success ? (result.data.files || []) : [];

                rows.forEach(t => {
                    if (t.currentOfficeId) {
                        officeData[t.currentOfficeId] = {
                            name: t.currentOffice,
                            code: t.currentOfficeId,
                            department: t.currentOfficeDepartment || t.department
                        };
                    }
                });

                transitState.rowsByOffice[officeCode] = rows;
            } catch (error) {
                console.error(`Error loading files for office ${officeCode}:`, error);
                transitState.rowsByOffice[officeCode] = [];
            } finally {
                transitState.loadingOffices.delete(officeCode);
            }
        }

        // Rebuild the office dropdown from the offices actually holding files.
        function populateOfficeFilter() {
            const select = document.getElementById('office-filter');
            if (!select) return;

            const current = transitState.office;
            select.innerHTML = ['<option value="ALL">All Offices</option>']
                .concat(transitState.officeDistribution.map(o =>
                    `<option value="${escapeHtml(o.officeCode)}">${escapeHtml(o.officeName)} (${o.totalFiles})</option>`
                ))
                .join('');
            select.value = current;
        }

        // Update dashboard stats for transit (files currently logged out)
        function updateDashboardStats() {
            const stats = transitState.stats || { total: 0, high: 0, offices: 0 };

            document.getElementById('total-files').textContent = stats.total;
            // Every in-transit file is actively out of the registry.
            document.getElementById('active-files').textContent = stats.total;
            document.getElementById('high-priority').textContent = stats.high;
            document.getElementById('offices-involved').textContent = stats.offices;
        }

        // ---------------------------------------------------------------------
        // "Not in Transit" tab
        //
        // Unlike the other two tabs this one is NOT driven by the file_tracker
        // payload: it lists every row of the file indexing register that is not
        // currently logged out, served page-by-page from
        // /api/file-tracker-dashboard/not-in-transit.
        // ---------------------------------------------------------------------
        const idleState = {
            page: 1,
            perPage: 25,
            search: '',
            total: 0,
            lastPage: 1,
            from: 0,
            to: 0,
            files: [],
            loaded: false,
            loading: false,
            requestId: 0
        };
        let idleSearchTimer = null;

        function escapeHtml(value) {
            if (value === null || value === undefined) return '';
            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        async function loadNotInTransit(page = idleState.page) {
            const requestId = ++idleState.requestId;
            idleState.loading = true;

            const loadingEl = document.getElementById('idle-loading');
            const noResults = document.getElementById('idle-no-results');
            const pagination = document.getElementById('idle-pagination');

            loadingEl?.classList.remove('hidden');
            noResults?.classList.add('hidden');
            pagination?.classList.add('hidden');

            const params = new URLSearchParams({
                page: page,
                per_page: idleState.perPage
            });
            if (idleState.search) params.set('search', idleState.search);

            try {
                const response = await fetch(`/api/file-tracker-dashboard/not-in-transit?${params.toString()}`);
                const result = await response.json();

                // A newer request has already been fired — discard this response.
                if (requestId !== idleState.requestId) return;

                if (!result.success) throw new Error(result.message || 'Request failed');

                idleState.files = result.data.files || [];
                idleState.page = result.data.pagination.page;
                idleState.lastPage = result.data.pagination.lastPage;
                idleState.total = result.data.pagination.total;
                idleState.from = result.data.pagination.from;
                idleState.to = result.data.pagination.to;
                idleState.loaded = true;

                document.getElementById('idle-total').textContent = result.data.stats.total;
                document.getElementById('idle-awaiting').textContent = result.data.stats.neverMoved;
                document.getElementById('idle-completed').textContent = result.data.stats.returned;

                renderIdleTable();
                renderIdlePagination();
            } catch (error) {
                if (requestId !== idleState.requestId) return;
                console.error('Error loading files not in transit:', error);
                document.getElementById('idle-table-body').innerHTML = '';
                noResults?.classList.remove('hidden');
            } finally {
                if (requestId === idleState.requestId) {
                    idleState.loading = false;
                    loadingEl?.classList.add('hidden');
                }
            }
        }

        // Shared pager for the server-paginated tabs (Requested / Not in Transit).
        function renderPagination(prefix, state, onGo) {
            const pagination = document.getElementById(`${prefix}-pagination`);
            const summary = document.getElementById(`${prefix}-pagination-summary`);
            const controls = document.getElementById(`${prefix}-pagination-controls`);
            if (!pagination || !controls) return;

            if (state.total === 0) {
                pagination.classList.add('hidden');
                return;
            }

            pagination.classList.remove('hidden');
            const unit = state.unit || 'files';
            summary.textContent = `Showing ${state.from}-${state.to} of ${state.total.toLocaleString()} ${unit}`;

            const btn = (label, page, opts = {}) => {
                const disabled = opts.disabled ? 'disabled' : '';
                const base = 'px-3 py-1.5 text-sm border rounded-md';
                const cls = opts.active
                    ? `${base} border-blue-600 bg-blue-600 text-white font-medium`
                    : opts.disabled
                        ? `${base} border-gray-200 text-gray-300 cursor-not-allowed`
                        : `${base} border-gray-300 text-gray-700 bg-white hover:bg-gray-50`;
                return `<button type="button" class="page-btn ${cls}" data-page="${page}" ${disabled}>${label}</button>`;
            };

            // Window of page numbers around the current page.
            const pages = [];
            const span = 2;
            const start = Math.max(1, state.page - span);
            const end = Math.min(state.lastPage, state.page + span);
            if (start > 1) pages.push(1);
            if (start > 2) pages.push('...');
            for (let p = start; p <= end; p++) pages.push(p);
            if (end < state.lastPage - 1) pages.push('...');
            if (end < state.lastPage) pages.push(state.lastPage);

            controls.innerHTML = [
                btn('Prev', state.page - 1, { disabled: state.page <= 1 }),
                ...pages.map(p => p === '...'
                    ? '<span class="px-2 text-sm text-gray-400">…</span>'
                    : btn(p, p, { active: p === state.page })),
                btn('Next', state.page + 1, { disabled: state.page >= state.lastPage })
            ].join('');

            controls.querySelectorAll('.page-btn:not([disabled])').forEach(button => {
                button.addEventListener('click', () => {
                    const target = parseInt(button.dataset.page, 10);
                    if (!isNaN(target) && target !== state.page) onGo(target);
                });
            });
        }

        function renderIdlePagination() {
            renderPagination('idle', idleState, page => {
                loadNotInTransit(page);
                document.getElementById('panel-idle')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        }

        // Format time difference
        function formatTimeDifference(dateString) {
            const now = new Date();
            const date = new Date(dateString);
            const diffMs = now - date;
            const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));
            const diffHours = Math.floor((diffMs % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            
            if (diffDays > 0) {
                return `${diffDays} day${diffDays > 1 ? 's' : ''} ago`;
            } else if (diffHours > 0) {
                return `${diffHours} hour${diffHours > 1 ? 's' : ''} ago`;
            } else {
                return 'Less than an hour ago';
            }
        }

        // Format date for display
        function formatDate(dateString) {
            const d = new Date(dateString);
            return d.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
        }

        // Build the movement chain following the Create File Tracker workflow:
        //   Registry (origin) → destination office(s) → back to Registry (on return).
        // Each hop is an office the file was logged out to; a trailing "↩ Registry"
        // marks a file that has been logged back in.
        function getMovementHistory(tracker) {
            const entries = (tracker && tracker.logEntries) || [];
            if (entries.length === 0) return 'No movement recorded';

            const chain = [];
            const origin = tracker.originOffice || 'Registry';
            chain.push(origin);
            entries.forEach(e => {
                const name = e.officeName || e.officeId;
                if (name && chain[chain.length - 1] !== name) chain.push(name);
            });
            // Logged back into the registry closes the loop.
            if (tracker.isReturned && chain[chain.length - 1] !== origin) {
                chain.push(`↩ ${origin}`);
            }
            return chain.join(' → ');
        }

        // Status label + badge class, derived from the workflow classification.
        function getStatusInfo(tracker) {
            const ms = tracker.movementStatus
                || (tracker.isInTransit ? 'in_transit'
                    : tracker.isReturned ? 'returned'
                    : tracker.isCanceled ? 'canceled'
                    : tracker.isRequested ? 'logout' : 'idle');
            switch (ms) {
                case 'logout':     return { label: 'Log-out', class: 'in-transit' };
                case 'in_transit': return { label: 'In Transit', class: 'in-transit' };
                case 'returned':   return { label: 'Completed', class: 'completed' };
                case 'canceled':   return { label: 'Canceled', class: 'idle' };
                default:           return { label: 'Idle', class: 'idle' };
            }
        }

        // Render the current page of requester-office groups; a group's files are
        // rendered underneath it once expanded (and fetched on first expand).
        function renderTransitTable() {
            const tableBody = document.getElementById('files-table-body');
            const noResults = document.getElementById('no-results');
            const groups = transitState.offices || [];

            if (groups.length === 0) {
                tableBody.innerHTML = '';
                noResults.classList.remove('hidden');
                return;
            }

            noResults.classList.add('hidden');

            filteredTrackers = Object.values(transitState.rowsByOffice).flat();

            tableBody.innerHTML = groups.map(group => {
                const code = group.officeCode;
                const expanded = transitState.expandedOffices.has(code);
                const rows = transitState.rowsByOffice[code];
                const isLoading = transitState.loadingOffices.has(code);
                const total = group.totalFiles;
                const high = group.highFiles || 0;

                // Offices carry their department's accent, so a group reads the
                // same way it does on the Requested tab.
                const style = departmentStyle(group.department);
                const styleVars = departmentStyleVars(style);

                const header = `
                <tr class="department-group-row ${expanded ? 'is-expanded' : ''}" style="${styleVars}" data-office-toggle="${escapeHtml(code)}" aria-expanded="${expanded}" title="Click to ${expanded ? 'collapse' : 'expand'}">
                    <td colspan="6" class="px-6 py-3">
                        <div class="flex items-center gap-3">
                            <i data-lucide="chevron-right" class="h-4 w-4 department-group-chevron"></i>
                            <span class="department-group-icon">
                                <i data-lucide="${style.icon}" class="h-4 w-4"></i>
                            </span>
                            <span class="department-group-name">${escapeHtml(group.officeName || code)}</span>
                            <span class="department-group-count">${total} file${total === 1 ? '' : 's'}</span>
                            ${high > 0 ? `
                            <span class="department-group-high">
                                <i data-lucide="alert-triangle" class="h-3 w-3"></i>
                                ${high} high
                            </span>` : ''}
                            ${group.department ? `<span class="department-group-note">${escapeHtml(group.department)}</span>` : ''}
                            <span class="flex-1"></span>
                            ${isLoading
                                ? '<span class="department-group-note">loading…</span>'
                                : `<span class="department-group-hint">${expanded ? 'Hide files' : 'Show files'}</span>`}
                        </div>
                    </td>
                </tr>
                `;

                if (!expanded) return header;

                if (isLoading || !rows) {
                    // A group can be expanded with its rows dropped (a filter
                    // reloaded the page), so re-fetch rather than spin forever.
                    if (!isLoading) loadOfficeRows(code).then(renderTransitTable);

                    return header + `
                    <tr class="department-group-loading" style="${styleVars}">
                        <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500">
                            <i data-lucide="loader-2" class="h-4 w-4 inline animate-spin mr-1"></i>
                            Loading ${escapeHtml(group.officeName || code)} files...
                        </td>
                    </tr>
                    `;
                }

                return header + rows.map(tracker => transitRowHtml(tracker, styleVars, code)).join('');
            }).join('');

            setupOfficeGroupToggles();
            setupViewDetailsButtons();
            updateTransitGroupToggleLabel(groups);
            lucide.createIcons();
        }

        function updateTransitGroupToggleLabel(groups) {
            const label = document.getElementById('transit-toggle-groups-label');
            if (!label) return;

            const allExpanded = groups.length > 0
                && groups.every(g => transitState.expandedOffices.has(g.officeCode));
            label.textContent = allExpanded ? 'Collapse all' : 'Expand all';
        }

        // Toggle an office group open/closed, remembering the state so it survives
        // re-renders within the session.
        function setupOfficeGroupToggles() {
            document.querySelectorAll('#files-table-body [data-office-toggle]').forEach(header => {
                header.addEventListener('click', async function () {
                    const code = this.dataset.officeToggle;

                    if (transitState.expandedOffices.has(code)) {
                        transitState.expandedOffices.delete(code);
                        renderTransitTable();
                        return;
                    }

                    transitState.expandedOffices.add(code);
                    renderTransitTable();

                    if (!transitState.rowsByOffice[code]) {
                        await loadOfficeRows(code);
                        renderTransitTable();
                    }
                });
            });
        }

        function transitRowHtml(tracker, styleVars, officeCode) {
            // Falls back to the tracker itself when the movement log is empty,
            // so a row is never silently dropped from the page.
            const activeEntry = getCurrentOutEntry(tracker) || {};

            const priorityClass = `priority-${tracker.priority}`;
            const priorityText = tracker.priority.charAt(0) + tracker.priority.slice(1).toLowerCase();
            const statusInfo = getStatusInfo(tracker);
            // The office the file was requested to (the receiving officer's
            // posting). Read off the row itself rather than the shared
            // officeData map, whose entries are keyed by office code and so
            // cannot carry a per-file department for Department Queue rows.
            const requesterOfficeName = tracker.requesterOffice || tracker.currentOffice || 'Unknown';
            const requesterOfficeDepartment = tracker.requesterOfficeDepartment || tracker.department || '';
            // "Department Queue" is a holding bucket rather than a real office, so
            // show the owning department (e.g. DAGs Office) on its own instead.
            const isDepartmentQueue = /department\s*queue/i.test(requesterOfficeName);
            const officeName = isDepartmentQueue ? (requesterOfficeDepartment || requesterOfficeName) : requesterOfficeName;
            const officeDepartment = isDepartmentQueue ? '' : requesterOfficeDepartment;
            // Same shape as the archive tab's "Last Updated": relative age on
            // top, the formatted calendar date underneath.
            const sinceStamp = activeEntry.createdAt || activeEntry.logInDate || null;
            const timeInOffice = sinceStamp ? formatTimeDifference(sinceStamp) : 'N/A';
            const timeInOfficeOn = sinceStamp ? formatDate(sinceStamp) : '';

            return `
                <tr class="file-row fade-in" data-office-rows="${escapeHtml(officeCode || '')}" style="${styleVars || ''}">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 h-10 w-10 bg-blue-100 rounded-lg flex items-center justify-center">
                                <i data-lucide="file-text" class="h-5 w-5 text-blue-600"></i>
                            </div>
                            <div class="ml-4">
                                <div>
                                    <span class="file-no-badge file-no-badge-lead"><span class="dot"></span>${escapeHtml(tracker.fileNo)}</span>
                                </div>
                                <div class="file-title-sub mt-1">${escapeHtml(tracker.fileName)}</div>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 h-8 w-8 bg-gray-100 rounded-full flex items-center justify-center">
                                <i data-lucide="building" class="h-4 w-4 text-gray-600"></i>
                            </div>
                            <div class="ml-3">
                                <div class="text-sm font-medium text-gray-900">${escapeHtml(officeName)}</div>
                                ${officeDepartment ? `<div class="text-xs text-gray-500">${escapeHtml(officeDepartment)}</div>` : ''}
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm text-gray-900">${timeInOffice}</div>
                        <div class="text-xs text-gray-500">${timeInOfficeOn}</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${priorityClass}">${priorityText}</span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="status-badge ${statusInfo.class}">
                            <span class="dot"></span>
                            ${statusInfo.label}
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                        <button class="view-details-btn inline-flex items-center px-3 py-1.5 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50" data-tracking-id="${tracker.trackingId}">
                            <i data-lucide="eye" class="h-4 w-4 mr-1"></i>
                            View Details
                        </button>
                    </td>
                </tr>
            `;
        }

        // Render the current page of indexed files that are not in transit.
        function renderIdleTable() {
            const tableBody = document.getElementById('idle-table-body');
            const noResults = document.getElementById('idle-no-results');

            if (idleState.files.length === 0) {
                tableBody.innerHTML = '';
                noResults.classList.remove('hidden');
                return;
            }

            noResults.classList.add('hidden');

            tableBody.innerHTML = idleState.files.map(file => {
                const statusClass = file.isReturned ? 'active' : 'completed';
                const location = escapeHtml(file.location || '—');
                const updated = file.updatedAt ? formatTimeDifference(file.updatedAt) : 'N/A';
                const updatedOn = file.updatedAt ? formatDate(file.updatedAt) : '';

                return `
                <tr class="file-row fade-in">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 h-10 w-10 bg-gray-100 rounded-lg flex items-center justify-center">
                                <i data-lucide="archive" class="h-5 w-5 text-gray-600"></i>
                            </div>
                            <div class="ml-4">
                                <div class="text-sm font-medium text-gray-900">${escapeHtml(file.fileName)}</div>
                                <div class="mt-1">
                                    <span class="file-no-badge"><span class="dot"></span>${escapeHtml(file.fileNo)}</span>
                                </div>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm font-medium text-gray-900">${location}</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm text-gray-900">${updated}</div>
                        <div class="text-xs text-gray-500">${updatedOn}</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm text-gray-900">${escapeHtml(file.fileType || '—')}</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="status-badge ${statusClass}">
                            <span class="dot"></span>
                            ${escapeHtml(file.statusLabel)}
                        </span>
                    </td>
                </tr>
                `;
            }).join('');

            setupViewDetailsButtons();
            lucide.createIcons();
        }

        // ---------------------------------------------------------------------
        // "Requested Files" tab
        //
        // Served by /api/file-tracker-dashboard/requested. It used to be filtered
        // client-side out of the commissioner overview, but that payload is capped
        // at 500 rows and orders In-Transit/Completed first, so the requested rows
        // were being truncated away — often leaving the tab completely empty.
        // ---------------------------------------------------------------------
        const requestedState = {
            page: 1,
            perPage: 25,
            search: '',
            period: 'all',
            total: 0,
            lastPage: 1,
            from: 0,
            to: 0,
            // A "page" here is 25 departments, not 25 rows — the tab lists groups
            // and pulls a department's rows only when it is expanded.
            unit: 'departments',
            departments: [],
            departmentCounts: [],
            rowsByDepartment: {},
            loadingDepartments: new Set(),
            stats: { total: 0, high: 0, medium: 0, low: 0, departments: 0 },
            // Departments start collapsed; only the ones opened by the user are listed.
            expandedDepartments: new Set(),
            loaded: false,
            loading: false,
            requestId: 0
        };
        let requestedSearchTimer = null;

        const REQUESTED_PERIOD_LABELS = {
            all: 'All requests',
            weekly: 'Last 7 days',
            monthly: 'Last month',
            quarterly: 'Last 3 months'
        };

        // Summary cards for the Requested tab — whole-set figures from the API,
        // so they reflect the active period/search rather than the visible page.
        function updateRequestedStats() {
            const stats = requestedState.stats || {};

            document.getElementById('requested-total').textContent = (stats.total || 0).toLocaleString();
            document.getElementById('requested-high').textContent = (stats.high || 0).toLocaleString();
            document.getElementById('requested-medium').textContent =
                ((stats.medium || 0) + (stats.low || 0)).toLocaleString();
            document.getElementById('requested-departments').textContent = stats.departments || 0;

            const periodLabel = document.getElementById('requested-period-label');
            if (periodLabel) {
                periodLabel.textContent = REQUESTED_PERIOD_LABELS[requestedState.period] || 'All requests';
            }

            // Busiest department, for context under the department count.
            const top = (requestedState.departmentCounts || [])
                .slice()
                .sort((a, b) => b.totalFiles - a.totalFiles)[0];
            const topLabel = document.getElementById('requested-top-department');
            if (topLabel) {
                topLabel.textContent = top
                    ? `Most requests: ${top.department} (${top.totalFiles})`
                    : 'Across all departments';
            }
        }

        function buildRequestedQuery(page, perPage) {
            const params = new URLSearchParams({
                page: page,
                per_page: perPage,
                period: requestedState.period
            });
            if (requestedState.search) params.set('search', requestedState.search);
            return params;
        }

        // Load one page of department groups (counts only — no rows).
        async function loadRequested(page = requestedState.page) {
            const requestId = ++requestedState.requestId;
            requestedState.loading = true;

            const loadingEl = document.getElementById('requested-loading');
            const noResults = document.getElementById('requested-no-results');
            const pagination = document.getElementById('requested-pagination');

            loadingEl?.classList.remove('hidden');
            noResults?.classList.add('hidden');
            pagination?.classList.add('hidden');

            try {
                const response = await fetch(
                    `/api/file-tracker-dashboard/requested-departments?${buildRequestedQuery(page, requestedState.perPage).toString()}`
                );
                const result = await response.json();

                // A newer request has already been fired — discard this response.
                if (requestId !== requestedState.requestId) return;

                if (!result.success) throw new Error(result.message || 'Request failed');

                requestedState.departments = result.data.departments || [];
                requestedState.departmentCounts = result.data.departmentCounts || [];
                requestedState.page = result.data.pagination.page;
                requestedState.lastPage = result.data.pagination.lastPage;
                requestedState.total = result.data.pagination.total;
                requestedState.from = result.data.pagination.from;
                requestedState.to = result.data.pagination.to;
                requestedState.stats = result.data.stats;
                requestedState.loaded = true;
                // Cached rows belong to the previous filter/period, so drop them.
                requestedState.rowsByDepartment = {};

                updateRequestedStats();
                renderRequestedTable();
                renderPagination('requested', requestedState, target => {
                    loadRequested(target);
                    document.getElementById('panel-requested')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                });
            } catch (error) {
                if (requestId !== requestedState.requestId) return;
                console.error('Error loading requested departments:', error);
                document.getElementById('requested-table-body').innerHTML = '';
                noResults?.classList.remove('hidden');
            } finally {
                if (requestId === requestedState.requestId) {
                    requestedState.loading = false;
                    loadingEl?.classList.add('hidden');
                }
            }
        }

        // One row per department group; a group's files are rendered underneath it
        // once expanded (and fetched on first expand).
        // Icon + accent per canonical department (App\Support\DepartmentNormalizer
        // folds every free-text value onto one of these names). Keys are lowercased.
        const DEPARTMENT_STYLES = {
            'all':               { icon: 'layers',        accent: '#475569', soft: '#f1f5f9', border: '#e2e8f0' },
            'csu':               { icon: 'headset',       accent: '#0891b2', soft: '#ecfeff', border: '#cffafe' },
            'land':              { icon: 'map',           accent: '#16a34a', soft: '#f0fdf4', border: '#dcfce7' },
            'survey':            { icon: 'ruler',         accent: '#a16207', soft: '#fefce8', border: '#fef08a' },
            'gis':               { icon: 'globe',         accent: '#0d9488', soft: '#f0fdfa', border: '#ccfbf1' },
            'gis/survey':        { icon: 'compass',       accent: '#0369a1', soft: '#f0f9ff', border: '#bae6fd' },
            'kangis':            { icon: 'database',      accent: '#4f46e5', soft: '#eef2ff', border: '#e0e7ff' },
            'account/finance':   { icon: 'wallet',        accent: '#059669', soft: '#ecfdf5', border: '#d1fae5' },
            'deeds':             { icon: 'scroll-text',   accent: '#b45309', soft: '#fffbeb', border: '#fde68a' },
            'physical planning': { icon: 'building-2',    accent: '#7c3aed', soft: '#f5f3ff', border: '#ede9fe' },
            'cadastral':         { icon: 'grid-3x3',      accent: '#db2777', soft: '#fdf2f8', border: '#fbcfe8' },
            'sectional titling': { icon: 'layers-3',      accent: '#c026d3', soft: '#fdf4ff', border: '#f5d0fe' },
            'sltr':              { icon: 'land-plot',     accent: '#ea580c', soft: '#fff7ed', border: '#ffedd5' },
            'ict':               { icon: 'cpu',           accent: '#2563eb', soft: '#eff6ff', border: '#dbeafe' },
            'dciv':              { icon: 'shield-check',  accent: '#dc2626', soft: '#fef2f2', border: '#fee2e2' },
            'unassigned':        { icon: 'circle-help',   accent: '#64748b', soft: '#f8fafc', border: '#e2e8f0' },
        };

        const DEPARTMENT_STYLE_FALLBACK = { icon: 'building-2', accent: '#64748b', soft: '#f8fafc', border: '#e2e8f0' };

        // Display-only suffix: KANGIS is an agency and Unassigned is a placeholder,
        // so neither reads as "… Department".
        const DEPARTMENT_LABEL_EXCEPTIONS = new Set(['kangis', 'unassigned']);

        function departmentLabel(department) {
            const name = String(department || '').trim();
            if (!name) return 'Unassigned';
            const key = name.toLowerCase();
            if (DEPARTMENT_LABEL_EXCEPTIONS.has(key) || key.endsWith('department')) return name;
            return `${name} Department`;
        }

        function departmentStyle(department) {
            return DEPARTMENT_STYLES[String(department || '').trim().toLowerCase()] || DEPARTMENT_STYLE_FALLBACK;
        }

        function departmentStyleVars(style) {
            return `--dept-accent:${style.accent};--dept-soft:${style.soft};--dept-border:${style.border};`;
        }

        function renderRequestedTable() {
            const tableBody = document.getElementById('requested-table-body');
            const noResults = document.getElementById('requested-no-results');
            const groups = requestedState.departments || [];

            if (groups.length === 0) {
                tableBody.innerHTML = '';
                noResults.classList.remove('hidden');
                return;
            }

            noResults.classList.add('hidden');

            tableBody.innerHTML = groups.map((group, groupIndex) => {
                const department = group.department;
                const expanded = requestedState.expandedDepartments.has(department);
                const total = group.totalFiles;
                const rows = requestedState.rowsByDepartment[department];
                const isLoading = requestedState.loadingDepartments.has(department);

                const style = departmentStyle(department);
                const styleVars = departmentStyleVars(style);
                const high = group.highFiles || 0;

                const header = `
                <tr class="department-group-row ${expanded ? 'is-expanded' : ''}" style="${styleVars}" data-dept-toggle="${groupIndex}" data-department="${escapeHtml(department)}" aria-expanded="${expanded}" title="Click to ${expanded ? 'collapse' : 'expand'}">
                    <td colspan="7" class="px-6 py-3">
                        <div class="flex items-center gap-3">
                            <i data-lucide="chevron-right" class="h-4 w-4 department-group-chevron"></i>
                            <span class="department-group-icon">
                                <i data-lucide="${style.icon}" class="h-4 w-4"></i>
                            </span>
                            <span class="department-group-name">${escapeHtml(departmentLabel(department))}</span>
                            <span class="department-group-count">${total} file${total === 1 ? '' : 's'}</span>
                            ${high > 0 ? `
                            <span class="department-group-high">
                                <i data-lucide="alert-triangle" class="h-3 w-3"></i>
                                ${high} high
                            </span>` : ''}
                            <span class="flex-1"></span>
                            ${isLoading
                                ? '<span class="department-group-note">loading…</span>'
                                : `<span class="department-group-hint">${expanded ? 'Hide files' : 'Show files'}</span>`}
                        </div>
                    </td>
                </tr>
                `;

                if (!expanded) return header;

                if (isLoading || !rows) {
                    return header + `
                    <tr class="department-group-loading" style="${styleVars}">
                        <td colspan="7" class="px-6 py-4 text-center text-sm text-gray-500">
                            <i data-lucide="loader-2" class="h-4 w-4 inline animate-spin mr-1"></i>
                            Loading ${escapeHtml(departmentLabel(department))} files...
                        </td>
                    </tr>
                    `;
                }

                return header + rows.map(tracker => requestedRowHtml(tracker, groupIndex, true, styleVars)).join('');
            }).join('');

            setupDepartmentGroupToggles();
            setupViewDetailsButtons();
            updateGroupToggleLabel(groups);
            lucide.createIcons();
        }

        function updateGroupToggleLabel(groups) {
            const label = document.getElementById('requested-toggle-groups-label');
            if (!label) return;

            const allExpanded = groups.length > 0
                && groups.every(g => requestedState.expandedDepartments.has(g.department));
            label.textContent = allExpanded ? 'Collapse all' : 'Expand all';
        }

        // Fetch (and cache) every requested file for one department.
        async function loadDepartmentRows(department) {
            if (requestedState.rowsByDepartment[department]
                || requestedState.loadingDepartments.has(department)) {
                return;
            }

            requestedState.loadingDepartments.add(department);

            const params = buildRequestedQuery(1, 1000);
            params.set('department', department);

            try {
                const response = await fetch(`/api/file-tracker-dashboard/requested?${params.toString()}`);
                const result = await response.json();
                requestedState.rowsByDepartment[department] = result.success ? (result.data.files || []) : [];
            } catch (error) {
                console.error(`Error loading files for ${department}:`, error);
                requestedState.rowsByDepartment[department] = [];
            } finally {
                requestedState.loadingDepartments.delete(department);
            }
        }

        // Toggle a department group open/closed, remembering the state so it
        // survives re-renders (search, refresh) within the session.
        function setupDepartmentGroupToggles() {
            document.querySelectorAll('#requested-table-body [data-dept-toggle]').forEach(header => {
                header.addEventListener('click', async function () {
                    const department = this.dataset.department;

                    if (requestedState.expandedDepartments.has(department)) {
                        requestedState.expandedDepartments.delete(department);
                        renderRequestedTable();
                        return;
                    }

                    requestedState.expandedDepartments.add(department);
                    renderRequestedTable();

                    if (!requestedState.rowsByDepartment[department]) {
                        await loadDepartmentRows(department);
                        renderRequestedTable();
                    }
                });
            });
        }

        // The officer the file is requested for (file_tracker.receiving_officer_name).
        function requesterName(tracker) {
            const name = (tracker.requester || '').trim();
            // Older rows stored the dropdown placeholder as a real value.
            if (!name || /^select receiving officer$/i.test(name)) return 'Unassigned';
            return name;
        }

        function requesterInitials(name) {
            if (name === 'Unassigned') return '?';
            // Names often carry a bracketed posting, e.g. "… (SECRET REGISTRY)".
            const parts = String(name).replace(/\(.*?\)/g, ' ').trim().split(/\s+/).filter(Boolean);
            if (parts.length === 0) return '?';
            return (parts[0][0] + (parts.length > 1 ? parts[1][0] : '')).toUpperCase();
        }

        function requestedRowHtml(tracker, groupIndex, visible, styleVars) {
            const priorityClass = `priority-${tracker.priority}`;
            const priorityText = tracker.priority.charAt(0) + tracker.priority.slice(1).toLowerCase();
            const statusInfo = getStatusInfo(tracker);
            const requester = requesterName(tracker);

            return `
                <tr class="file-row fade-in" data-dept-rows="${groupIndex}" style="${styleVars || ''}${visible ? '' : 'display:none;'}">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center">
                            <div class="department-group-icon department-group-icon-lg">
                                <i data-lucide="file-text" class="h-5 w-5"></i>
                            </div>
                            <div class="ml-4">
                                <div>
                                    <span class="file-no-badge file-no-badge-lead"><span class="dot"></span>${escapeHtml(tracker.fileNo)}</span>
                                </div>
                                <div class="file-title-sub mt-1">${escapeHtml(tracker.fileName)}</div>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm text-gray-900">${formatDate(tracker.requestedDate || tracker.requestDate)}</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${priorityClass}">${priorityText}</span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm text-gray-900">${escapeHtml(departmentLabel(tracker.department))}</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center gap-2">
                            <span class="requester-avatar">${escapeHtml(requesterInitials(requester))}</span>
                            <span class="text-sm text-gray-900">${escapeHtml(requester)}</span>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="status-badge ${statusInfo.class}">
                            <span class="dot"></span>
                            ${statusInfo.label}
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                        <button class="view-details-btn inline-flex items-center px-3 py-1.5 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50" data-tracking-id="${escapeHtml(tracker.trackingId)}">
                            <i data-lucide="eye" class="h-4 w-4 mr-1"></i>
                            View Details
                        </button>
                    </td>
                </tr>
            `;
        }

        // Setup view details buttons
        function setupViewDetailsButtons() {
            document.querySelectorAll('.view-details-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const trackingId = this.dataset.trackingId;
                    showTrackerDetails(trackingId);
                });
            });
        }

        // Show tracker details
        function showTrackerDetails(trackingId) {
            // Both grouped tabs fetch their rows a group at a time, so look through
            // whichever groups are currently expanded.
            const tracker = Object.values(transitState.rowsByOffice)
                    .flat()
                    .find(t => t.trackingId === trackingId)
                || Object.values(requestedState.rowsByDepartment)
                    .flat()
                    .find(t => t.trackingId === trackingId);
            if (!tracker) return;
            
            currentDetailsTracker = tracker;
            
            const priorityClass = `priority-${tracker.priority}`;
            const priorityText = tracker.priority.charAt(0) + tracker.priority.slice(1).toLowerCase();
            const activeEntry = tracker.isReturned ? null : getCurrentOutEntry(tracker);

            const detailsContent = document.getElementById('details-content');
            detailsContent.innerHTML = `
                <div class="py-4 space-y-6">
                    <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                        <div>
                            <h3 class="text-xl font-semibold">${tracker.fileName}</h3>
                            <p class="text-sm text-gray-600">File No: ${tracker.fileNo} | Tracking ID: ${tracker.trackingId}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${priorityClass}">${priorityText}</span>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">${tracker.currentOfficeId}</span>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-4">
                            <div>
                                <h4 class="text-sm font-medium text-gray-700 mb-2">File Information</h4>
                                <div class="space-y-2 text-sm">
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">File Name:</span>
                                        <span class="font-medium">${tracker.fileName}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">File No:</span>
                                        <span class="font-medium">${tracker.fileNo}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Tracking ID:</span>
                                        <span class="font-mono">${tracker.trackingId}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Priority:</span>
                                        <span class="font-medium ${priorityClass} px-2 py-0.5 rounded text-xs">${priorityText}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Department:</span>
                                        <span>${tracker.department || 'N/A'}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Case Type:</span>
                                        <span>${tracker.caseType || 'N/A'}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Requester:</span>
                                        <span class="font-medium">${requesterName(tracker)}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Request Date:</span>
                                        <span>${tracker.requestDate ? formatDate(tracker.requestDate) : 'N/A'}</span>
                                    </div>
                                    ${tracker.isRequested ? `
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Requested Date:</span>
                                        <span>${tracker.requestedDate ? formatDate(tracker.requestedDate) : 'N/A'}</span>
                                    </div>
                                    ` : ''}
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Created:</span>
                                        <span>${new Date(tracker.createdAt).toLocaleString()}</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div>
                                <h4 class="text-sm font-medium text-gray-700 mb-2">Current Location</h4>
                                <div class="space-y-2 text-sm">
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Office:</span>
                                        <span>${tracker.currentOffice}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Office ID:</span>
                                        <span class="font-mono">${tracker.currentOfficeId}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Department:</span>
                                        <span>${officeData[tracker.currentOfficeId]?.department || 'N/A'}</span>
                                    </div>
                                    ${activeEntry ? `
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Time in Office:</span>
                                        <span>${formatTimeDifference(activeEntry.createdAt)}</span>
                                    </div>
                                    <div class="mt-2 p-2 bg-blue-50 rounded border border-blue-100">
                                        <div class="text-xs font-medium text-blue-800 mb-1">Current Notes:</div>
                                        <div class="text-xs text-blue-700">${activeEntry.notes || 'No notes recorded'}</div>
                                    </div>
                                    ` : `
                                    <div class="mt-2 p-2 bg-gray-50 rounded border border-gray-200">
                                        <div class="text-xs font-medium text-gray-600 mb-1">Status:</div>
                                        <div class="text-xs text-gray-700">Not in transit</div>
                                    </div>
                                    `}
                                </div>
                            </div>
                        </div>
                        
                        <div>
                            <h4 class="text-sm font-medium text-gray-700 mb-2">Movement History</h4>
                            <div class="mb-3 p-2 bg-gray-50 rounded border border-gray-200 text-xs text-gray-700">
                                <span class="font-medium text-gray-500">Flow:</span> ${getMovementHistory(tracker)}
                            </div>
                            <div class="space-y-3 max-h-80 overflow-y-auto">
                                <div class="log-entry log-entry-completed p-3 rounded">
                                    <div class="flex items-center justify-between mb-1">
                                        <span class="text-sm font-medium">${tracker.originOffice || 'Registry'}</span>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-700">Origin</span>
                                    </div>
                                    <div class="text-xs text-gray-600">File logged out from the registry.</div>
                                </div>
                                ${tracker.logEntries.map((entry) => {
                                    const returned = !!entry.logOutDate;
                                    const st = (entry.status || '').toLowerCase();
                                    let label, badge, entryClass;
                                    if (returned) {
                                        label = 'Returned'; badge = 'bg-gray-100 text-gray-800'; entryClass = 'log-entry log-entry-completed';
                                    } else if (st === 'pending_acceptance') {
                                        label = 'Pending Acceptance'; badge = 'bg-amber-100 text-amber-800'; entryClass = 'log-entry log-entry-active';
                                    } else {
                                        label = 'In Transit'; badge = 'bg-green-100 text-green-800'; entryClass = 'log-entry log-entry-active';
                                    }
                                    return `
                                    <div class="${entryClass} p-3 rounded">
                                        <div class="flex items-center justify-between mb-1">
                                            <span class="text-sm font-medium">${entry.officeName || entry.officeId || 'Unknown office'}</span>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${badge}">${label}</span>
                                        </div>
                                        <div class="text-xs text-gray-600 space-y-1">
                                            ${entry.logId ? `<div>Log ID: <span class="font-mono">${entry.logId}</span></div>` : ''}
                                            ${entry.logInDate ? `<div>Received: ${entry.logInDate}${entry.logInTime ? ' at ' + entry.logInTime : ''}</div>` : ''}
                                            ${entry.logOutDate ? `<div>Returned to registry: ${entry.logOutDate}${entry.logOutTime ? ' at ' + entry.logOutTime : ''}</div>` : ''}
                                            ${entry.receivingOfficer ? `<div>Receiving Officer: ${entry.receivingOfficer}</div>` : ''}
                                            ${entry.handledBy ? `<div>Logged by: ${entry.handledBy}</div>` : ''}
                                            ${entry.notes ? `<div class="mt-1"><strong>Notes:</strong> ${entry.notes}</div>` : ''}
                                        </div>
                                    </div>
                                    `;
                                }).join('')}
                                ${tracker.isReturned ? `
                                <div class="log-entry log-entry-completed p-3 rounded">
                                    <div class="flex items-center justify-between mb-1">
                                        <span class="text-sm font-medium">↩ ${tracker.originOffice || 'Registry'}</span>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">Completed</span>
                                    </div>
                                    <div class="text-xs text-gray-600">File logged back into the registry.</div>
                                </div>
                                ` : ''}
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            document.getElementById('details-dialog').classList.add('show');
            lucide.createIcons();
        }

        // Filter files based on search and filters (reloads page 1 server-side)
        function filterFiles() {
            transitState.priority = document.getElementById('priority-filter').value || 'ALL';
            transitState.office = document.getElementById('office-filter').value || 'ALL';
            loadInTransit(1);
        }

        // Update charts
        function updateCharts() {
            updatePriorityChart();
            updateOfficeChart();
        }

        // Update priority chart
        function updatePriorityChart() {
            const ctx = document.getElementById('priority-chart').getContext('2d');

            // Whole-set counts from the API, not just the visible page.
            const priorityCounts = {
                HIGH: transitState.stats.high || 0,
                MEDIUM: transitState.stats.medium || 0,
                LOW: transitState.stats.low || 0
            };
            
            if (priorityChart) {
                priorityChart.destroy();
            }
            
            priorityChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['High Priority', 'Medium Priority', 'Low Priority'],
                    datasets: [{
                        data: [priorityCounts.HIGH, priorityCounts.MEDIUM, priorityCounts.LOW],
                        backgroundColor: [
                            '#EF4444',
                            '#F59E0B',
                            '#10B981'
                        ],
                        borderWidth: 2,
                        borderColor: '#fff',
                        hoverOffset: 15
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 20,
                                usePointStyle: true,
                                pointStyle: 'circle'
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.raw || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = Math.round((value / total) * 100);
                                    return `${label}: ${value} (${percentage}%)`;
                                }
                            }
                        }
                    },
                    cutout: '70%',
                    animation: {
                        animateScale: true,
                        animateRotate: true
                    }
                }
            });
        }

        // Update office chart
        function updateOfficeChart() {
            const ctx = document.getElementById('office-chart').getContext('2d');

            // Whole-set distribution from the API, not just the visible page.
            const topOffices = (transitState.officeDistribution || []).slice(0, 8);
            const labels = topOffices.map(o => o.officeName || o.officeCode);
            const data = topOffices.map(o => o.totalFiles);
            
            if (officeChart) {
                officeChart.destroy();
            }
            
            officeChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Files in Office',
                        data: data,
                        backgroundColor: 'rgba(59, 130, 246, 0.7)',
                        borderColor: 'rgb(59, 130, 246)',
                        borderWidth: 1,
                        borderRadius: 4,
                        hoverBackgroundColor: 'rgba(59, 130, 246, 1)'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return `Files: ${context.raw}`;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            },
                            grid: {
                                drawBorder: false
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    },
                    animation: {
                        duration: 1000,
                        easing: 'easeOutQuart'
                    }
                }
            });
        }

        // Toggle charts visibility
        function toggleCharts() {
            const chartsSection = document.getElementById('charts-section');
            const toggleBtn = document.getElementById('toggleChartsBtn');
            
            chartsVisible = !chartsVisible;
            
            if (chartsVisible) {
                chartsSection.classList.add('show');
                toggleBtn.innerHTML = '<i data-lucide="x" class="h-4 w-4 mr-1"></i><span>Hide Charts</span>';
                toggleBtn.classList.remove('bg-purple-50');
                toggleBtn.classList.add('bg-purple-100');
                
                if (!priorityChart || !officeChart) {
                    setTimeout(updateCharts, 300);
                } else {
                    updateCharts();
                }
            } else {
                chartsSection.classList.remove('show');
                toggleBtn.innerHTML = '<i data-lucide="bar-chart-3" class="h-4 w-4 mr-1"></i><span>Show Charts</span>';
                toggleBtn.classList.remove('bg-purple-100');
                toggleBtn.classList.add('bg-purple-50');
            }
            
            lucide.createIcons();
        }

        // What the sheet was last generated with, so the department dropdown can
        // regenerate over the same period without re-asking for it.
        const sheetState = { period: 'all', customStart: null, customEnd: null, department: '' };

        // Identifies the newest generate run, so a slow one cannot overwrite a
        // newer sheet when the user changes the department mid-fetch.
        let sheetRunId = 0;

        // The endpoint caps per_page at 1000. The sheet used to request a single
        // page of that size and present it as the whole set — with ~20k requested
        // files that silently dropped everything past the first department or two.
        const SHEET_PAGE_SIZE = 1000;

        // A print sheet that renders every row would be tens of thousands of table
        // rows in one modal. Past this the sheet says so and asks for a department.
        const SHEET_MAX_ROWS = 5000;

        // Pull the whole requested set (not just the visible page) for the sheet,
        // paging until the server says there is nothing left. The period is applied
        // server-side; custom ranges are trimmed afterwards.
        async function fetchAllRequested(period, department = '') {
            const serverPeriod = ['weekly', 'monthly', 'quarterly'].includes(period) ? period : 'all';

            const fetchPage = async page => {
                const params = new URLSearchParams({ page, per_page: SHEET_PAGE_SIZE, period: serverPeriod });
                if (requestedState.search) params.set('search', requestedState.search);
                if (department) params.set('department', department);

                const response = await fetch(`/api/file-tracker-dashboard/requested?${params.toString()}`);
                const result = await response.json();
                return result.success ? result.data : null;
            };

            try {
                // Page 1 also reports how many there are, so the rest can be
                // fetched together instead of one round trip at a time.
                const first = await fetchPage(1);
                if (!first) return { files: [], truncated: false };

                const lastPage = first.pagination?.lastPage || 1;
                const maxPages = Math.min(lastPage, Math.ceil(SHEET_MAX_ROWS / SHEET_PAGE_SIZE));

                const rest = [];
                for (let page = 2; page <= maxPages; page++) rest.push(page);

                const pages = await Promise.all(rest.map(fetchPage));

                const files = [...(first.files || [])];
                pages.forEach(data => files.push(...(data?.files || [])));

                return { files, truncated: maxPages < lastPage };
            } catch (error) {
                console.error('Error loading requested files for the sheet:', error);
                return { files: [], truncated: false };
            }
        }

        // The dropdown lists every department in the requested set, not just the
        // ones on the current page of the table.
        async function populateSheetDepartments() {
            const select = document.getElementById('sheet-department');
            if (!select) return;

            // The counts are period-specific, so they are rebuilt whenever the
            // sheet is generated for a different period.
            const serverPeriod = ['weekly', 'monthly', 'quarterly'].includes(sheetState.period)
                ? sheetState.period
                : 'all';
            if (select.dataset.loadedPeriod === serverPeriod) return;

            try {
                const params = new URLSearchParams({ page: 1, per_page: 200, period: serverPeriod });
                const response = await fetch(`/api/file-tracker-dashboard/requested-departments?${params.toString()}`);
                const result = await response.json();
                if (!result.success) return;

                const selected = select.value;
                select.innerHTML = '<option value="">All departments</option>';

                (result.data.departmentCounts || []).forEach(group => {
                    const option = document.createElement('option');
                    option.value = group.department;
                    option.textContent = `${departmentLabel(group.department)} (${group.totalFiles})`;
                    select.appendChild(option);
                });

                select.value = selected;
                select.dataset.loadedPeriod = serverPeriod;
            } catch (error) {
                console.error('Error loading departments for the sheet:', error);
            }
        }

        // Generate Request Sheet based on period, grouped by department.
        async function generateRequestSheet(period, customStart = null, customEnd = null, department = null) {
            let requestedFiles = [];
            const now = new Date();
            let startDate, endDate;
            let periodLabel = '';

            sheetState.period = period;
            sheetState.customStart = customStart;
            sheetState.customEnd = customEnd;
            if (department !== null) sheetState.department = department;

            // Several pages may be fetched, so show the sheet as pending rather
            // than leaving the button looking dead.
            const token = ++sheetRunId;
            document.getElementById('requestSheetContent').innerHTML =
                '<div style="padding:3rem; text-align:center; color:#64748b;">Building sheet…</div>';
            document.getElementById('requestSheetOverlay').classList.add('show');
            populateSheetDepartments();

            const { files: allRequested, truncated } = await fetchAllRequested(period, sheetState.department);

            // A later run (e.g. the dropdown changed again) already owns the sheet.
            if (token !== sheetRunId) return;

            if (period === 'weekly') {
                startDate = new Date(now);
                startDate.setDate(now.getDate() - 7);
                endDate = now;
                periodLabel = 'Weekly Request Sheet';
                requestedFiles = allRequested.filter(f => {
                    const reqDate = new Date(f.requestedDate || f.requestDate);
                    return reqDate >= startDate && reqDate <= endDate;
                });
            } else if (period === 'monthly') {
                startDate = new Date(now);
                startDate.setMonth(now.getMonth() - 1);
                endDate = now;
                periodLabel = 'Monthly Request Sheet';
                requestedFiles = allRequested.filter(f => {
                    const reqDate = new Date(f.requestedDate || f.requestDate);
                    return reqDate >= startDate && reqDate <= endDate;
                });
            } else if (period === 'quarterly') {
                startDate = new Date(now);
                startDate.setMonth(now.getMonth() - 3);
                endDate = now;
                periodLabel = 'Quarterly Request Sheet';
                requestedFiles = allRequested.filter(f => {
                    const reqDate = new Date(f.requestedDate || f.requestDate);
                    return reqDate >= startDate && reqDate <= endDate;
                });
            } else if (period === 'custom' && customStart && customEnd) {
                startDate = new Date(customStart);
                endDate = new Date(customEnd);
                periodLabel = `Custom Request Sheet (${formatDate(customStart)} - ${formatDate(customEnd)})`;
                requestedFiles = allRequested.filter(f => {
                    const reqDate = new Date(f.requestedDate || f.requestDate);
                    return reqDate >= startDate && reqDate <= endDate;
                });
            } else {
                // Default: show all requested files
                startDate = new Date(2000, 0, 1);
                endDate = now;
                periodLabel = 'All Requested Files';
                requestedFiles = allRequested;
            }

            // Group by department, newest request first within each group.
            // Departments read alphabetically; files with none sort last.
            const groupOf = file => (file.department || '').trim() || 'Unassigned';

            const grouped = new Map();
            requestedFiles.forEach(file => {
                const key = groupOf(file);
                if (!grouped.has(key)) grouped.set(key, []);
                grouped.get(key).push(file);
            });

            const groups = [...grouped.entries()]
                .sort(([a], [b]) => {
                    if (a === 'Unassigned') return 1;
                    if (b === 'Unassigned') return -1;
                    return a.localeCompare(b);
                })
                .map(([name, files]) => ({
                    name,
                    files: files.sort((a, b) => {
                        const dateA = new Date(a.requestedDate || a.requestDate);
                        const dateB = new Date(b.requestedDate || b.requestDate);
                        return dateB - dateA;
                    }),
                }));

            const totalFiles = requestedFiles.length;
            const highPriority = requestedFiles.filter(f => f.priority === 'HIGH').length;
            const mediumPriority = requestedFiles.filter(f => f.priority === 'MEDIUM').length;
            const lowPriority = requestedFiles.filter(f => f.priority === 'LOW').length;
            const activeFiles = requestedFiles.filter(f => !f.isReturned && !f.isCanceled).length;
            const completedFiles = requestedFiles.filter(f => f.isReturned).length;
            const scopeLabel = sheetState.department
                ? `${sheetState.department} only`
                : `${groups.length} department${groups.length === 1 ? '' : 's'}`;

            let html = `
                <div class="header">
                    <h1>File Request Sheet</h1>
                    <div class="subtitle">Honorable Commissioner's Office · KLAES File Tracking System</div>
                    <div class="meta-info">
                        <span><strong>Period:</strong> ${periodLabel}</span>
                        <span><strong>Generated:</strong> ${new Date().toLocaleString()}</span>
                        <span><strong>Total Files:</strong> ${totalFiles}</span>
                        <span><strong>Departments:</strong> ${scopeLabel}</span>
                    </div>
                </div>
`;

            if (truncated) {
                html += `
                    <div style="margin-bottom:1rem; padding:0.6rem 0.8rem; border:1px solid #fcd34d; background:#fffbeb; color:#92400e; font-size:0.8rem; border-radius:4px;">
                        Showing the first ${totalFiles.toLocaleString()} files only — the full set is larger than one sheet.
                        Pick a department above to print a complete list for that department.
                    </div>
                `;
            }

            html += `

                <div class="summary-stats">
                    <div class="stat-item">
                        <div class="stat-value">${totalFiles}</div>
                        <div class="stat-label">Total Requested</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value" style="color:#065f46;">${activeFiles}</div>
                        <div class="stat-label">In Transit</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value" style="color:#475569;">${completedFiles}</div>
                        <div class="stat-label">Completed</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value" style="color:#991b1b;">${highPriority}</div>
                        <div class="stat-label">High Priority</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value" style="color:#92400e;">${mediumPriority}</div>
                        <div class="stat-label">Medium Priority</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value" style="color:#065f46;">${lowPriority}</div>
                        <div class="stat-label">Low Priority</div>
                    </div>
                </div>

                ${groups.length > 1 ? `
                    <div style="margin-bottom:0.9rem; font-size:0.75rem; color:#334155;">
                        <strong style="display:block; margin-bottom:0.3rem;">Department breakdown</strong>
                        ${groups.map(g => `<span style="display:inline-block; margin:0 0.9rem 0.2rem 0;">${departmentLabel(g.name)}: <strong>${g.files.length}</strong></span>`).join('')}
                    </div>
                ` : ''}

                <table>
                    <thead>
                        <tr>
                            <th style="width:3%;">#</th>
                            <th style="width:12%;">File No.</th>
                            <th style="width:14%;">File Name</th>
                            <th style="width:6%;">Priority</th>
                            <th style="width:11%;">Requester</th>
                            <th style="width:10%;">Requester Office</th>
                            <th style="width:10%;">Requester Department</th>
                            <th style="width:9%;">Requested Date</th>
                            <th style="width:7%;">Status</th>
                            <th style="width:18%;">Movement History</th>
                        </tr>
                    </thead>
                    <tbody>
            `;

            if (requestedFiles.length === 0) {
                html += `
                    <tr>
                        <td colspan="10" style="text-align:center; padding:2rem; color:#94a3b8; font-size:1rem;">
                            📭 No files have been requested during the selected period.
                        </td>
                    </tr>
                `;
            } else {
                // Rows carry their own department column too — the group header
                // is the grouping key, the column is the requester's posting.
                groups.forEach(group => {
                    const groupHigh = group.files.filter(f => f.priority === 'HIGH').length;

                    html += `
                        <tr>
                            <td colspan="10" style="background:#eef2ff; border-top:2px solid #6366f1; font-weight:700; text-transform:uppercase; letter-spacing:0.03em; color:#312e81; padding:0.45rem 0.6rem;">
                                ${departmentLabel(group.name)}
                                <span style="float:right; font-weight:600; text-transform:none; letter-spacing:0; color:#4338ca;">
                                    ${group.files.length} file${group.files.length === 1 ? '' : 's'} · ${groupHigh} high priority
                                </span>
                            </td>
                        </tr>
                    `;

                    // Numbering restarts per department so each group reads as its
                    // own list when the sheet is split up and circulated.
                    group.files.forEach((file, index) => {
                        const priorityClass = file.priority.toLowerCase();
                        const statusInfo = getStatusInfo(file);
                        const movement = getMovementHistory(file);
                        const entries = file.logEntries || [];
                        const lastEntry = entries[entries.length - 1];
                        // Same rule as the In-Transit tab: show the office the file
                        // was requested to, and fall back to the department when
                        // that office is the "Department Queue" holding bucket.
                        const requesterOfficeName = file.requesterOffice
                            || officeData[file.currentOfficeId]?.name
                            || file.currentOffice
                            || 'Unknown';
                        const requesterDepartment = file.requesterOfficeDepartment || file.department || '';
                        const isDepartmentQueue = /department\s*queue/i.test(requesterOfficeName);
                        const requesterOffice = isDepartmentQueue
                            ? (requesterDepartment || requesterOfficeName)
                            : requesterOfficeName;

                        html += `
                            <tr>
                                <td style="text-align:center; font-weight:600;">${index + 1}</td>
                                <td><strong>${file.fileNo}</strong></td>
                                <td>${file.fileName}</td>
                                <td><span class="badge-priority ${priorityClass}">${file.priority}</span></td>
                                <td>${requesterName(file)}</td>
                                <td>${requesterOffice}</td>
                                <td>${requesterDepartment ? departmentLabel(requesterDepartment) : '—'}</td>
                                <td>${file.requestedDate ? formatDate(file.requestedDate) : formatDate(file.requestDate)}</td>
                                <td><span class="status-badge ${statusInfo.class}">${statusInfo.label}</span></td>
                                <td>
                                    <div class="movement-history">
                                        ${movement !== 'No movement recorded' ? movement : 'No movement recorded'}
                                        ${lastEntry ? `<div style="margin-top:2px; font-size:0.65rem; color:#94a3b8;">Last: ${lastEntry.officeName} (${formatTimeDifference(lastEntry.createdAt)})</div>` : ''}
                                    </div>
                                </td>
                            </tr>
                        `;
                    });
                });
            }

            html += `
                    </tbody>
                </table>

                <div class="footer">
                    <div>
                        <strong>Commissioner's Signature:</strong>
                        <span class="signature-line"></span>
                        <span style="margin-left:1rem;">Date: ___________</span>
                    </div>
                    <div>
                        <span>Page 1 of 1</span>
                    </div>
                </div>
                <div style="text-align:center; font-size:0.7rem; color:#94a3b8; margin-top:1rem; padding-top:0.5rem; border-top:1px solid #e2e8f0;">
                    This is a system-generated document. All information is based on the latest file tracking data. 
                    For any discrepancies, please contact the Records Department.
                </div>
            `;

            document.getElementById('requestSheetContent').innerHTML = html;
            document.getElementById('requestSheetOverlay').classList.add('show');

            const departmentSelect = document.getElementById('sheet-department');
            if (departmentSelect) departmentSelect.value = sheetState.department;
            populateSheetDepartments();

            lucide.createIcons();
        }

        // Switch tabs
        function switchTab(tab) {
            currentTab = tab;
            
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            document.querySelector(`[data-tab="${tab}"]`).classList.add('active');
            
            document.querySelectorAll('.tab-panel').forEach(panel => {
                panel.classList.remove('active-panel');
            });
            document.getElementById(`panel-${tab}`).classList.add('active-panel');
            
            if (tab === 'transit') {
                // Loaded lazily the first time the tab is opened.
                if (!transitState.loaded && !transitState.loading) loadInTransit(transitState.page);
                else if (chartsVisible) updateCharts();
            } else if (tab === 'idle') {
                // Loaded lazily the first time the tab is opened.
                if (!idleState.loaded && !idleState.loading) loadNotInTransit(idleState.page);
            } else if (tab === 'requested') {
                if (!requestedState.loaded && !requestedState.loading) loadRequested(requestedState.page);
            }
        }

        // Initialize dashboard
        function initializeDashboard() {
            // Set default tab to requested
            currentTab = 'requested';
            
            // Requested Files is the default tab, so it loads immediately; the
            // other two load the first time their tab is opened.
            loadRequested(1);

            document.getElementById('transit-per-page')?.addEventListener('change', function () {
                transitState.perPage = parseInt(this.value, 10) || 25;
                loadInTransit(1);
            });

            // Expand / collapse every office group on the current page.
            document.getElementById('transit-toggle-groups')?.addEventListener('click', async function () {
                const codes = (transitState.offices || []).map(o => o.officeCode);
                const allExpanded = codes.length > 0
                    && codes.every(c => transitState.expandedOffices.has(c));

                if (allExpanded) {
                    codes.forEach(c => transitState.expandedOffices.delete(c));
                    renderTransitTable();
                    return;
                }

                codes.forEach(c => transitState.expandedOffices.add(c));
                renderTransitTable();

                await Promise.all(codes.map(c => loadOfficeRows(c)));
                renderTransitTable();
            });

            document.getElementById('requested-search')?.addEventListener('input', function () {
                const value = this.value.trim();
                clearTimeout(requestedSearchTimer);
                requestedSearchTimer = setTimeout(() => {
                    requestedState.search = value;
                    loadRequested(1);
                }, 350);
            });

            document.getElementById('requested-per-page')?.addEventListener('change', function () {
                requestedState.perPage = parseInt(this.value, 10) || 25;
                loadRequested(1);
            });

            // Expand / collapse every department group on the current page.
            document.getElementById('requested-toggle-groups')?.addEventListener('click', async function () {
                const departments = (requestedState.departments || []).map(d => d.department);
                const allExpanded = departments.length > 0
                    && departments.every(d => requestedState.expandedDepartments.has(d));

                if (allExpanded) {
                    departments.forEach(d => requestedState.expandedDepartments.delete(d));
                    renderRequestedTable();
                    return;
                }

                departments.forEach(d => requestedState.expandedDepartments.add(d));
                renderRequestedTable();

                await Promise.all(departments.map(d => loadDepartmentRows(d)));
                renderRequestedTable();
            });

            // Not in Transit is served by its own paginated endpoint and is
            // fetched the first time that tab is opened (see switchTab).
            document.getElementById('idle-search')?.addEventListener('input', function () {
                const value = this.value.trim();
                clearTimeout(idleSearchTimer);
                idleSearchTimer = setTimeout(() => {
                    idleState.search = value;
                    loadNotInTransit(1);
                }, 350);
            });

            document.getElementById('idle-per-page')?.addEventListener('change', function () {
                idleState.perPage = parseInt(this.value, 10) || 25;
                loadNotInTransit(1);
            });

            // Tab switching
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    switchTab(this.dataset.tab);
                });
            });
            
            // Set up event listeners — the search hits the API, so it is debounced.
            document.getElementById('search-input').addEventListener('input', function () {
                const value = this.value.trim();
                clearTimeout(transitSearchTimer);
                transitSearchTimer = setTimeout(() => {
                    transitState.search = value;
                    filterFiles();
                }, 350);
            });
            document.getElementById('priority-filter').addEventListener('change', filterFiles);
            document.getElementById('office-filter').addEventListener('change', filterFiles);
            document.getElementById('clear-filters').addEventListener('click', () => {
                document.getElementById('search-input').value = '';
                document.getElementById('priority-filter').value = 'ALL';
                document.getElementById('office-filter').value = 'ALL';
                transitState.search = '';
                filterFiles();
            });
            
            // Requested period filter — applied server-side, so reload page 1.
            document.getElementById('requested-filter').addEventListener('change', function () {
                requestedState.period = this.value === 'ALL' ? 'all' : this.value;
                loadRequested(1);
            });
            
            // Generate requested sheet — opens on every department; the sheet's own
            // dropdown narrows it from there.
            document.getElementById('generateRequestedSheet').addEventListener('click', () => {
                const filter = document.getElementById('requested-filter').value;
                if (filter === 'ALL') {
                    generateRequestSheet('all', null, null, '');
                } else {
                    generateRequestSheet(filter, null, null, '');
                }
            });
            
            // Priority chart filter buttons
            document.querySelectorAll('.priority-filter-btn').forEach(button => {
                button.addEventListener('click', function() {
                    document.querySelectorAll('.priority-filter-btn').forEach(btn => {
                        btn.classList.remove('bg-blue-100', 'text-blue-700');
                        btn.classList.add('text-gray-500', 'hover:bg-gray-100');
                    });
                    this.classList.remove('text-gray-500', 'hover:bg-gray-100');
                    this.classList.add('bg-blue-100', 'text-blue-700');
                    
                    const priority = this.dataset.priority;
                    document.getElementById('priority-filter').value = priority;
                    filterFiles();
                });
            });
            
            // Office chart filter buttons
            document.querySelectorAll('.office-filter-btn').forEach(button => {
                button.addEventListener('click', function() {
                    document.querySelectorAll('.office-filter-btn').forEach(btn => {
                        btn.classList.remove('bg-blue-100', 'text-blue-700');
                        btn.classList.add('text-gray-500', 'hover:bg-gray-100');
                    });
                    this.classList.remove('text-gray-500', 'hover:bg-gray-100');
                    this.classList.add('bg-blue-100', 'text-blue-700');
                });
            });
            
            document.getElementById('refreshBtn')?.addEventListener('click', () => {
                loadInTransit(transitState.page);
                loadNotInTransit(idleState.page);
                loadRequested(requestedState.page);
                const icon = document.querySelector('#refreshBtn i');
                icon?.classList.add('animate-spin');
                setTimeout(() => {
                    icon?.classList.remove('animate-spin');
                }, 1000);
            });

            document.getElementById('toggleChartsBtn').addEventListener('click', toggleCharts);

            // Print menu
            document.getElementById('printMenuBtn')?.addEventListener('click', function(e) {
                e.stopPropagation();
                document.getElementById('printDropdown').classList.toggle('show');
            });
            
            document.addEventListener('click', function() {
                document.getElementById('printDropdown').classList.remove('show');
            });
            
            document.querySelectorAll('.print-dropdown-item').forEach(item => {
                item.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const period = this.dataset.period;
                    if (period === 'custom') {
                        const start = prompt('Enter start date (YYYY-MM-DD):');
                        if (!start) return;
                        const end = prompt('Enter end date (YYYY-MM-DD):');
                        if (!end) return;
                        generateRequestSheet('custom', start, end, '');
                    } else {
                        generateRequestSheet(period, null, null, '');
                    }
                    document.getElementById('printDropdown').classList.remove('show');
                });
            });
            
            // Department filter inside the sheet — regenerates over the period the
            // sheet was opened with, so the two filters compose.
            document.getElementById('sheet-department')?.addEventListener('change', function () {
                generateRequestSheet(
                    sheetState.period,
                    sheetState.customStart,
                    sheetState.customEnd,
                    this.value
                );
            });

            // Request sheet close
            document.getElementById('closeRequestSheet').addEventListener('click', () => {
                document.getElementById('requestSheetOverlay').classList.remove('show');
            });
            
            // Click outside to close
            document.getElementById('requestSheetOverlay').addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('show');
                }
            });
            
            // Details dialog event listeners
            document.getElementById('close-details').addEventListener('click', () => {
                document.getElementById('details-dialog').classList.remove('show');
            });
            document.getElementById('close-details-btn').addEventListener('click', () => {
                document.getElementById('details-dialog').classList.remove('show');
            });
            document.getElementById('print-details-btn').addEventListener('click', () => {
                if (currentDetailsTracker) {
                    // Mark as requested if not already
                    if (!currentDetailsTracker.isRequested) {
                        currentDetailsTracker.isRequested = true;
                        currentDetailsTracker.requestedDate = new Date().toISOString().split('T')[0];
                        loadRequested(requestedState.page);
                    }
                    generateRequestSheet('custom',
                        currentDetailsTracker.requestedDate,
                        currentDetailsTracker.requestedDate
                    );
                    document.getElementById('details-dialog').classList.remove('show');
                }
            });
        }

        // Initialize when the page loads
        document.addEventListener('DOMContentLoaded', () => {
            initializeDashboard();
        });


