<script>
    const cofoDetailRouteTemplate = @json(route('propertycard.cofo.show', ['cofo' => '__ID__']));

    document.addEventListener('DOMContentLoaded', function() {
        console.log('CofO page loaded');

        // Initialize CofO DataTable immediately
        let cofoTable = null;
        let actionsDelegated = false;
        let searchDebounceHandle = null;

        const cofoSearch = document.getElementById('cofo-search');
        const searchStatus = document.getElementById('cofo-search-status');
        const resetButton = document.getElementById('reset-cofo-cards-view');

        document.querySelectorAll('#close-cofo-view, #cofo-view-close').forEach(button => {
            if (button) {
                button.addEventListener('click', closeCofOViewModal);
            }
        });

        function toggleSearchStatus(message = '') {
            if (!searchStatus) {
                return;
            }

            if (message) {
                searchStatus.textContent = message;
                searchStatus.classList.remove('hidden');
            } else {
                searchStatus.textContent = '';
                searchStatus.classList.add('hidden');
            }
        }

        function initializeActionDelegates() {
            if (actionsDelegated) {
                return;
            }

            actionsDelegated = true;

            $(document).on('click', '.cofo-timeline-btn', function(event) {
                event.preventDefault();
                event.stopPropagation();
                var propId = this.getAttribute('data-prop-id') || '';
                var fileNo = this.getAttribute('data-file-number') || '';
                if (typeof openPropertyTimeline === 'function') {
                    openPropertyTimeline(propId, fileNo);
                }
            });

            $(document).on('click', '.cofo-view-btn', function(event) {
                if (!cofoTable) {
                    return;
                }

                event.preventDefault();
                event.stopPropagation();

                const targetId = this.getAttribute('data-id');
                if (!targetId) {
                    return;
                }

                const row = cofoTable.row($(this).closest('tr'));
                if (!row.any()) {
                    return;
                }

                $('#cofo-records-table tbody tr').removeClass('selected-row');
                $(row.node()).addClass('selected-row');

                let recordPromise = openCofOViewModal(targetId);

                if (typeof loadCofODetailsInCards === 'function') {
                    loadCofODetailsInCards(targetId, recordPromise);
                }

                if (recordPromise && typeof recordPromise.catch === 'function') {
                    recordPromise.catch(() => {});
                }
            });
        }

        // Kebab menu for the CofO row; empty when the user has no item to show
        // (txnFileNoCorrectionMenuItem returns '' without permission).
        function renderCofoActionMenu(id) {
            var correctItem = window.txnFileNoCorrectionMenuItem
                ? window.txnFileNoCorrectionMenuItem('CofO_staging', id)
                : '';
            if (!correctItem) {
                return '';
            }
            return ' <span class="relative inline-block text-left align-middle">' +
                '<button type="button" class="cofo-row-menu-toggle btn btn-xs" style="border:1px solid #e5e7eb;background:#fff;color:#4b5563;" title="More actions">' +
                '<i class="fas fa-ellipsis-v"></i></button>' +
                '<div class="txn-fileno-menu hidden z-20 w-56 rounded-md bg-white shadow-lg ring-1 ring-black ring-opacity-5">' +
                '<div class="py-1">' + correctItem + '</div></div></span>';
        }

        document.addEventListener('click', function (e) {
            var toggle = e.target.closest('.cofo-row-menu-toggle');
            var menus = document.querySelectorAll('#cofo-records-table .txn-fileno-menu');
            if (!toggle) {
                if (!e.target.closest('.txn-fileno-menu')) {
                    menus.forEach(function (m) { m.classList.add('hidden'); });
                }
                return;
            }
            e.preventDefault();
            e.stopPropagation();
            var menu = toggle.parentElement.querySelector('.txn-fileno-menu');
            var willOpen = menu && menu.classList.contains('hidden');
            menus.forEach(function (m) { m.classList.add('hidden'); });
            if (menu && willOpen) {
                // Fixed, so the table wrapper cannot clip it.
                var rect = toggle.getBoundingClientRect();
                menu.style.position = 'fixed';
                menu.style.top = (rect.bottom + 4) + 'px';
                menu.style.left = Math.max(8, rect.right - 224) + 'px';
                menu.classList.remove('hidden');
            }
        }, true);

        window.addEventListener('scroll', function () {
            document.querySelectorAll('#cofo-records-table .txn-fileno-menu').forEach(function (m) { m.classList.add('hidden'); });
        }, true);

        window.TxnFileNoCorrection = Object.assign(window.TxnFileNoCorrection || {}, {
            onChanged: function () {
                if (cofoTable) {
                    cofoTable.ajax.reload(null, false);
                }
            }
        });

        function initializeCofOTable() {
            if (cofoTable) {
                console.log('CofO table already initialized');
                return;
            }
            
            console.log('Initializing CofO DataTable...');
            
            try {
                cofoTable = $('#cofo-records-table').DataTable({
                    processing: true,
                    serverSide: true,
                    deferRender: true,
                    searchDelay: 350,
                    stateSave: true,
                    ajax: {
                        url: '{{ route("propertycard.getCofOData") }}',
                        type: 'GET',
                        data: function(data) {
                            var currentSearch = '';
                            if (cofoSearch && typeof cofoSearch.value === 'string') {
                                currentSearch = cofoSearch.value.trim();
                            }
                            data.search.value = currentSearch;
                        },
                        error: function(xhr, error, thrown) {
                            console.error('CofO DataTables Ajax error:', error, thrown);
                            console.error('Response text:', xhr.responseText);
                            toggleSearchStatus('Search failed. Please try again.');
                        }
                    },
                    columns: [
                        {
                            data: null,
                            name: 'file_number',
                            render: function(data, type, row) {
                                var fileNumber = row.kangisFileNo
                                    || row.mlsFNo
                                    || row.NewKANGISFileno
                                    || row.fileno
                                    || row.temp_fileno
                                    || '';

                                // Sort/filter payloads should be plain text.
                                if (type !== 'display') {
                                    return fileNumber || '';
                                }

                                var escapeHtml = function (v) {
                                    return String(v == null ? '' : v)
                                        .replace(/&/g, '&amp;')
                                        .replace(/</g, '&lt;')
                                        .replace(/>/g, '&gt;')
                                        .replace(/"/g, '&quot;')
                                        .replace(/'/g, '&#039;');
                                };

                                if (!fileNumber) {
                                    return '<span class="text-gray-400">No File Number</span>';
                                }

                                var rawCount = Number(row.timeline_count);
                                var safeCount = Number.isFinite(rawCount) && rawCount > 0 ? rawCount : 1;
                                var propId = row.prop_id || '';

                                var timelineBtn = '<button type="button"' +
                                    ' class="timeline-badge timeline-tier-1 cofo-timeline-btn"' +
                                    ' data-prop-id="' + escapeHtml(String(propId)) + '"' +
                                    ' data-file-number="' + escapeHtml(fileNumber) + '"' +
                                    ' title="View full property timeline">' +
                                    'Timeline (' + safeCount + ')</button>';

                                return '<div class="flex flex-col gap-1">' +
                                    '<span class="font-medium" title="' + escapeHtml(fileNumber) + '">' + escapeHtml(fileNumber) + '</span>' +
                                    timelineBtn +
                                    '</div>';
                            }
                        },
                        {
                            data: 'property_description',
                            name: 'property_description',
                            render: function(data, type, row) {
                                if (data && data.length > 30) {
                                    return data.substring(0, 30) + '...';
                                }
                                return data || 'No description';
                            }
                        },
                        {
                            data: 'location',
                            name: 'location',
                            render: function(data, type, row) {
                                return data || 'N/A';
                            }
                        },
                        {
                            data: 'regNo',
                            name: 'regNo',
                            render: function(data, type, row) {
                                return data || 'N/A';
                            }
                        },
                        {
                            data: 'land_use',
                            name: 'land_use',
                            render: function(data, type, row) {
                                return data || 'N/A';
                            }
                        },
                        {
                            data: 'instrument_type',
                            name: 'instrument_type',
                            render: function(data, type, row) {
                                return data || 'N/A';
                            }
                        },
                        {
                            data: 'Grantor',
                            name: 'Grantor',
                            orderable: false,
                            render: function(data) {
                                if (!data) return 'N/A';
                                return String(data).toLowerCase().replace(/\b\w/g, c => c.toUpperCase());
                            }
                        },
                        {
                            data: 'Grantee',
                            name: 'Grantee',
                            orderable: false,
                            render: function(data) {
                                if (!data) return 'N/A';
                                return String(data).toLowerCase().replace(/\b\w/g, c => c.toUpperCase());
                            }
                        },
                        {
                            data: 'created_at',
                            name: 'created_at',
                            render: function(data, type, row) {
                                if (type === 'sort' || type === 'type') {
                                    return data ? new Date(data).getTime() : 0;
                                }
                                if (data) {
                                    const date = new Date(data);
                                    const formattedDate = date.toLocaleDateString('en-US', {
                                        year: 'numeric',
                                        month: 'short',
                                        day: 'numeric'
                                    });
                                    const formattedTime = date.toLocaleTimeString('en-US', {
                                        hour: '2-digit',
                                        minute: '2-digit'
                                    });
                                    return `<div class="flex flex-col text-sm">
                                                <span class="font-medium">${formattedDate}</span>
                                                <span class="text-xs text-gray-500">${formattedTime}</span>
                                            </div>`;
                                }
                                return '<span class="text-gray-400">N/A</span>';
                            }
                        },
                        {
                            data: 'id',
                            name: 'actions',
                            orderable: false,
                            searchable: false,
                            render: function(data, type, row) {
                                if (!row || !row.id) {
                                    return '<span class="text-xs text-gray-400 italic">No actions</span>';
                                }

                                var timelineBtn = '';
                                if (row.prop_id) {
                                    var fno = row.kangisFileNo || row.mlsFNo || row.NewKANGISFileno || '';
                                    timelineBtn = ' <button type="button" class="btn btn-xs cofo-timeline-btn" style="background:#4f46e5;color:#fff;border:none;" data-prop-id="' + String(row.prop_id).replace(/"/g,'') + '" data-file-number="' + String(fno).replace(/"/g,'') + '"><i class="fas fa-history mr-1"></i>Timeline</button>';
                                }

                                return '<button type="button" class="btn btn-secondary btn-xs cofo-view-btn" data-id="' + row.id + '">' +
                                    '<i class="fas fa-eye mr-1"></i>View' +
                                '</button>' + timelineBtn + renderCofoActionMenu(row.id);
                            }
                        }
                    ],
                    order: [[8, 'desc']], // Order by Date Captured column (index 8) in descending order
                    pageLength: 25,
                    responsive: true,
                    autoWidth: false,
                    language: {
                        processing: "Searching CofO records...",
                        emptyTable: "No CofO records found",
                        zeroRecords: "No matching CofO records found"
                    },
                    dom: '<"flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3"l>rtip',
                    drawCallback: function(settings) {
                        // Re-initialize Lucide icons after table redraw
                        if (typeof lucide !== 'undefined') {
                            lucide.createIcons();
                        }
                    },
                    createdRow: function(row) {
                        $(row).addClass('align-middle text-sm');
                    }
                });
                
                console.log('CofO DataTable initialized successfully');

                initializeActionDelegates();

                const savedState = cofoTable.state.loaded();
                if (savedState && savedState.search && savedState.search.search) {
                    const restoredSearch = savedState.search.search.trim();
                    if (cofoSearch && restoredSearch.length) {
                        cofoSearch.value = restoredSearch;
                        if (resetButton) {
                            resetButton.style.display = 'inline-flex';
                        }
                    }
                }

                $('#cofo-records-table').on('processing.dt', function(e, settings, processing) {
                    if (!cofoTable) {
                        return;
                    }

                    var searchActive = false;
                    if (cofoSearch && typeof cofoSearch.value === 'string') {
                        searchActive = cofoSearch.value.trim().length > 0;
                    }

                    if (processing && (searchActive || settings.iDraw > 1)) {
                        toggleSearchStatus('Searching...');
                    } else if (!processing) {
                        const info = cofoTable.page.info();
                        if (searchActive && info && typeof info.recordsDisplay !== 'undefined') {
                            toggleSearchStatus(`Found ${info.recordsDisplay} result${info.recordsDisplay === 1 ? '' : 's'}`);
                        } else {
                            toggleSearchStatus('');
                        }
                    }
                });

                $('#cofo-records-table').on('xhr.dt', function(e, settings, json) {
                    if (json && json.error) {
                        toggleSearchStatus(json.error);
                    }
                });
            } catch (error) {
                console.error('Error initializing CofO DataTable:', error);
            }
        }

        // Initialize the table immediately
        initializeCofOTable();

        // Delegate row click to load selected CofO details card
        $(document).on('click', '#cofo-records-table tbody tr', function(e) {
            if (!cofoTable) {
                return;
            }

            const rowData = cofoTable.row(this).data();
            const cofoId = rowData ? rowData.id : null;

            if (cofoId && typeof loadCofODetailsInCards === 'function') {
                // Highlight selection
                $('#cofo-records-table tbody tr').removeClass('selected-row');
                $(this).addClass('selected-row');
                // Load details into the selected-cofo-detail-card
                loadCofODetailsInCards(cofoId);
            }
        });

        // Removed add button functionality as requested

        // Search functionality with debounce and UX hints
        if (cofoSearch) {
            cofoSearch.addEventListener('input', function() {
                if (searchDebounceHandle) {
                    clearTimeout(searchDebounceHandle);
                }

                const value = this.value.trim();

                if (resetButton) {
                    resetButton.style.display = value ? 'inline-flex' : 'none';
                }

                if (!cofoTable) {
                    return;
                }

                searchDebounceHandle = setTimeout(function() {
                    cofoTable.search(value).draw();
                    if (value) {
                        toggleSearchStatus('Searching...');
                    } else {
                        toggleSearchStatus('');
                    }
                }, 250);
            });
        }

        if (resetButton) {
            resetButton.addEventListener('click', function() {
                if (!cofoTable) {
                    return;
                }

                if (cofoSearch) {
                    cofoSearch.value = '';
                    cofoSearch.dispatchEvent(new Event('input'));
                }

                cofoTable.search('').draw();
                toggleSearchStatus('');
                this.style.display = 'none';
            });
        }
    });

    // Function to open property form for CofO
    function openPropertyFormForCofO() {
        // Redirect to property form with CofO context
        window.location.href = '{{ route("propertycard.index") }}#add-cofo';
    }

    // Function to load CofO details in cards
    function loadCofODetailsInCards(cofoId, existingPromise) {
        console.log('Load CofO details in cards for ID:', cofoId);

        const detailCard = document.getElementById('selected-cofo-detail-card');
        if (!detailCard || !cofoId) {
            return;
        }

        detailCard.innerHTML = `
            <div class="border rounded-lg shadow-lg overflow-hidden bg-gray-50 border-gray-200 p-8 text-center">
                <div class="flex flex-col items-center justify-center">
                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-green-600 mb-3"></div>
                    <p class="text-gray-500">Loading CofO details...</p>
                </div>
            </div>
        `;

        const fetchPromise = existingPromise && typeof existingPromise.then === 'function'
            ? existingPromise
            : fetchCofORecord(cofoId);

        fetchPromise.then(record => {
            detailCard.innerHTML = buildSelectedCofOCard(record);
        }).catch(error => {
            console.error('Failed to load CofO details', error);
            detailCard.innerHTML = `
                <div class="border rounded-lg shadow-lg overflow-hidden bg-red-50 border-red-200 p-8 text-center">
                    <div class="flex flex-col items-center justify-center space-y-2">
                        <i class="fas fa-exclamation-triangle text-red-500 text-2xl"></i>
                        <p class="text-red-600">Unable to load CofO details.</p>
                        <p class="text-sm text-red-500">${escapeHtml(error.message || 'An unexpected error occurred.')}</p>
                    </div>
                </div>
            `;
        });

        return fetchPromise;
    }

    // Placeholder functions for CofO operations

        window.loadCofODetailsInCards = loadCofODetailsInCards;
    function escapeHtml(value) {
        const safeValue = (value === null || value === undefined) ? '' : value;
        return String(safeValue)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function safeText(value, fallback = 'N/A') {
        if (value === null || value === undefined || value === '') {
            return escapeHtml(fallback);
        }
        return escapeHtml(String(value));
    }

    function getValue(source, ...keys) {
        for (const key of keys) {
            if (Object.prototype.hasOwnProperty.call(source, key) && source[key] !== null && source[key] !== undefined && source[key] !== '') {
                return source[key];
            }
        }
        return null;
    }

    function formatDate(value) {
        if (!value) {
            return 'N/A';
        }

        const parsed = new Date(value);
        if (!Number.isNaN(parsed.getTime())) {
            return escapeHtml(parsed.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            }));
        }

        return escapeHtml(String(value));
    }

function formatDateLabel(value, fallback = 'N/A') {
        if (!value) {
            return fallback;
        }

        const parsed = new Date(value);
        if (!Number.isNaN(parsed.getTime())) {
            return parsed.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            });
        }

        return String(value);
    }

    function formatTimeLabel(value, fallback = 'N/A') {
        if (value === null || value === undefined) {
            return fallback;
        }

        const raw = String(value).trim();
        if (!raw) {
            return fallback;
        }

        const normalizedMatch = raw.match(/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/);
        if (normalizedMatch) {
            let hour = parseInt(normalizedMatch[1], 10);
            const minute = normalizedMatch[2];
            if (Number.isNaN(hour)) {
                return fallback;
            }

            const suffix = hour >= 12 ? 'PM' : 'AM';
            hour = ((hour + 11) % 12) + 1;
            return `${hour}:${minute} ${suffix}`;
        }

        const parsed = new Date(raw);
        if (!Number.isNaN(parsed.getTime())) {
            return parsed.toLocaleTimeString('en-US', {
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        return raw;
    }

    function formatTime(value, fallback = 'N/A') {
        return escapeHtml(formatTimeLabel(value, fallback));
    }

    function formatDateTimeLabel(value, fallback = 'N/A') {
        if (!value) {
            return fallback;
        }

        const parsed = new Date(value);
        if (!Number.isNaN(parsed.getTime())) {
            return parsed.toLocaleString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        return String(value);
    }

    function setElementTextById(elementId, value) {
        const element = document.getElementById(elementId);
        if (!element) {
            return;
        }

        element.textContent = value;
    }

    function setCofOFieldText(elementId, value, fallback = 'N/A') {
        const resolved = value === null || value === undefined || value === ''
            ? fallback
            : String(value);
        setElementTextById(elementId, resolved);
    }

    function resolveTransactionParties(record) {
        const transactionType = String(record.transaction_type || record.transactionType || '').toLowerCase();

        switch (transactionType) {
            case 'assignment':
                return {
                    fromParty: getValue(record, 'Assignor'),
                    toParty: getValue(record, 'Assignee'),
                    fromLabel: 'Assignor',
                    toLabel: 'Assignee'
                };
            case 'mortgage':
                return {
                    fromParty: getValue(record, 'Mortgagor'),
                    toParty: getValue(record, 'Mortgagee'),
                    fromLabel: 'Mortgagor',
                    toLabel: 'Mortgagee'
                };
            case 'surrender':
                return {
                    fromParty: getValue(record, 'Surrenderor'),
                    toParty: getValue(record, 'Surrenderee'),
                    fromLabel: 'Surrenderor',
                    toLabel: 'Surrenderee'
                };
            case 'sub-lease':
            case 'lease':
                return {
                    fromParty: getValue(record, 'Lessor'),
                    toParty: getValue(record, 'Lessee'),
                    fromLabel: 'Lessor',
                    toLabel: 'Lessee'
                };
            default:
                return {
                    fromParty: getValue(record, 'Grantor'),
                    toParty: getValue(record, 'Grantee'),
                    fromLabel: 'Grantor',
                    toLabel: 'Grantee'
                };
        }
    }

    function buildSelectedCofOCard(record) {
        const cofoType = safeText(getValue(record, 'cofo_type', 'cofoType', 'cofo_type_name'), 'CofO');
        const fileNumber = safeText(getValue(record, 'kangisFileNo', 'mlsFNo', 'NewKANGISFileno', 'file_number'), 'No File Number');
        const description = safeText(getValue(record, 'property_description', 'description'), 'No description available');
        const lga = safeText(getValue(record, 'lgsaOrCity', 'lga', 'lgsa_or_city'), 'N/A');
        const plotNo = safeText(getValue(record, 'plot_no', 'plotNo'), 'N/A');
        const layout = safeText(getValue(record, 'layout', 'layoutName'), 'N/A');
        const location = safeText(getValue(record, 'location'), 'N/A');
        const landUse = safeText(getValue(record, 'land_use', 'landUse'), 'N/A');
        const instrumentType = safeText(getValue(record, 'instrument_type', 'instrumentType'), 'N/A');
        const transactionType = safeText(getValue(record, 'transaction_type', 'transactionType'), 'N/A');
        const transactionDate = formatDate(getValue(record, 'transaction_date', 'transactionDate'));
        const transactionTime = formatTime(getValue(record, 'transaction_time', 'transactionTime', 'reg_time', 'regTime', 'deeds_time', 'deedsTime'));
        const regNo = safeText(getValue(record, 'regNo', 'registration_number'), 'N/A');

        const parties = resolveTransactionParties(record);

        const partySection = parties.fromParty || parties.toParty ? `
            <div class="border-t pt-3">
                <div class="grid grid-cols-2 gap-4 text-sm">
                    ${parties.fromParty ? `<div><strong>${escapeHtml(parties.fromLabel)}:</strong> ${safeText(parties.fromParty)}</div>` : ''}
                    ${parties.toParty ? `<div><strong>${escapeHtml(parties.toLabel)}:</strong> ${safeText(parties.toParty)}</div>` : ''}
                </div>
            </div>
        ` : '';

        const mlsLine = getValue(record, 'mlsFNo') ? `<div>MLPP: ${safeText(record.mlsFNo)}</div>` : '';
        const kangisLine = getValue(record, 'kangisFileNo') ? `<div>KANGIS: ${safeText(record.kangisFileNo)}</div>` : '';
        const newKangisLine = getValue(record, 'NewKANGISFileno') ? `<div>New KANGIS: ${safeText(record.NewKANGISFileno)}</div>` : '';

        return `
            <div class="border rounded-lg shadow-lg overflow-hidden bg-green-50 border-green-200">
                <div class="bg-green-100 p-4 border-b border-green-200">
                    <div class="flex justify-between items-center">
                        <span class="bg-green-200 text-green-800 border-green-300 px-3 py-1 rounded-full text-sm font-medium">
                            ${cofoType} - Selected Record
                        </span>
                        <span class="text-xs text-gray-500">Actions disabled</span>
                    </div>
                    <h3 class="mt-2 font-bold text-lg text-green-900">${fileNumber}</h3>
                </div>
                <div class="p-4">
                    <div class="space-y-4">
                        <div class="text-sm">
                            <strong>Description:</strong> ${description}
                        </div>
                        <div class="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <strong>LGA/City:</strong> ${lga}
                            </div>
                            <div>
                                <strong>Plot Number:</strong> ${plotNo}
                            </div>
                            <div>
                                <strong>Layout:</strong> ${layout}
                            </div>
                            <div>
                                <strong>Location:</strong> ${location}
                            </div>
                            <div>
                                <strong>Land Use:</strong> ${landUse}
                            </div>
                            <div>
                                <strong>CofO Type:</strong> ${cofoType}
                            </div>
                        </div>
                        <div class="border-t pt-3">
                            <div class="grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <strong>Transaction Type:</strong> ${transactionType}
                                </div>
                                <div>
                                    <strong>Transaction Date:</strong> ${transactionDate}
                                </div>
                                <div>
                                    <strong>Transaction Time:</strong> ${transactionTime}
                                </div>
                                <div>
                                    <strong>Registration No:</strong> ${regNo}
                                </div>
                                <div>
                                    <strong>Instrument Type:</strong> ${instrumentType}
                                </div>
                            </div>
                        </div>
                        ${partySection}
                    </div>
                </div>
                <div class="p-4 pt-0 flex justify-between border-t bg-white">
                    <div class="text-xs text-gray-500">
                        <div>File Numbers:</div>
                        ${mlsLine}
                        ${kangisLine}
                        ${newKangisLine}
                    </div>
                    <div class="flex gap-2 text-xs text-gray-400 italic">
                        Actions are disabled for now.
                    </div>
                </div>
            </div>
        `;
    }

    function fetchCofORecord(cofoId) {
        const endpoint = cofoDetailRouteTemplate.replace('__ID__', encodeURIComponent(cofoId));

        return fetch(endpoint, {
            headers: {
                'Accept': 'application/json'
            }
        }).then(response => {
            if (!response.ok) {
                throw new Error(`Server responded with status ${response.status}`);
            }
            return response.json();
        }).then(payload => {
            if (!payload.success || !payload.data) {
                throw new Error(payload.message || 'Unable to load CofO details.');
            }
            return payload.data;
        });
    }

    function setCofOViewModalLoadingState() {
        const subtitle = document.getElementById('cofo-view-subtitle');
        if (subtitle) {
            subtitle.textContent = 'Loading record...';
            subtitle.classList.remove('text-red-600');
        }

        const loadingFields = [
            'cofo-view-type',
            'cofo-view-file-number',
            'cofo-view-mls',
            'cofo-view-kangis',
            'cofo-view-new-kangis',
            'cofo-view-created-at',
            'cofo-view-description',
            'cofo-view-lga',
            'cofo-view-plot-no',
            'cofo-view-layout',
            'cofo-view-location',
            'cofo-view-land-use',
            'cofo-view-transaction-type',
            'cofo-view-transaction-date',
            'cofo-view-transaction-time',
            'cofo-view-reg-no',
            'cofo-view-instrument-type',
            'cofo-view-party-from',
            'cofo-view-party-to'
        ];

        loadingFields.forEach(fieldId => setElementTextById(fieldId, 'Loading...'));

        const partyContainer = document.getElementById('cofo-view-parties');
        if (partyContainer) {
            partyContainer.classList.add('hidden');
        }
    }

    function setCofOViewModalError(message) {
        const subtitle = document.getElementById('cofo-view-subtitle');
        if (subtitle) {
            subtitle.textContent = message || 'Unable to load CofO details.';
            subtitle.classList.add('text-red-600');
        }

        const fields = [
            'cofo-view-type',
            'cofo-view-file-number',
            'cofo-view-mls',
            'cofo-view-kangis',
            'cofo-view-new-kangis',
            'cofo-view-created-at',
            'cofo-view-description',
            'cofo-view-lga',
            'cofo-view-plot-no',
            'cofo-view-layout',
            'cofo-view-location',
            'cofo-view-land-use',
            'cofo-view-transaction-type',
            'cofo-view-transaction-date',
            'cofo-view-transaction-time',
            'cofo-view-reg-no',
            'cofo-view-instrument-type',
            'cofo-view-party-from',
            'cofo-view-party-to'
        ];

        fields.forEach(fieldId => setCofOFieldText(fieldId, null));

        const partyContainer = document.getElementById('cofo-view-parties');
        if (partyContainer) {
            partyContainer.classList.add('hidden');
        }
    }

    function populateCofOViewModal(record) {
        const cofoTypeRaw = getValue(record, 'cofo_type', 'cofoType', 'cofo_type_name') || 'CofO';
        const fileNumberRaw = getValue(record, 'kangisFileNo', 'mlsFNo', 'NewKANGISFileno', 'file_number') || 'No File Number';
        const subtitle = document.getElementById('cofo-view-subtitle');
        const createdAtLabel = formatDateTimeLabel(getValue(record, 'created_at'), 'Not specified');

        if (subtitle) {
            if (createdAtLabel && createdAtLabel !== 'Not specified') {
                subtitle.textContent = `Captured on ${createdAtLabel}`;
            } else {
                subtitle.textContent = 'Capture timestamp unavailable';
            }
            subtitle.classList.remove('text-red-600');
        }

        setCofOFieldText('cofo-view-type', cofoTypeRaw, 'CofO');
        setCofOFieldText('cofo-view-file-number', fileNumberRaw, 'No File Number');
        setCofOFieldText('cofo-view-mls', getValue(record, 'mlsFNo'), 'Not specified');
        setCofOFieldText('cofo-view-kangis', getValue(record, 'kangisFileNo'), 'Not specified');
        setCofOFieldText('cofo-view-new-kangis', getValue(record, 'NewKANGISFileno'), 'Not specified');
        setCofOFieldText('cofo-view-created-at', createdAtLabel, 'Not specified');

        setCofOFieldText('cofo-view-description', getValue(record, 'property_description', 'description'), 'No description available');
        setCofOFieldText('cofo-view-lga', getValue(record, 'lgsaOrCity', 'lga', 'lgsa_or_city'), 'Not specified');
        setCofOFieldText('cofo-view-plot-no', getValue(record, 'plot_no', 'plotNo'), 'Not specified');
        setCofOFieldText('cofo-view-layout', getValue(record, 'layout', 'layoutName'), 'Not specified');
        setCofOFieldText('cofo-view-location', getValue(record, 'location'), 'Not specified');
        setCofOFieldText('cofo-view-land-use', getValue(record, 'land_use', 'landUse'), 'Not specified');

        setCofOFieldText('cofo-view-transaction-type', getValue(record, 'transaction_type', 'transactionType'), 'Not specified');
        setCofOFieldText('cofo-view-transaction-date', formatDateLabel(getValue(record, 'transaction_date', 'transactionDate'), 'Not specified'), 'Not specified');
        setCofOFieldText('cofo-view-transaction-time', formatTimeLabel(getValue(record, 'transaction_time', 'transactionTime', 'reg_time', 'regTime', 'deeds_time', 'deedsTime'), 'Not specified'), 'Not specified');
        setCofOFieldText('cofo-view-reg-no', getValue(record, 'regNo', 'registration_number'), 'Not specified');
        setCofOFieldText('cofo-view-instrument-type', getValue(record, 'instrument_type', 'instrumentType'), 'Not specified');

        const parties = resolveTransactionParties(record);
        const partyContainer = document.getElementById('cofo-view-parties');

        if (partyContainer) {
            const hasParties = Boolean(parties.fromParty) || Boolean(parties.toParty);

            if (hasParties) {
                partyContainer.classList.remove('hidden');
                setElementTextById('cofo-view-party-from-label', parties.fromLabel || 'From');
                setElementTextById('cofo-view-party-to-label', parties.toLabel || 'To');
                setCofOFieldText('cofo-view-party-from', parties.fromParty, 'Not specified');
                setCofOFieldText('cofo-view-party-to', parties.toParty, 'Not specified');
            } else {
                partyContainer.classList.add('hidden');
            }
        }
    }

    function closeCofOViewModal() {
        const modal = document.getElementById('cofo-view-dialog');
        if (!modal) {
            return;
        }

        modal.classList.add('hidden');
    }

    function openCofOViewModal(cofoId) {
        const modal = document.getElementById('cofo-view-dialog');
        if (!modal) {
            return Promise.reject(new Error('CofO view modal is unavailable.'));
        }

        setCofOViewModalLoadingState();
        modal.classList.remove('hidden');

        return fetchCofORecord(cofoId)
            .then(record => {
                populateCofOViewModal(record);
                return record;
            })
            .catch(error => {
                console.error('Failed to load CofO details', error);
                setCofOViewModalError(error.message);
                throw error;
            });
    }
</script>
