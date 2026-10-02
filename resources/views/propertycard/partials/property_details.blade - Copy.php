@php
    $Property_records = $Property_records ?? ($pra ?? collect());
    $dataRoute = $dataRoute ?? route('propertycard.getData');
    $fallbackRoute = $fallbackRoute ?? route('propertycard.data.fallback');
    $cofoRoute = $cofoRoute ?? route('propertycard.cofo');
    $showPropertyRecordButton = $showPropertyRecordButton ?? true;
    $showIndexCardButton = $showIndexCardButton ?? true;
@endphp

<style>
    #property-details-content .table-container {
        overflow-x: auto;
    }

    #property-records-table.datatable-nowrap th,
    #property-records-table.datatable-nowrap td {
        white-space: nowrap;
    }

    #property-records-table_wrapper .dataTables_processing {
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background-color: rgba(37, 99, 235, 0.9);
        color: #ffffff;
        padding: 0.5rem 1.5rem;
        border-radius: 0.5rem;
        font-weight: 600;
    }

    .timeline-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 36px;
        padding: 0.15rem 0.65rem;
        border-radius: 9999px;
        font-size: 0.8rem;
        font-weight: 600;
        border-width: 1px;
        border-style: solid;
    }

    .timeline-tier-1 {
        color: #374151;
        background-color: #f3f4f6;
        border-color: #d1d5db;
    }

    .timeline-tier-2 {
        color: #1e3a8a;
        background-color: #dbeafe;
        border-color: #bfdbfe;
    }
</style>

<div id="property-details-content" class="tab-content active" style="display: block;">
    <div class="card">
        <div class="card-header">
            <h2 class="text-lg font-medium">Property Records</h2>
            <div class="flex items-center gap-2">
                <input type="text" id="property-search" class="form-input w-64" placeholder="Search properties...">
                <span id="property-searching-indicator" class="ml-3 text-sm text-blue-600 hidden">Searching...</span>
                <button id="reset-cards-view" class="btn btn-secondary" style="display: none;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 mr-2">
                        <path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"></path>
                        <path d="M21 3v5h-5"></path>
                        <path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"></path>
                        <path d="M3 21v-5h5"></path>
                    </svg>
                    Reset View 
                </button>
              
             
                <!-- Add New Property Card Button -->
                <div class="relative inline-block text-left">
                    <button type="button" class="btn btn-primary flex items-center whitespace-nowrap shadow-lg border-2 border-blue-400 bg-gradient-to-r from-blue-500 to-blue-700 text-white hover:from-blue-600 hover:to-blue-800 transition-all" id="dropdown-toggle">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5 mr-2">
                            <path d="M12 5v14M5 12h14"></path>
                        </svg>
                        Add New
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 ml-2">
                            <path d="m6 9 6 6 6-6"></path>
                        </svg>
                    </button>
                    
                    <div class="absolute right-0 z-10 mt-2 w-56 origin-top-right rounded-md bg-white shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none hidden" id="dropdown-menu" role="menu">
                        <div class="py-1" role="none">
                            @if($showPropertyRecordButton)
                            <button class="text-gray-700 block w-full px-4 py-2 text-left text-sm hover:bg-gray-100 hover:text-gray-900" role="menuitem" id="add-property-btn">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 mr-2 inline">
                                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                                    <polyline points="9,22 9,12 15,12 15,22"></polyline>
                                </svg>
                               Property Record
                            </button>
                            @endif
                            @if($showIndexCardButton)
                            <button class="text-gray-700 block w-full px-4 py-2 text-left text-sm hover:bg-gray-100 hover:text-gray-900" role="menuitem" id="index-card-btn">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 mr-2 inline">
                                    <rect width="18" height="18" x="3" y="3" rx="2"></rect>
                                    <path d="M9 8h6m-6 4h6m-6 4h6"></path>
                                </svg>
                                Index Card
                            </button>
                            @endif
                        </div>
                    </div>
                </div>

                <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const dropdownToggle = document.getElementById('dropdown-toggle');
                    const dropdownMenu = document.getElementById('dropdown-menu');
                    const addPropertyBtn = document.getElementById('add-property-btn');
                    const indexCardBtn = document.getElementById('index-card-btn');
                    
                    dropdownToggle.addEventListener('click', function(e) {
                        e.stopPropagation();
                        dropdownMenu.classList.toggle('hidden');
                    });
                    
                    // Close dropdown when clicking outside
                    document.addEventListener('click', function() {
                        dropdownMenu.classList.add('hidden');
                    });
                    
                    // Prevent dropdown from closing when clicking inside
                    dropdownMenu.addEventListener('click', function(e) {
                        e.stopPropagation();
                    });

                    // Add New Property Record button
                    if (addPropertyBtn) {
                        addPropertyBtn.addEventListener('click', function() {
                            dropdownMenu.classList.add('hidden');
                            // Open the Add New Property modal with default title
                            openAddPropertyModal('Add New Property Record');
                        });
                    }

                    // Index Card button
                    if (indexCardBtn) {
                        indexCardBtn.addEventListener('click', function() {
                            dropdownMenu.classList.add('hidden');
                            // Open the Add New Property modal with "Index Card" title
                            openAddPropertyModal('Index Card');
                        });
                    }

                    // Function to open Add Property modal with custom title
                    function openAddPropertyModal(title) {
                        if (title === 'Index Card') {
                            // Open the Index Card modal
                            const indexModal = document.getElementById('index-card-dialog');
                            const indexTitle = document.getElementById('index-card-title');
                            
                            if (indexModal && indexTitle) {
                                indexTitle.textContent = title;
                                indexModal.classList.remove('hidden');
                                // Force the display styles to ensure proper centering
                                indexModal.style.display = 'flex';
                                indexModal.style.position = 'fixed';
                                indexModal.style.top = '0';
                                indexModal.style.left = '0';
                                indexModal.style.right = '0';
                                indexModal.style.bottom = '0';
                                indexModal.style.alignItems = 'center';
                                indexModal.style.justifyContent = 'center';
                                indexModal.style.zIndex = '50';
                                indexModal.style.backgroundColor = 'rgba(0, 0, 0, 0.5)';
                            }
                        } else {
                            // Open the regular Add Property modal
                            const modal = document.getElementById('property-form-dialog');
                            const modalTitle = document.querySelector('#property-form-dialog h2');
                            
                            if (modal && modalTitle) {
                                modalTitle.textContent = title;
                                modal.classList.remove('hidden');
                                modal.style.display = 'flex';
                            } else {
                                // Fallback: trigger existing add property functionality
                                if (typeof window.openAddPropertyModal === 'function') {
                                    window.openAddPropertyModal(title);
                                } else {
                                    console.log('Opening ' + title + ' modal');
                                }
                            }
                        }
                    }
                });
                </script>
            </div>
        </div>
        <div class="card-body">
            <!-- Property Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-6" id="property-cards-container">
                <!-- Add New Property Card -->
                <div class="border-2 border-dashed border-blue-400 rounded-lg shadow-lg cursor-pointer hover:bg-blue-50 transition-all flex flex-col items-center justify-center p-8 bg-gradient-to-br from-blue-50 to-white" id="add-property-card" style="display: none;">
                    <div class="h-16 w-16 rounded-full bg-blue-200 flex items-center justify-center mb-4 shadow">
                        <span class="text-blue-700 text-3xl font-bold">+</span>
                    </div>
                    <h3 class="text-xl font-semibold text-center text-blue-800">Add New Property Record</h3>
                    <p class="text-base text-blue-600 text-center mt-2 font-medium">Click here to create a new property record</p>
                </div>
                <!-- Selected Property Detail Card will be injected here by JS -->
                <div id="selected-property-detail-card" class="col-span-3">
                    
                    @if($Property_records->count())
                        @php $property = $Property_records->first(); @endphp
                        @php
                            $fallbackFileNumber = collect([
                                $property->kangisFileNo ?? null,
                                $property->mlsFNo ?? null,
                                $property->NewKANGISFileno ?? null,
                                $property->fileno ?? null,
                                $property->temp_fileno ?? null,
                                $property->tempFileno ?? null,
                                $property->tempFileNo ?? null,
                                $property->tempFileNumber ?? null,
                            ])->first(function ($value) {
                                return filled($value);
                            });
                        @endphp
                        <div class="border rounded-lg shadow-lg overflow-hidden bg-blue-50 border-blue-200">
                            <div class="bg-blue-100 p-4 border-b border-blue-200">
                                <div class="flex justify-between items-center">
                                    <span class="bg-blue-200 text-blue-800 border-blue-300 px-3 py-1 rounded-full text-sm font-medium">
                                        {{ $property->title_type ?? 'N/A' }} - Selected Record
                                    </span>
                                    <button class="text-blue-600 hover:text-blue-800 property-options" data-id="{{ $property->id }}">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4">
                                            <circle cx="12" cy="12" r="1"></circle>
                                            <circle cx="12" cy="5" r="1"></circle>
                                            <circle cx="12" cy="19" r="1"></circle>
                                        </svg>
                                    </button>
                                </div>
                                <h3 class="mt-2 font-bold text-lg text-blue-900">
                                    {{ $fallbackFileNumber ?? 'No File Number' }}
                                </h3>
                            </div>
                            <div class="p-4">
                                @php
                                    $streetCityFallback = collect([
                                        $property->house_no ?? $property->houseNo ?? null,
                                        $property->streetName ?? $property->street_name ?? null,
                                        $property->lgsaOrCity ?? $property->lgsa_or_city ?? null,
                                    ])->filter(function ($value) {
                                        if (!filled($value)) {
                                            return false;
                                        }

                                        $normalized = strtolower(trim((string) $value));
                                        return $normalized !== 'n/a';
                                    })->implode(', ');

                                    $rawLocation = $property->location ?? null;
                                    $normalizedLocation = is_string($rawLocation) ? strtolower(trim($rawLocation)) : '';
                                    $primaryLocation = (filled($rawLocation) && $normalizedLocation !== 'n/a') ? $rawLocation : null;

                                    $rawDescription = $property->property_description ?? null;
                                    $normalizedDescription = is_string($rawDescription) ? strtolower(trim($rawDescription)) : '';

                                    $resolvedLocation = $primaryLocation
                                        ?? ($streetCityFallback ?: (filled($rawDescription) && $normalizedDescription !== 'no description available' ? $rawDescription : null));
                                @endphp
                                <div class="space-y-4">
                                    <div class="grid grid-cols-2 gap-4 text-sm">
                                        <div>
                                            <strong>LGA/City:</strong> {{ $property->lgsaOrCity ?? 'N/A' }}
                                        </div>
                                        <div>
                                            <strong>Plot Number:</strong> {{ $property->plot_no ?? 'N/A' }}
                                        </div>
                                        <div>
                                            <strong>Layout:</strong> {{ $property->layout ?? 'N/A' }}
                                        </div>
                                        <div>
                                            <strong>Location:</strong> {{ $resolvedLocation ?? 'N/A' }}
                                        </div>
                                    </div> 

                                    <div class="border-t pt-3">
                                        <div class="grid grid-cols-2 gap-4 text-sm">
                                            <div>
                                                <strong>Transaction Type:</strong> {{ $property->transaction_type ?? 'N/A' }}
                                            </div>
                                            <div>
                                                <strong>Transaction Date:</strong> {{ $property->transaction_date ? \Carbon\Carbon::parse($property->transaction_date)->toFormattedDateString() : 'N/A' }}
                                            </div>
                                            <div>
                                                <strong>Registration No:</strong> {{ $property->regNo ?? 'N/A' }}
                                            </div>
                                            <div>
                                                <strong>Instrument Type:</strong> {{ $property->transaction_type ?? 'N/A' }}
                                            </div>
                                        </div>
                                    </div>
                                    @php
                                        $fromParty = $toParty = $fromLabel = $toLabel = '';
                                        switch(strtolower($property->transaction_type ?? '')) {
                                            case 'assignment':
                                                $fromParty = $property->Assignor ?? '';
                                                $toParty = $property->Assignee ?? '';
                                                $fromLabel = 'Assignor';
                                                $toLabel = 'Assignee';
                                                break;
                                            case 'mortgage':
                                                $fromParty = $property->Mortgagor ?? '';
                                                $toParty = $property->Mortgagee ?? '';
                                                $fromLabel = 'Mortgagor';
                                                $toLabel = 'Mortgagee';
                                                break;
                                            case 'surrender':
                                                $fromParty = $property->Surrenderor ?? '';
                                                $toParty = $property->Surrenderee ?? '';
                                                $fromLabel = 'Surrenderor';
                                                $toLabel = 'Surrenderee';
                                                break;
                                            case 'sub-lease':
                                            case 'lease':
                                                $fromParty = $property->Lessor ?? '';
                                                $toParty = $property->Lessee ?? '';
                                                $fromLabel = 'Lessor';
                                                $toLabel = 'Lessee';
                                                break;
                                            default:
                                                $fromParty = $property->Grantor ?? '';
                                                $toParty = $property->Grantee ?? '';
                                                $fromLabel = 'Grantor';
                                                $toLabel = 'Grantee';
                                        }
                                    @endphp
                                    @if($fromParty || $toParty)
                                    <div class="border-t pt-3">
                                        <div class="grid grid-cols-2 gap-4 text-sm">
                                            @if($fromParty)
                                                <div><strong>{{ $fromLabel }}:</strong> {{ $fromParty }}</div>
                                            @endif
                                            @if($toParty)
                                                <div><strong>{{ $toLabel }}:</strong> {{ $toParty }}</div>
                                            @endif
                                        </div>
                                    </div>
                                    @endif
                                </div>
                            </div>
                            <div class="p-4 pt-0 flex justify-between border-t bg-white">
                                <div class="text-xs text-gray-500">
                                    <div>File Numbers:</div>
                                    @if($property->mlsFNo)
                                        <div>MLS: {{ $property->mlsFNo }}</div>
                                    @endif
                                    @if($property->kangisFileNo)
                                        <div>KANGIS: {{ $property->kangisFileNo }}</div>
                                    @endif
                                    @if($property->NewKANGISFileno)
                                        <div>New KANGIS: {{ $property->NewKANGISFileno }}</div>
                                    @endif
                                    @if($property->fileno)
                                        <div>Legacy File No: {{ $property->fileno }}</div>
                                    @endif
                                    @php
                                        $tempDisplay = $property->temp_fileno ?? $property->tempFileno ?? $property->tempFileNo ?? $property->tempFileNumber ?? null;
                                    @endphp
                                    @if($tempDisplay)
                                        <div>Temp: {{ $tempDisplay }}</div>
                                    @endif
                                </div>
                                <div class="flex gap-2">
                                    <button class="px-3 py-1 border rounded-md text-sm flex items-center view-property-details bg-blue-600 text-white hover:bg-blue-700" data-id="{{ $property->id }}">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 mr-1">
                                            <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"></path>
                                            <circle cx="12" cy="12" r="3"></circle>
                                        </svg>
                                        View Full Details
                                    </button>
                                  
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="border rounded-lg shadow-sm bg-white p-6 text-center text-gray-500 col-span-3">
                            <p>No property selected yet. Use the search box or DataTable to load a record.</p>
                        </div>
                    @endif
                </div>
            </div>
<br>
<hr>
<button type="button" class="btn btn-success flex items-center whitespace-nowrap shadow-lg border-2 border-green-400 bg-gradient-to-r from-green-500 to-green-700 text-white hover:from-green-600 hover:to-green-800 transition-all px-4 py-2 rounded-md text-sm mr-3" onclick="window.location.href='{{ $cofoRoute }}'">
    <i class="fas fa-certificate mr-2"></i> COFO
</button>
                
                <br>
                <hr>
            <!-- Property Table -->
            <div class="table-container">
                <div id="property-records-loading" class="hidden p-4 text-blue-600 text-sm">Loading property records...</div>
                <div id="property-records-error" class="hidden p-4 text-red-600 text-sm"></div>
                <table id="property-records-table" class="table datatable-nowrap">
                    <thead>
                        <tr>
                    <th>Prop ID</th>
                    <th>File Number</th>
                    <th>Timeline</th>
                    <th>Grantor</th>
                    <th>Grantee</th>
                    <th>Location</th>
                    <th>Registration Particulars</th>
                    <th>Land Use</th>
                    <th>Instrument Type</th>
                    <th>Date Captured</th>
                    <th>Actions</th>
                </tr>
            </thead>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Include DataTables CSS and JS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<script src="https://code.jquery.com/jquery-3.7.0.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Hide all property cards except the selected detail card and the add card
        const cardsContainer = document.getElementById('property-cards-container');
        if (cardsContainer) {
            // Only hide direct children .border cards, not nested ones
            cardsContainer.querySelectorAll(':scope > .border:not(#add-property-card):not(#selected-property-detail-card)').forEach(card => {
                card.style.display = 'none';
            });
        }

        const dataUrl = @json($dataRoute);
        const fallbackUrl = @json($fallbackRoute);
        let hasSwitchedToFallback = false;

        const propertySearchInput = document.getElementById('property-search');
        const searchIndicator = document.getElementById('property-searching-indicator');
        const loadingIndicator = document.getElementById('property-records-loading');
        const fallbackText = '<span class="text-gray-400">N/A</span>';

        const stateStorageKey = 'propertycard.table.state';
        let savedState = {};
        try {
            savedState = JSON.parse(localStorage.getItem(stateStorageKey)) || {};
        } catch (e) {
            savedState = {};
        }

        function debounce(fn, delay = 300) {
            let timeoutId;
            return function(...args) {
                clearTimeout(timeoutId);
                timeoutId = setTimeout(() => fn.apply(this, args), delay);
            };
        }

        const escapeHtml = (unsafe = '') => String(unsafe)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');

        const renderTruncatedCell = (value, limit = 80) => {
            if (value === null || value === undefined) {
                return fallbackText;
            }

            const trimmed = String(value).trim();
            if (!trimmed) {
                return fallbackText;
            }

            const displayValue = trimmed.length > limit ? trimmed.substring(0, limit) + '...' : trimmed;

            return `<span title="${escapeHtml(trimmed)}">${escapeHtml(displayValue)}</span>`;
        };

        const resolveFileNumber = (row) => row.file_number_display
            || row.kangisFileNo
            || row.mlsFNo
            || row.NewKANGISFileno
            || row.fileno
            || row.temp_fileno
            || row.tempFileno
            || row.tempFileNo
            || row.tempFileNumber
            || '';

        const renderFileNumberCell = (row, type) => {
            const fileNumber = resolveFileNumber(row);

            if (type === 'display' || type === 'filter') {
                if (!fileNumber) {
                    return '<span class="text-gray-400">No File Number</span>';
                }

                return `<span title="${escapeHtml(fileNumber)}">${escapeHtml(fileNumber)}</span>`;
            }

            return fileNumber;
        };

        const renderPropIdCell = (value, type) => {
            if (type === 'display' || type === 'filter') {
                if (!value) {
                    return '<span class="text-gray-400">No Prop ID</span>';
                }

                return `<span class="inline-flex items-center px-2.5 py-0.5 rounded-full border text-xs font-semibold text-blue-800 border-blue-200 bg-blue-50">#${escapeHtml(String(value))}</span>`;
            }

            return value || '';
        };

        const renderTimelineBadge = (count, type) => {
            const numeric = Number(count);
            const safeCount = Number.isFinite(numeric) && numeric > 0 ? numeric : 1;
            const tier = safeCount > 1 ? 2 : 1;

            if (type === 'sort' || type === 'type') {
                return safeCount;
            }

            const className = tier === 1 ? 'timeline-tier-1' : 'timeline-tier-2';
            const title = `Timeline entries: ${safeCount}`;

            return `<span class="timeline-badge ${className}" title="${escapeHtml(title)}">Timeline (${safeCount})</span>`;
        };

        const renderPartyCell = (value, type, limit = 60) => {
            if (type === 'display' || type === 'filter') {
                if (!value) {
                    return fallbackText;
                }

                return renderTruncatedCell(value, limit);
            }

            return value || '';
        };

        const renderDateCell = (value, type) => {
            if (type === 'sort' || type === 'type') {
                return value ? new Date(value).getTime() : 0;
            }

            if (type === 'display' || type === 'filter') {
                if (!value) {
                    return fallbackText;
                }

                const date = new Date(value);
                if (Number.isNaN(date.getTime())) {
                    return fallbackText;
                }

                const formattedDate = date.toLocaleDateString('en-US', {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric'
                });
                const formattedTime = date.toLocaleTimeString('en-US', {
                    hour: '2-digit',
                    minute: '2-digit'
                });

                return `<div class="flex flex-col text-sm"><span class="font-medium">${escapeHtml(formattedDate)}</span><span class="text-xs text-gray-500">${escapeHtml(formattedTime)}</span></div>`;
            }

            return value || '';
        };

        const toggleSearchIndicator = (show) => {
            if (!searchIndicator) {
                return;
            }

            if (show) {
                searchIndicator.classList.remove('hidden');
            } else {
                searchIndicator.classList.add('hidden');
            }
        };

        const toggleLoadingIndicator = (show) => {
            if (!loadingIndicator) return;
            if (show) {
                loadingIndicator.classList.remove('hidden');
            } else {
                loadingIndicator.classList.add('hidden');
            }
        };

        const resetActionButtons = () => {
            document.querySelectorAll('.view-property, .edit-property, .delete-property').forEach(button => {
                const cloned = button.cloneNode(true);
                button.replaceWith(cloned);
            });

            if (typeof window.setupPropertyActions === 'function') {
                window.setupPropertyActions();
            }
        };

        const propertyTable = $('#property-records-table').DataTable({
            dom: 'lrtip',
            processing: true,
            serverSide: true,
            deferRender: true,
            searchDelay: 400,
            autoWidth: false,
            scrollX: true,
            pageLength: savedState.length || 20,
            lengthMenu: [[20, 50, 100], [20, 50, 100]],
            columnDefs: [
                { targets: '_all', className: 'align-middle' }
            ],
            ajax: {
                url: dataUrl,
                type: 'GET',
                dataSrc: function(json) {
                    if (!json || json.success === false) {
                        const msg = (json && json.message) ? json.message : 'Failed to load data';
                        console.error('DataTables Ajax error:', msg);
                        toggleSearchIndicator(false);
                        $('#property-records-error').removeClass('hidden').text(msg);
                        return [];
                    }

                    const payload = json.data || {};
                    json.recordsTotal = payload.recordsTotal || 0;
                    json.recordsFiltered = payload.recordsFiltered || 0;

                    return payload.rows || [];
                },
                error: function(xhr, error, thrown) {
                    console.error('DataTables Ajax error:', error, thrown, xhr && xhr.responseText);
                    toggleSearchIndicator(false);
                    $('#property-records-error').removeClass('hidden').text('Unable to load records. Please try again.');

                    if (!hasSwitchedToFallback) {
                        hasSwitchedToFallback = true;

                        try {
                            propertyTable.ajax.url(fallbackUrl).load();
                        } catch (fallbackError) {
                            console.error('Failed to switch to fallback data source:', fallbackError);
                        }
                    }
                }
            },
            order: savedState.order || [[9, 'desc']],
            language: {
                processing: 'Searching...',
                emptyTable: 'No property records found',
                zeroRecords: 'No matching property records found'
            },
            columns: [
                {
                    data: 'prop_id',
                    name: 'prop_id',
                    render: function(data, type) {
                        return renderPropIdCell(data, type);
                    }
                },
                {
                    data: null,
                    name: 'kangisFileNo',
                    render: function(data, type, row) {
                        return renderFileNumberCell(row, type);
                    }
                },
                {
                    data: 'timeline_count',
                    name: 'prop_id',
                    searchable: false,
                    render: function(data, type) {
                        return renderTimelineBadge(data, type);
                    }
                },
                {
                    data: 'grantor',
                    name: 'Grantor',
                    render: function(data, type, row) {
                        return renderPartyCell(data, type, 60);
                    }
                },
                {
                    data: 'grantee',
                    name: 'Grantee',
                    render: function(data, type, row) {
                        return renderPartyCell(data, type, 60);
                    }
                },
                {
                    data: 'location',
                    name: 'location',
                    render: function(data, type) {
                        if (type === 'display' || type === 'filter') {
                            return renderTruncatedCell(data, 60);
                        }

                        return data || '';
                    }
                },
                {
                    data: 'registration_particulars',
                    name: 'regNo',
                    render: function(data, type, row) {
                        const value = data || row.regNo;

                        if (type === 'display' || type === 'filter') {
                            return renderTruncatedCell(value, 40);
                        }

                        return value || '';
                    }
                },
                {
                    data: 'land_use_type',
                    name: 'land_use_type',
                    render: function(data, type) {
                        if (type === 'display' || type === 'filter') {
                            return renderTruncatedCell(data, 40);
                        }

                        return data || '';
                    }
                },
                {
                    data: 'transaction_type',
                    name: 'transaction_type',
                    render: function(data, type, row) {
                        const displayValue = data || row.instrument_type;

                        if (type === 'display' || type === 'filter') {
                            return renderTruncatedCell(displayValue, 40);
                        }

                        return displayValue || '';
                    }
                },
                {
                    data: 'created_at',
                    name: 'created_at',
                    render: function(data, type) {
                        return renderDateCell(data, type);
                    }
                },
                {
                    data: 'id',
                    name: 'actions',
                    orderable: false,
                    searchable: false,
                    render: function(data) {
                        return `
                            <div class="flex items-center gap-2">
                                <button class="text-blue-500 hover:text-blue-700 transition-colors view-property" data-id="${data}">
                                    <i data-lucide="eye" class="h-4 w-4 text-blue-500"></i>
                                </button>
                                <button class="text-green-500 hover:text-green-700 transition-colors edit-property" data-id="${data}">
                                    <i data-lucide="pencil" class="h-4 w-4 text-green-500"></i>
                                </button>
                                <button class="text-red-500 hover:text-red-700 transition-colors delete-property" data-id="${data}">
                                    <i data-lucide="trash-2" class="h-4 w-4 text-red-500"></i>
                                </button>
                            </div>
                        `;
                    }
                }
            ],
            drawCallback: function() {
                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }

                resetActionButtons();
            }
        });

        propertyTable.on('processing.dt', function(e, settings, processing) {
            toggleSearchIndicator(processing);
            toggleLoadingIndicator(processing);
        });

        propertyTable.on('xhr.dt', function() {
            toggleSearchIndicator(false);
            toggleLoadingIndicator(false);
        });

        window.propertyRecordsTableInstance = propertyTable;

        if (savedState.search && propertySearchInput) {
            propertySearchInput.value = savedState.search;
            propertyTable.search(savedState.search).draw();
        }

        const executeSearch = (term) => {
            propertyTable.search(term).draw();
        };

        const debouncedSearch = debounce(function(term) {
            executeSearch(term);
        }, 400);

        if (propertySearchInput) {
            propertySearchInput.addEventListener('input', function() {
                debouncedSearch(this.value);
            });
        }

        window.propertyRecordsTableSearch = function(term) {
            debouncedSearch(term);
        };

        $('#property-records-table tbody').on('click', 'tr', function(e) {
            if ($(e.target).closest('.view-property, .edit-property, .delete-property').length) {
                return;
            }

            const rowData = propertyTable.row(this).data();
            if (!rowData || !rowData.id) {
                return;
            }

            $('#property-records-table tbody tr').removeClass('selected-row');
            $(this).addClass('selected-row');

            if (typeof window.loadPropertyDetailsInCards === 'function') {
                window.loadPropertyDetailsInCards(rowData.id);
            }
        });

        window.addEventListener('resize', debounce(function() {
            propertyTable.columns.adjust();
        }, 200));

        propertyTable.on('search.dt order.dt length.dt', function() {
            try {
                const currentState = {
                    search: propertyTable.search(),
                    order: propertyTable.order(),
                    length: propertyTable.page.len()
                };
                localStorage.setItem(stateStorageKey, JSON.stringify(currentState));
            } catch (e) {
                // ignore storage failures
            }
        });

        // Additional shared functions live in propertycard.js.javascript.
    });
</script>
