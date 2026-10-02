(function () {
    class FileHistoryPage {
        constructor(payload) {
            this.data = payload || {};
            this.activeTab = 'timeline';
            this.selectors = {
                fileNumberLabel: '#fileNumberLabel',
                fileNumberDisplay: '#fileNumberDisplay',
                totalTransactions: '#totalTransactions',
                landUse: '#landUse',
                timelineContainer: '#timeline-items',
                detailsContainer: '#transaction-details',
                summaryGrid: '#summary-grid',
                summaryList: '#transactions-list',
                tabTriggers: '.tabs-trigger',
                tabPanels: '.tab-panel',
                timelineEmpty: '#timeline-empty',
                detailsEmpty: '#details-empty',
                summaryEmpty: '#summary-empty',
            };
        }

        init() {
            this.cacheDom();
            this.bindTabEvents();
            this.renderHeader();
            this.renderTimeline();
            this.renderDetails();
            this.renderSummary();
        }

        cacheDom() {
            this.elements = {};
            Object.entries(this.selectors).forEach(([key, selector]) => {
                if (selector.startsWith('#')) {
                    this.elements[key] = document.querySelector(selector);
                } else {
                    this.elements[key] = document.querySelectorAll(selector);
                }
            });
        }

        bindTabEvents() {
            this.elements.tabTriggers.forEach((trigger) => {
                trigger.addEventListener('click', () => {
                    const target = trigger.getAttribute('data-tab');
                    this.switchTab(target);
                });
            });
        }

        switchTab(tabName) {
            this.activeTab = tabName;
            this.elements.tabTriggers.forEach((trigger) => {
                trigger.classList.toggle('active', trigger.getAttribute('data-tab') === tabName);
            });

            this.elements.tabPanels.forEach((panel) => {
                panel.classList.toggle('active', panel.id === `${tabName}-tab`);
            });
        }

        renderHeader() {
            const { fileNumber, landuse, totalTransactions } = this.data;
            if (this.elements.fileNumberLabel) {
                this.elements.fileNumberLabel.textContent = fileNumber || '—';
            }
            if (this.elements.fileNumberDisplay) {
                this.elements.fileNumberDisplay.textContent = fileNumber || '—';
            }
            if (this.elements.totalTransactions) {
                this.elements.totalTransactions.textContent = totalTransactions ?? 0;
            }
            if (this.elements.landUse) {
                this.elements.landUse.textContent = landuse || '—';
            }
        }

        renderTimeline() {
            const container = this.elements.timelineContainer;
            if (!container) return;

            const transactions = this.data.transactions || [];
            container.innerHTML = '';

            if (!transactions.length) {
                this.toggleEmptyState('timelineEmpty', true);
                return;
            }
            this.toggleEmptyState('timelineEmpty', false);

            transactions.forEach((transaction, index) => {
                const property = transaction.propertyDetails || { area: '—', location: '—' };
                const wrapper = document.createElement('div');
                wrapper.className = 'timeline-item';
                wrapper.innerHTML = `
                    <div class="timeline-node">
                        <div class="timeline-node-circle">
                            <div class="timeline-node-number">${index + 1}</div>
                        </div>
                    </div>
                    <div class="flex-1 mt-2">
                        <div class="card hover:shadow-lg transition-shadow">
                            <div class="card-content">
                                <div class="space-y-4">
                                    <div class="flex items-start justify-between">
                                        <div class="flex items-start gap-3">
                                            <svg class="icon-md text-gray-600 mt-1 flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                                <circle cx="12" cy="7" r="4"></circle>
                                            </svg>
                                            <div>
                                                <p class="text-xs text-gray-500 uppercase tracking-wide">
                                                    ${index === 0 ? 'Original Holder' : 'Current Holder'}
                                                </p>
                                                <p class="text-lg font-bold text-gray-900">${transaction.owner ?? 'Unknown'}</p>
                                                <div class="badge badge-outline mt-1 text-xs capitalize">
                                                    ${transaction.transactionType}
                                                </div>
                                            </div>
                                        </div>
                                        ${index < transactions.length - 1 ? `
                                            <div class="timeline-arrow">
                                                <svg class="icon-md" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <polyline points="6 9 12 15 18 9"></polyline>
                                                </svg>
                                            </div>
                                        ` : ''}
                                    </div>
                                    <div class="flex items-center gap-3 text-sm">
                                        <svg class="icon-sm text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                            <line x1="16" y1="2" x2="16" y2="6"></line>
                                            <line x1="8" y1="2" x2="8" y2="6"></line>
                                            <line x1="3" y1="10" x2="21" y2="10"></line>
                                        </svg>
                                        <span class="text-gray-700">${transaction.date_display ?? 'Date unavailable'}</span>
                                    </div>
                                    <div class="bg-gray-50 rounded-lg p-3 space-y-2">
                                        <div class="flex items-start gap-3">
                                            <svg class="icon-sm text-amber-600 mt-0.5 flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                                <polyline points="14 2 14 8 20 8"></polyline>
                                                <line x1="16" y1="13" x2="8" y2="13"></line>
                                                <line x1="16" y1="17" x2="8" y2="17"></line>
                                            </svg>
                                            <div>
                                                <p class="text-xs text-gray-600 uppercase tracking-wide">Transaction Details</p>
                                                <p class="font-semibold text-gray-900">${transaction.transactionType}</p>
                                                <p class="text-xs text-gray-600 mt-1">From: ${transaction.originalHolder ?? 'Unknown'}</p>
                                            </div>
                                        </div>
                                                    ${transaction.description ? `<p class="text-sm text-gray-700 ml-7">${transaction.description}</p>` : ''}
                                            </div>
                                            <div class="bg-green-50 rounded-lg p-3 space-y-2">
                                                <p class="text-xs font-semibold text-gray-900 uppercase tracking-wide">Property Details</p>
                                                <div class="space-y-1">
                                                    <div class="flex items-center gap-2 text-sm">
                                                        <svg class="icon-sm text-gray-600 flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                                                            <circle cx="12" cy="10" r="3"></circle>
                                                        </svg>
                                                        <span class="text-gray-700">${property.location}</span>
                                                    </div>
                                                    <p class="text-sm text-gray-700 ml-6">Land Use: ${property.area}</p>
                                                </div>
                                            </div>
                                    <div class="text-sm border-t pt-3">
                                        <p class="text-xs text-gray-600 uppercase tracking-wide mb-2">Registration Details</p>
                                        <div class="grid grid-cols-3 gap-2 text-xs">
                                            <div>
                                                <p class="text-gray-600">Serial No:</p>
                                                <p class="font-semibold text-gray-900">${transaction.serialNo}</p>
                                            </div>
                                            <div>
                                                <p class="text-gray-600">Page No:</p>
                                                <p class="font-semibold text-gray-900">${transaction.pageNo}</p>
                                            </div>
                                            <div>
                                                <p class="text-gray-600">Vol No:</p>
                                                <p class="font-semibold text-gray-900">${transaction.volNo}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                container.appendChild(wrapper);
            });
        }

        renderDetails() {
            const container = this.elements.detailsContainer;
            if (!container) return;
            const transactions = this.data.transactions || [];
            container.innerHTML = '';

            if (!transactions.length) {
                this.toggleEmptyState('detailsEmpty', true);
                return;
            }
            this.toggleEmptyState('detailsEmpty', false);

            transactions.forEach((transaction, index) => {
                const property = transaction.propertyDetails || { area: '—', location: '—' };
                const card = document.createElement('div');
                card.className = 'card';
                card.innerHTML = `
                    <div class="card-header pb-3">
                        <div class="flex items-start justify-between">
                            <div>
                                <h3 class="card-title">Transaction ${index + 1}: ${transaction.transactionType}</h3>
                                <p class="text-sm text-gray-600 mt-1">${transaction.date_display ?? 'Date unavailable'}</p>
                            </div>
                            <div class="badge ${index === transactions.length - 1 ? 'badge-default' : 'badge-secondary'}">
                                ${index === transactions.length - 1 ? 'Current' : 'Historical'}
                            </div>
                        </div>
                    </div>
                    <div class="card-content space-y-3">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-xs text-gray-600 uppercase">Original Holder</p>
                                <p class="font-semibold text-gray-900">${transaction.originalHolder}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-600 uppercase">Current Holder</p>
                                <p class="font-semibold text-gray-900">${transaction.owner}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-600 uppercase">Transaction Type</p>
                                <p class="font-semibold text-gray-900">${transaction.transactionType}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-600 uppercase">Land Use</p>
                                <p class="font-semibold text-gray-900">${property.area}</p>
                            </div>
                        </div>
                        <div class="grid grid-cols-3 gap-4 text-sm">
                            <div>
                                <p class="text-xs text-gray-600 uppercase">Serial No</p>
                                <p class="font-semibold text-gray-900">${transaction.serialNo}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-600 uppercase">Page No</p>
                                <p class="font-semibold text-gray-900">${transaction.pageNo}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-600 uppercase">Vol No</p>
                                <p class="font-semibold text-gray-900">${transaction.volNo}</p>
                            </div>
                        </div>
                        ${transaction.description ? `
                            <div>
                                <p class="text-xs text-gray-600 uppercase mb-1">Description</p>
                                <p class="text-sm text-gray-700">${transaction.description}</p>
                            </div>
                        ` : ''}
                        <div class="bg-gray-50 rounded p-3 text-sm">
                            <p class="text-xs text-gray-600 uppercase mb-2">Property Location</p>
                            <p class="font-semibold text-gray-900">${property.location}</p>
                        </div>
                    </div>
                `;
                container.appendChild(card);
            });
        }

        renderSummary() {
            const grid = this.elements.summaryGrid;
            const list = this.elements.summaryList;
            if (!grid || !list) return;

            const transactions = this.data.transactions || [];
            if (!transactions.length) {
                this.toggleEmptyState('summaryEmpty', true);
                return;
            }
            this.toggleEmptyState('summaryEmpty', false);

            // Rows arrive in the Legal Search timeline order (weight first, dates
            // second), so the first and last ROWS are not the first and last
            // DATES. The span comes from the payload, which reads the timestamps.
            const span = this.data.dateSpan || {};
            const first = {
                date: span.earliest ?? transactions[0]?.date,
                date_display: span.earliest_display ?? transactions[0]?.date_display,
            };
            const last = {
                date: span.latest ?? transactions[transactions.length - 1]?.date,
                date_display: span.latest_display ?? transactions[transactions.length - 1]?.date_display,
            };
            const years = this.calculateYearsBetween(first.date, last.date);

            // Root of Title / Original Holder / Current Holder are three DIFFERENT
            // things (client spec 2026-08-20 §12), resolved server-side by
            // TitleHolderResolver. WHICH of them print is the spec table's call —
            // a Direct Allocation shows only two (row iii) — so the tiles come
            // from `titleLines` rather than being hard-coded.
            // The three lines must read as three different things, so each takes
            // its colour from the resolver's `tone`.
            const TONES = {
                amber: ['text-amber-700', 'text-amber-800'],
                emerald: ['text-emerald-700', 'text-emerald-800'],
                indigo: ['text-indigo-700', 'text-indigo-800'],
                gray: ['text-gray-600', 'text-gray-900'],
            };
            const titleLines = Array.isArray(this.data.titleLines) ? this.data.titleLines : [];
            const titleTiles = titleLines.map((line) => {
                const tone = TONES[line.tone] || TONES.gray;
                return `
                <div>
                    <p class="text-xs uppercase font-semibold ${tone[0]}">${line.label ?? ''}</p>
                    <p class="font-bold text-lg ${line.value ? tone[1] : 'text-gray-400'}">${line.value ?? '—'}</p>
                </div>`;
            }).join('');

            grid.innerHTML = `
                ${titleTiles}
                <div>
                    <p class="text-xs text-gray-600 uppercase">Total Transactions</p>
                    <p class="font-bold text-lg text-gray-900">${this.data.totalTransactions ?? 0}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-600 uppercase">Original Date</p>
                    <p class="font-bold text-gray-900">${first?.date_display ?? '—'}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-600 uppercase">Last Updated</p>
                    <p class="font-bold text-gray-900">${last?.date_display ?? '—'}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-600 uppercase">Period (Years)</p>
                    <p class="font-bold text-gray-900">${years ?? '—'}</p>
                </div>
            `;

            list.innerHTML = transactions
                .map((transaction, index) => `
                    <li class="text-sm flex items-start gap-2">
                        <span class="text-gray-600 font-bold">${index + 1}.</span>
                        <span class="text-gray-700">
                            ${transaction.transactionType}: ${transaction.originalHolder} → ${transaction.owner}
                            ${transaction.date_year ? `<span class="text-xs text-gray-500 ml-2">(${transaction.date_year})</span>` : ''}
                        </span>
                    </li>
                `)
                .join('');
        }

        toggleEmptyState(key, shouldShow) {
            const el = this.elements[key];
            if (el) {
                el.hidden = !shouldShow;
            }
        }

        calculateYearsBetween(start, end) {
            if (!start || !end) return null;
            const startDate = new Date(start);
            const endDate = new Date(end);
            if (Number.isNaN(startDate) || Number.isNaN(endDate)) {
                return null;
            }
            return Math.max(0, endDate.getFullYear() - startDate.getFullYear());
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        if (!window.__FILE_HISTORY__) {
            return;
        }
        const page = new FileHistoryPage(window.__FILE_HISTORY__);
        page.init();
    });
})();
