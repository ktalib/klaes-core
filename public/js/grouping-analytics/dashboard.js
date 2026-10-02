window.groupingAnalyticsDashboard = function groupingAnalyticsDashboard() {
    return {
        isRefreshing: false,
        lastUpdated: null,
        previewDataTable: null,
        searchModal: null,
        searchForm: null,
        hasLoadedPreview: false,
        previewAutoloadTimer: null,
        previewAutoDelayMs: 2500,
        activeFilters: {
            batch: '',
            landuse: '',
            year: '',
            fileno: ''
        },

        init() {
            if (window.__GROUPING_FILTERS__) {
                this.activeFilters = Object.assign({
                    batch: '',
                    landuse: '',
                    year: '',
                    fileno: ''
                }, window.__GROUPING_FILTERS__);
            }

            // Initialize empty table immediately for fast page load
            this.initEmptyPreviewTable();
            this.setupAdvancedSearch();
            this.setupQuickFilter();
            // Only load stats on first paint; preview table stays empty until requested
            this.refreshStats({ includePreview: false });
        },

        schedulePreviewAutoload() {
            this.clearPreviewAutoload();

            if (this.hasLoadedPreview) {
                return;
            }

            const delay = Number(this.previewAutoDelayMs) || 2000;
            this.previewAutoloadTimer = setTimeout(() => {
                this.previewAutoloadTimer = null;

                if (!this.hasLoadedPreview && !this.isRefreshing) {
                    this.loadPreviewData();
                }
            }, delay);
        },

        clearPreviewAutoload() {
            if (this.previewAutoloadTimer) {
                clearTimeout(this.previewAutoloadTimer);
                this.previewAutoloadTimer = null;
            }
        },

        async refreshStats(options = {}) {
            if (this.isRefreshing) {
                return;
            }

            this.isRefreshing = true;

            try {
                const statsResponse = await fetch('/api/grouping-analytics/stats', {
                    headers: {
                        'Accept': 'application/json'
                    }
                });

                const includePreview = options.includePreview === true;
                const previewResponse = includePreview
                    ? await fetch('/api/grouping-analytics/preview?limit=100', {
                        headers: {
                            'Accept': 'application/json'
                        }
                    })
                    : null;

                let statsPayload = null;
                if (statsResponse) {
                    try {
                        statsPayload = await statsResponse.json();
                    } catch (parseError) {
                        console.warn('Failed to parse stats response JSON', parseError);
                    }
                }

                if (!statsResponse?.ok || !statsPayload?.success) {
                    const fallback = statsPayload?.fallback_data?.overall ?? null;
                    if (!fallback) {
                        throw new Error(statsPayload?.message || `Stats request failed with status ${statsResponse?.status}`);
                    }

                    this.updateKpiCards({
                        ...fallback,
                        _fallback: true
                    });
                    this.updateLandUseTable(statsPayload?.fallback_data?.landuse || []);
                    this.updateActivityList(statsPayload?.fallback_data?.recent_activity || []);
                } else {
                    const statsData = statsPayload.data || {};
                    const overallStats = statsData.overall || {};
                    const landUseBreakdown = statsData.landuse || [];
                    const activity = statsData.recent_activity || [];

                    this.updateKpiCards(overallStats);
                    this.updateLandUseTable(landUseBreakdown);
                    this.updateActivityList(activity);
                }

                if (previewResponse) {
                    const previewPayload = previewResponse.ok ? await previewResponse.json() : null;
                    if (previewResponse.ok && previewPayload?.success) {
                        this.updatePreviewTable(previewPayload.data || []);
                        this.updatePreviewCount(previewPayload.count || 0);
                        this.hasLoadedPreview = true;
                    } else if (!previewResponse.ok) {
                        console.warn('Preview endpoint unavailable.', previewResponse.status);
                    }
                }

                if (includePreview) {
                    this.clearPreviewAutoload();
                } else if (!this.hasLoadedPreview) {
                    this.schedulePreviewAutoload();
                }

                this.lastUpdated = new Date().toLocaleTimeString();
            } catch (error) {
                console.error('Analytics refresh failed:', error);
                this.notify('Unable to refresh analytics at this time.', 'error');
            } finally {
                this.isRefreshing = false;
            }
        },

        initEmptyPreviewTable() {
            const tbody = document.querySelector('[data-table="preview"]');
            if (!tbody) {
                return;
            }

            // Show empty state with load button
            tbody.innerHTML = `
                <tr data-empty-row>
                    <td colspan="12" class="empty" style="text-align: center; padding: 40px;">
                        <div style="margin-bottom: 15px;">Preview table is empty for faster page loading.</div>
                        <button onclick="window.groupingDashboard?.loadPreviewData()" 
                                style="padding: 8px 16px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer;">
                            Load Preview Data
                        </button>
                    </td>
                </tr>
            `;
            
            // Initialize empty DataTable
            if (typeof requestAnimationFrame === 'function') {
                requestAnimationFrame(() => this.refreshPreviewDataTable());
            } else {
                setTimeout(() => this.refreshPreviewDataTable(), 0);
            }
        },

        async loadPreviewData() {
            if (this.isRefreshing) {
                return;
            }

            this.clearPreviewAutoload();

            // Update button to show loading state
            const button = document.querySelector('[data-table="preview"] button');
            if (button) {
                button.textContent = 'Loading...';
                button.disabled = true;
            }

            const previousRefreshing = this.isRefreshing;
            this.isRefreshing = true;

            try {
                const response = await fetch('/api/grouping-analytics/preview?limit=100', {
                    headers: {
                        'Accept': 'application/json'
                    }
                });

                const previewPayload = response.ok ? await response.json() : null;
                if (response.ok && previewPayload?.success) {
                    this.updatePreviewTable(previewPayload.data || []);
                    this.updatePreviewCount(previewPayload.count || 0);
                    this.hasLoadedPreview = true;
                } else {
                    throw new Error('Failed to load preview data');
                }

            } catch (error) {
                console.error('Preview load failed:', error);
                const tbody = document.querySelector('[data-table="preview"]');
                if (tbody) {
                    tbody.innerHTML = `
                        <tr data-empty-row>
                            <td colspan="12" class="empty" style="text-align: center; padding: 40px; color: #dc3545;">
                                <div style="margin-bottom: 15px;">Failed to load preview data: ${error.message}</div>
                                <button onclick="window.groupingDashboard?.loadPreviewData()" 
                                        style="padding: 8px 16px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer;">
                                    Retry
                                </button>
                            </td>
                        </tr>
                    `;
                }
            } finally {
                this.isRefreshing = previousRefreshing;
            }
        },

        setupAdvancedSearch() {
            this.searchModal = document.getElementById('grouping-search-modal');
            this.searchForm = document.getElementById('grouping-search-form');

            const closeNodes = document.querySelectorAll('[data-action="close-search"]');
            closeNodes.forEach((node) => {
                node.addEventListener('click', () => this.closeSearchModal());
            });

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    this.closeSearchModal();
                }
            });
        },

        hasActiveFilters() {
            return Object.values(this.activeFilters || {}).some((value) => value !== null && value !== undefined && String(value).trim() !== '');
        },


        updateKpiCards(overall) {
            const isPlaceholder = overall && (overall._placeholder || overall._fallback);
            const mappings = {
                total: ['total_files', '[data-metric="total-files"]'],
                matched: ['matched_files', '[data-metric="matched-files"]'],
                unmatched: ['unmatched_files', '[data-metric="unmatched-files"]'],
                percentage: ['matching_percentage', '[data-metric="matching-percentage"]'],
                today: ['today_matches', '[data-metric="today-matches"]'],
                lastMatch: ['last_match_time', '[data-metric="last-match-time"]']
            };

            Object.values(mappings).forEach(([key, selector]) => {
                const node = document.querySelector(selector);
                if (!node) {
                    return;
                }

                if (isPlaceholder) {
                    node.textContent = key === 'last_match_time' ? '—' : 'Loading…';
                    node.classList.add('loading-skeleton');
                    return;
                }

                let value = overall[key];

                if (value === null || value === undefined) {
                    node.textContent = '—';
                    node.classList.remove('loading-skeleton');
                    return;
                }

                // Handle fallback/loading states
                if (typeof value === 'string' && (value.includes('Loading') || value === '—')) {
                    node.textContent = value;
                    node.classList.add('loading-skeleton');
                    return;
                }

                node.classList.remove('loading-skeleton');

                if (key === 'matching_percentage' && typeof value === 'number') {
                    value = `${value.toFixed(2)}%`;
                } else if (typeof value === 'number') {
                    value = new Intl.NumberFormat().format(value);
                } else if (key === 'last_match_time' && value) {
                    value = new Date(value).toLocaleString();
                }

                node.textContent = value;
            });
        },

        updateLandUseTable(rows) {
            const tbody = document.querySelector('[data-table="landuse"]');
            if (!tbody) {
                return;
            }

            if (!Array.isArray(rows) || rows.length === 0) {
                tbody.innerHTML = '<tr><td colspan="3" class="empty">No land use data available.</td></tr>';
                return;
            }

            const formatter = new Intl.NumberFormat();
            const html = rows.map((row) => {
                const totalRaw = Number(row.count ?? row.total_files ?? row.total ?? 0);
                const shareRaw = Number(row.percentage ?? row.match_percentage ?? row.share ?? 0);
                const total = formatter.format(totalRaw);
                const share = Number.isFinite(shareRaw) ? shareRaw.toFixed(2) : '0.00';
                return `
                <tr>
                    <th scope="row">${row.landuse ?? 'Unknown'}</th>
                    <td class="numeric">${total}</td>
                    <td class="numeric">${share}%</td>
                </tr>`;
            }).join('');

            tbody.innerHTML = html;
        },

        updateActivityList(activities) {
            const list = document.querySelector('[data-list="recent-activity"]');
            if (!list) {
                return;
            }

            if (!Array.isArray(activities) || activities.length === 0) {
                list.innerHTML = '<li class="empty">No recent matches recorded.</li>';
                return;
            }

            const html = activities.map((activity) => {
                const awaiting = activity.awaiting_fileno ?? '—';
                const mls = activity.mls_fileno ?? 'N/A';
                const status = mls && mls !== 'N/A' ? 'matched' : 'pending';
                const group = activity.group_number ?? '—';
                const position = activity.position_in_group ?? '—';
                const matchedAt = activity.matched_at ? new Date(activity.matched_at).toLocaleString() : '—';

                return `
                    <li>
                        <div class="activity-meta">
                            <span class="file-number">${awaiting}</span>
                            <span class="match-status ${status}">${status === 'matched' ? 'Matched' : 'Pending'}</span>
                        </div>
                        <div class="activity-details">
                            <span>MLPP: ${mls}</span>
                            <span>Group ${group} · Pos ${position}</span>
                            <span>${matchedAt}</span>
                        </div>
                    </li>
                `;
            }).join('');

            list.innerHTML = html;
        },

        updatePreviewTable(rows) {
            const tbody = document.querySelector('[data-table="preview"]');
            if (!tbody) {
                return;
            }

            if (!Array.isArray(rows) || rows.length === 0) {
                tbody.innerHTML = '<tr data-empty-row><td colspan="12" class="empty">No records available for preview.</td></tr>';
                this.refreshPreviewDataTable();
                return;
            }

            const html = rows.map((row) => {
                const mappingRaw = row.mapping ?? row.is_matched ?? row.matched ?? row.matching ?? 0;
                const mappingNumber = Number(mappingRaw);
                const mappingText = String(mappingRaw).trim().toLowerCase();
                const isMatched = (Number.isFinite(mappingNumber) && mappingNumber >= 1)
                    || ['true', 'yes', 'matched', 'match', 'y'].includes(mappingText);
                const createdDate = row.created_at ? new Date(row.created_at) : null;
                const createdAtLabel = createdDate ? createdDate.toLocaleString() : '—';
                const createdAtOrder = row.created_at ?? '';
                const groupValue = row.group_number ?? row.group ?? row.groupNumber ?? '—';
                const batchValue = row.batch_no ?? row.batch ?? '—';
                const mdcBatchValue = row.mdc_batch_no ?? row.mdc_batch ?? '—';
                const registryValue = row.registry ?? row.registry_label ?? '—';
                const shelfRackValue = row.shelf_rack ?? row.shelfRack ?? '—';
                const indexedByValue = row.indexed_by ?? row.indexedBy ?? '—';
                const landUseValue = row.landuse ?? row.land_use ?? '—';
                const yearValue = row.year ?? row.record_year ?? '—';

                return `
                    <tr data-row>
                        <td data-heading="Awaiting File No" class="whitespace-nowrap">${row.awaiting_fileno ?? '—'}</td>
                        <td data-heading="MLPP File No" class="whitespace-nowrap">${row.mls_fileno ?? '—'}</td>
                        <td data-heading="Mapping" class="whitespace-nowrap">
                            <span class="pill ${isMatched ? 'pill-success' : 'pill-warning'}">
                                ${isMatched ? 'Matched' : 'Pending'}
                            </span>
                        </td>
                        <td data-heading="Group" class="whitespace-nowrap">${groupValue}</td>
                        <td data-heading="Batch" class="whitespace-nowrap">${batchValue}</td>
                        <td data-heading="MDC Batch" class="whitespace-nowrap">${mdcBatchValue}</td>
                        <td data-heading="Registry" class="whitespace-nowrap">${registryValue}</td>
                        <td data-heading="Shelf Rack" class="whitespace-nowrap">${shelfRackValue}</td>
                        <td data-heading="Indexed By" class="whitespace-nowrap">${indexedByValue}</td>
                        <td data-heading="Land Use" class="whitespace-nowrap">${landUseValue}</td>
                        <td data-heading="Year" class="whitespace-nowrap">${yearValue}</td>
                        <td data-heading="Created" class="whitespace-nowrap" data-order="${createdAtOrder}">${createdAtLabel}</td>
                    </tr>
                `;
            }).join('');

            tbody.innerHTML = `${html}<tr data-empty-row class="hidden"><td colspan="12" class="empty">No matching records for this quick filter.</td></tr>`;
            this.refreshPreviewDataTable();
            this.prepareQuickFilterIndex();
        },

        refreshPreviewDataTable() {
            const jQ = window.jQuery;
            if (!jQ || !jQ.fn || !jQ.fn.DataTable) {
                return;
            }

            const $table = jQ('#grouping-preview-table');
            if ($table.length === 0) {
                return;
            }

            if (this.hasActiveFilters()) {
                if (this.previewDataTable) {
                    this.previewDataTable.destroy();
                    this.previewDataTable = null;
                }
                this.prepareQuickFilterIndex();
                return;
            }

            if (this.previewDataTable) {
                this.previewDataTable.destroy();
                this.previewDataTable = null;
            }

            this.previewDataTable = $table.DataTable({
                paging: true,
                pagingType: 'simple_numbers',
                pageLength: 50,
                lengthMenu: [
                    [50, 100, 250],
                    ['50', '100', '250']
                ],
                autoWidth: false,
                order: [[11, 'desc']],
                dom: '<"grouping-table-controls"f><"table-responsive-scroll"t><"grouping-table-footer"ip>',
                language: {
                    search: 'Quick filter:',
                    searchPlaceholder: 'Type to filter…',
                    lengthMenu: 'Show _MENU_ rows',
                    info: 'Showing _START_–_END_ of _TOTAL_ rows',
                    infoEmpty: 'No records to display',
                    zeroRecords: 'No matching records found'
                },
                columnDefs: [
                    { targets: [2], orderable: false, searchable: false }
                ]
            });
        },

        setupQuickFilter() {
            const quickFilterInput = document.querySelector('[data-table-quick-filter]');
            if (!quickFilterInput) {
                return;
            }

            this.quickFilterInput = quickFilterInput;

            const handleInput = (event) => {
                this.filterTableRows(event.target.value || '');
            };

            quickFilterInput.addEventListener('input', handleInput);
            quickFilterInput.addEventListener('search', handleInput);

            this.prepareQuickFilterIndex();
        },

        prepareQuickFilterIndex() {
            const tbody = document.querySelector('[data-table="preview"]');
            if (!tbody) {
                return;
            }

            const rows = tbody.querySelectorAll('tr[data-row]');
            rows.forEach((row) => {
                row.dataset.searchText = row.textContent.toLowerCase();
            });

            if (this.quickFilterInput) {
                this.filterTableRows(this.quickFilterInput.value || '');
            }
        },

        filterTableRows(term) {
            const tbody = document.querySelector('[data-table="preview"]');
            if (!tbody) {
                return;
            }

            const rows = Array.from(tbody.querySelectorAll('tr[data-row]'));
            const emptyRows = Array.from(tbody.querySelectorAll('tr[data-empty-row]'));
            const normalized = (term || '').trim().toLowerCase();

            if (rows.length === 0) {
                emptyRows.forEach((row) => {
                    row.classList.remove('hidden');
                });
                return;
            }

            let anyVisible = false;

            rows.forEach((row) => {
                const haystack = row.dataset.searchText || row.textContent.toLowerCase();
                const isMatch = !normalized || haystack.includes(normalized);
                row.classList.toggle('hidden', !isMatch);
                if (isMatch) {
                    anyVisible = true;
                }
            });

            emptyRows.forEach((row, index) => {
                const shouldShow = !anyVisible && index === emptyRows.length - 1;
                row.classList.toggle('hidden', !shouldShow);
            });
        },

        updatePreviewCount(count) {
            const node = document.querySelector('[data-counter="preview-count"]');
            if (node) {
                node.textContent = new Intl.NumberFormat().format(Number(count) || 0);
            }
        },

        openSearchModal() {
            if (!this.searchModal) {
                this.notify('Advanced search is not available in this environment.', 'info');
                return;
            }

            this.searchModal.classList.remove('hidden');
        },

        closeSearchModal() {
            if (this.searchModal) {
                this.searchModal.classList.add('hidden');
            }
        },

        triggerExport() {
            this.notify('Exporting snapshot… This may take a few seconds.', 'info');
            window.location.href = '/grouping-analytics/export/snapshot';
        },

        notify(message, type = 'info') {
            if (window.Swal) {
                window.Swal.fire({
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 2600,
                    icon: type,
                    title: message
                });
                return;
            }

            console[type === 'error' ? 'error' : 'log'](message);
        }
    };
};
