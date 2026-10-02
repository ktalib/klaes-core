(function () {
    'use strict';

    const SOURCE_LABELS = {
        'file_history_staging': 'File History',
        'CofO_staging': 'CofO',
        'pra': 'PRA',
        'deed_registrations': 'Deed Registration',
    };

    const SOURCE_COLORS = {
        'file_history_staging': { bg: '#dbeafe', text: '#1e40af', border: '#bfdbfe' },
        'CofO_staging': { bg: '#d1fae5', text: '#065f46', border: '#a7f3d0' },
        'pra': { bg: '#fef3c7', text: '#92400e', border: '#fde68a' },
        'deed_registrations': { bg: '#ede9fe', text: '#5b21b6', border: '#ddd6fe' },
    };

    class PropertyTimelinePage {
        constructor(payload) {
            this.data = payload || {};
            this.activeTab = 'timeline';
            this.selectors = {
                fileNumberLabel: '#fileNumberLabel',
                fileNumberDisplay: '#fileNumberDisplay',
                totalTransactions: '#totalTransactions',
                landUse: '#landUse',
                locationDisplay: '#locationDisplay',
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
            const { fileNumber, landuse, location, totalTransactions } = this.data;
            if (this.elements.fileNumberLabel) this.elements.fileNumberLabel.textContent = fileNumber || '—';
            if (this.elements.fileNumberDisplay) this.elements.fileNumberDisplay.textContent = fileNumber || '—';
            if (this.elements.totalTransactions) this.elements.totalTransactions.textContent = totalTransactions ?? 0;
            if (this.elements.landUse) this.elements.landUse.textContent = landuse || '—';
            if (this.elements.locationDisplay) this.elements.locationDisplay.textContent = location || '—';
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

            transactions.forEach((txn, index) => {
                const wrapper = document.createElement('div');
                wrapper.className = 'timeline-item';
                wrapper.innerHTML = this.buildTimelineCard(txn, index, transactions.length);
                container.appendChild(wrapper);
            });
        }

        buildTimelineCard(txn, index, total) {
            const parties = txn.parties || {};
            const partyEntries = Object.entries(parties).filter(([, v]) => v && v.trim());
            const partiesHtml = partyEntries.map(([role, name]) =>
                `<span class="text-xs text-gray-600"><strong>${this.escapeHtml(role)}:</strong> ${this.escapeHtml(name)}</span>`
            ).join('<span class="text-gray-300 px-1">·</span>');

            const sourceLabel = SOURCE_LABELS[txn.source_table] || txn.source_table;
            const sourceColor = SOURCE_COLORS[txn.source_table] || SOURCE_COLORS['file_history_staging'];

            const regParts = [txn.serial_no, txn.page_no, txn.volume_no].filter(Boolean);
            const regDisplay = regParts.length ? regParts.join('/') : (txn.regNo || 'N/A');

            return `
                <div class="timeline-node">
                    <div class="timeline-node-circle">
                        <div class="timeline-node-number">${index + 1}</div>
                    </div>
                </div>
                <div class="flex-1 mt-2">
                    <div class="card hover:shadow-lg transition-shadow">
                        <div class="card-content">
                            <div class="space-y-4">
                                <div class="flex items-start justify-between flex-wrap gap-2">
                                    <div class="flex items-start gap-3">
                                        <svg class="icon-md text-gray-600 mt-1 flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                            <circle cx="12" cy="7" r="4"></circle>
                                        </svg>
                                        <div>
                                            <p class="text-xs text-gray-500 uppercase tracking-wide">
                                                ${index === 0 ? 'Original Party' : 'Transaction Party'}
                                            </p>
                                            <p class="text-lg font-bold text-gray-900">${this.escapeHtml(txn.primary_party || 'Unknown')}</p>
                                            <div class="flex items-center gap-2 mt-1 flex-wrap">
                                                <span class="badge badge-outline text-xs capitalize">${this.escapeHtml(txn.transaction_type || 'Transaction')}</span>
                                                <span class="source-tag source-tag-${txn.source_table}" style="background:${sourceColor.bg};color:${sourceColor.text};border-color:${sourceColor.border}">${this.escapeHtml(sourceLabel)}</span>
                                            </div>
                                        </div>
                                    </div>
                                    ${index < total - 1 ? `
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
                                    <span class="text-gray-700">${this.escapeHtml(txn.transaction_date || 'Date unavailable')}</span>
                                </div>

                                ${partyEntries.length > 0 ? `
                                    <div class="bg-gray-50 rounded-lg p-3">
                                        <p class="text-xs text-gray-600 uppercase tracking-wide mb-2">Transaction Parties</p>
                                        <div class="flex flex-wrap gap-1">${partiesHtml}</div>
                                    </div>
                                ` : ''}

                                <div class="bg-green-50 rounded-lg p-3 space-y-2">
                                    <p class="text-xs font-semibold text-gray-900 uppercase tracking-wide">Property Details</p>
                                    <div class="space-y-1">
                                        <div class="flex items-center gap-2 text-sm">
                                            <svg class="icon-sm text-gray-600 flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                                                <circle cx="12" cy="10" r="3"></circle>
                                            </svg>
                                            <span class="text-gray-700">${this.escapeHtml(txn.location || 'N/A')}</span>
                                        </div>
                                        <p class="text-sm text-gray-700 ml-6">Land Use: ${this.escapeHtml(txn.land_use || 'N/A')}</p>
                                    </div>
                                </div>

                                <div class="text-sm border-t pt-3">
                                    <p class="text-xs text-gray-600 uppercase tracking-wide mb-2">Registration Details</p>
                                    <div class="grid grid-cols-3 gap-2 text-xs">
                                        <div>
                                            <p class="text-gray-600">Serial No:</p>
                                            <p class="font-semibold text-gray-900">${this.escapeHtml(txn.serial_no || 'N/A')}</p>
                                        </div>
                                        <div>
                                            <p class="text-gray-600">Page No:</p>
                                            <p class="font-semibold text-gray-900">${this.escapeHtml(txn.page_no || 'N/A')}</p>
                                        </div>
                                        <div>
                                            <p class="text-gray-600">Vol No:</p>
                                            <p class="font-semibold text-gray-900">${this.escapeHtml(txn.volume_no || 'N/A')}</p>
                                        </div>
                                    </div>
                                </div>

                                ${txn.comments ? `
                                    <div class="text-sm border-t pt-3">
                                        <p class="text-xs text-gray-600 uppercase tracking-wide mb-1">Comments</p>
                                        <p class="text-gray-700">${this.escapeHtml(txn.comments)}</p>
                                    </div>
                                ` : ''}
                            </div>
                        </div>
                    </div>
                </div>
            `;
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

            transactions.forEach((txn, index) => {
                const sourceLabel = SOURCE_LABELS[txn.source_table] || txn.source_table;
                const card = document.createElement('div');
                card.className = 'card';
                card.innerHTML = `
                    <div class="card-header pb-3">
                        <div class="flex items-start justify-between">
                            <div>
                                <h3 class="card-title">Transaction ${index + 1}: ${this.escapeHtml(txn.transaction_type || 'Transaction')}</h3>
                                <p class="text-sm text-gray-600 mt-1">${this.escapeHtml(txn.transaction_date || 'Date unavailable')}</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="badge ${index === transactions.length - 1 ? 'badge-default' : 'badge-secondary'}">
                                    ${index === transactions.length - 1 ? 'Latest' : 'Historical'}
                                </span>
                                <span class="source-tag source-tag-${txn.source_table}">${this.escapeHtml(sourceLabel)}</span>
                            </div>
                        </div>
                    </div>
                    <div class="card-content space-y-3">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-xs text-gray-600 uppercase">Party 1</p>
                                <p class="font-semibold text-gray-900">${this.escapeHtml(txn.party_1 || 'N/A')}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-600 uppercase">Party 2</p>
                                <p class="font-semibold text-gray-900">${this.escapeHtml(txn.party_2 || txn.secondary_party || 'N/A')}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-600 uppercase">Transaction Type</p>
                                <p class="font-semibold text-gray-900">${this.escapeHtml(txn.transaction_type || 'N/A')}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-600 uppercase">Land Use</p>
                                <p class="font-semibold text-gray-900">${this.escapeHtml(txn.land_use || 'N/A')}</p>
                            </div>
                        </div>
                        <div class="grid grid-cols-3 gap-4 text-sm">
                            <div>
                                <p class="text-xs text-gray-600 uppercase">Serial No</p>
                                <p class="font-semibold text-gray-900">${this.escapeHtml(txn.serial_no || 'N/A')}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-600 uppercase">Page No</p>
                                <p class="font-semibold text-gray-900">${this.escapeHtml(txn.page_no || 'N/A')}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-600 uppercase">Vol No</p>
                                <p class="font-semibold text-gray-900">${this.escapeHtml(txn.volume_no || 'N/A')}</p>
                            </div>
                        </div>
                        <div class="bg-gray-50 rounded p-3 text-sm">
                            <p class="text-xs text-gray-600 uppercase mb-2">Location</p>
                            <p class="font-semibold text-gray-900">${this.escapeHtml(txn.location || 'N/A')}</p>
                        </div>
                        <div class="text-xs text-gray-500">
                            Source: ${this.escapeHtml(sourceLabel)}
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

            const first = transactions[0];
            const last = transactions[transactions.length - 1];
            const years = this.calculateYearsBetween(first.sort_date, last.sort_date);

            // Count by source
            const sourceCounts = {};
            transactions.forEach(t => {
                const label = SOURCE_LABELS[t.source_table] || t.source_table;
                sourceCounts[label] = (sourceCounts[label] || 0) + 1;
            });
            const sourceBreakdown = Object.entries(sourceCounts)
                .map(([src, count]) => `${src}: ${count}`)
                .join(', ');

            grid.innerHTML = `
                <div>
                    <p class="text-xs text-gray-600 uppercase">Original Party</p>
                    <p class="font-bold text-lg text-gray-900">${this.escapeHtml(this.data.originalOwner || 'Unknown')}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-600 uppercase">Latest Party</p>
                    <p class="font-bold text-lg text-gray-900">${this.escapeHtml(this.data.currentOwner || 'Unknown')}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-600 uppercase">Total Transactions</p>
                    <p class="font-bold text-lg text-gray-900">${this.data.totalTransactions ?? 0}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-600 uppercase">Earliest Date</p>
                    <p class="font-bold text-gray-900">${this.escapeHtml(first?.transaction_date || '—')}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-600 uppercase">Latest Date</p>
                    <p class="font-bold text-gray-900">${this.escapeHtml(last?.transaction_date || '—')}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-600 uppercase">Period (Years)</p>
                    <p class="font-bold text-gray-900">${years ?? '—'}</p>
                </div>
                <div class="col-span-full">
                    <p class="text-xs text-gray-600 uppercase">Source Breakdown</p>
                    <p class="font-bold text-gray-900">${this.escapeHtml(sourceBreakdown)}</p>
                </div>
            `;

            list.innerHTML = transactions
                .map((txn, index) => {
                    const sourceLabel = SOURCE_LABELS[txn.source_table] || txn.source_table;
                    return `
                        <li class="text-sm flex items-start gap-2">
                            <span class="text-gray-600 font-bold">${index + 1}.</span>
                            <span class="text-gray-700">
                                ${this.escapeHtml(txn.transaction_type || 'Transaction')}: 
                                ${this.escapeHtml(txn.primary_party || 'Unknown')}
                                ${txn.secondary_party ? ` → ${this.escapeHtml(txn.secondary_party)}` : ''}
                                <span class="text-xs text-gray-500 ml-1">(${this.escapeHtml(txn.transaction_date || '?')})</span>
                                <span class="text-xs text-gray-400 ml-1">[${this.escapeHtml(sourceLabel)}]</span>
                            </span>
                        </li>
                    `;
                })
                .join('');
        }

        toggleEmptyState(key, shouldShow) {
            const el = this.elements[key];
            if (el) el.hidden = !shouldShow;
        }

        calculateYearsBetween(start, end) {
            if (!start || !end) return null;
            const s = new Date(start);
            const e = new Date(end);
            if (Number.isNaN(s.getTime()) || Number.isNaN(e.getTime())) return null;
            return Math.max(0, e.getFullYear() - s.getFullYear());
        }

        escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        if (!window.__PROPERTY_TIMELINE__) return;
        const page = new PropertyTimelinePage(window.__PROPERTY_TIMELINE__);
        page.init();
    });
})();
