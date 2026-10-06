@php
    $featuredRecord = $featuredRecord ?? null;
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

    .timeline-tier-1,
    .timeline-tier-2 {
        color: #1e40af;
        /* blue-800 */
        background-color: #eff6ff;
        /* blue-50 */
        border-color: #bfdbfe;
        /* blue-200 */
    }
</style>

<div id="property-details-content" class="tab-content active" style="display: block;">
    <div class="card">
        <div class="card-header">
            <h2 class="text-lg font-medium">Property Records</h2>
            <div class="flex items-center gap-3">
                <div class="relative">
                    <input type="text" id="property-search" class="form-input w-64 pl-10"
                        placeholder="Search properties...">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
                        <i class="fas fa-search h-4 w-4"></i>
                    </div>
                </div>

                <!-- Instrument Type Smart Filter -->
                <div class="relative">
                    <select id="filter-instrument-type"
                        class="form-input pr-10 appearance-none bg-white font-medium border-blue-200 focus:border-blue-500 focus:ring-blue-500 rounded-lg shadow-sm">
                        <option value="">All Instrument Types</option>
                        @if(isset($instrumentTypes))
                            @foreach($instrumentTypes as $type)
                                <option value="{{ $type }}">{{ $type }}</option>
                            @endforeach
                        @endif
                    </select>
                    <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-blue-500">
                        <i class="fas fa-filter h-3 w-3"></i>
                    </div>
                </div>

                <span id="property-searching-indicator" class="text-sm text-blue-600 font-medium hidden">
                    <span class="inline-block animate-pulse">Searching...</span>
                </span>
                <button id="reset-cards-view" class="btn btn-secondary" style="display: none;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                        class="h-4 w-4 mr-2">
                        <path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"></path>
                        <path d="M21 3v5h-5"></path>
                        <path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"></path>
                        <path d="M3 21v-5h5"></path>
                    </svg>
                    Reset View
                </button>


                <!-- Property Record Button -->
                <button type="button"
                    class="btn btn-primary flex items-center whitespace-nowrap shadow-lg border-2 border-blue-400 bg-gradient-to-r from-blue-500 to-blue-700 text-white hover:from-blue-600 hover:to-blue-800 transition-all"
                    id="add-property-btn">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                        class="h-5 w-5 mr-2">
                        <path d="M12 5v14M5 12h14"></path>
                    </svg>
                    Property Record
                </button>

                <script>
                    document.addEventListener('DOMContentLoaded', function () {
                        const addPropertyBtn = document.getElementById('add-property-btn');

                        if (addPropertyBtn) {
                            addPropertyBtn.addEventListener('click', function () {
                                openAddPropertyModal('Add New Property Record');
                            });
                        }

                        // Function to open Add Property modal with custom title
                        function openAddPropertyModal(title) {
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
                    });
                </script>
            </div>
        </div>
        <div class="card-body">
            <!-- Property Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-6" id="property-cards-container">
                <!-- Add New Property Card -->
                <div class="border-2 border-dashed border-blue-400 rounded-lg shadow-lg cursor-pointer hover:bg-blue-50 transition-all flex flex-col items-center justify-center p-8 bg-gradient-to-br from-blue-50 to-white"
                    id="add-property-card" style="display: none;">
                    <div class="h-16 w-16 rounded-full bg-blue-200 flex items-center justify-center mb-4 shadow">
                        <span class="text-blue-700 text-3xl font-bold">+</span>
                    </div>
                    <h3 class="text-xl font-semibold text-center text-blue-800">Add New Property Record</h3>
                    <p class="text-base text-blue-600 text-center mt-2 font-medium">Click here to create a new property
                        record</p>
                </div>
                <!-- Selected Property Detail Card will be injected here by JS -->
                @php
                    $initialPraCandidates = collect([
                        optional($featuredRecord)->mlsFNo,
                        optional($featuredRecord)->kangisFileNo,
                        optional($featuredRecord)->NewKANGISFileno,
                        optional($featuredRecord)->fileno,
                        optional($featuredRecord)->temp_fileno,
                    ])->filter(function ($value) {
                        return filled($value);
                    })->map(function ($value) {
                        return trim((string) $value);
                    })->values()->all();
                @endphp
                <div id="selected-property-detail-card" class="col-span-3"
                    data-pra-candidates='@json($initialPraCandidates)'>

                    @if($featuredRecord)
                        @php $property = $featuredRecord; @endphp
                        @php
                            $fallbackFileNumber = collect([
                                $property->mlsFNo ?? null,
                                $property->kangisFileNo ?? null,
                                $property->NewKANGISFileno ?? null,
                                $property->fileno ?? null,
                                $property->temp_fileno ?? null,
                            ])->first(function ($value) {
                                return filled($value);
                            });
                            $praPrefillCandidates = collect([
                                $property->mlsFNo ?? null,
                                $property->kangisFileNo ?? null,
                                $property->NewKANGISFileno ?? null,
                                $property->fileno ?? null,
                                $property->temp_fileno ?? null,
                            ])->filter(function ($value) {
                                return filled($value);
                            })->map(function ($value) {
                                return trim((string) $value);
                            })->values()->all();
                        @endphp
                        <div class="border rounded-lg shadow-lg overflow-hidden bg-blue-50 border-blue-200"
                            data-pra-candidates='@json($praPrefillCandidates)'>
                            <div class="bg-blue-100 p-4 border-b border-blue-200">
                                <div class="flex justify-between items-center">
                                    <span
                                        class="bg-blue-200 text-blue-800 border-blue-300 px-3 py-1 rounded-full text-sm font-medium">
                                        {{ $property->title_type ?? 'N/A' }} - Selected Record
                                    </span>
                                    <button class="text-blue-600 hover:text-blue-800 property-options"
                                        data-id="{{ $property->id }}">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                                            fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                            stroke-linejoin="round" class="h-4 w-4">
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
                                            <strong>Location:</strong>
                                            {{ isset($resolvedLocation) ? strtoupper($resolvedLocation) : 'N/A' }}
                                        </div>
                                    </div>

                                    <div class="border-t pt-3">
                                        <div class="grid grid-cols-2 gap-4 text-sm">
                                            <div>
                                                <strong>Instrument Type:</strong>
                                                {{ $property->transaction_type ?? 'N/A' }}
                                            </div>
                                            <div>
                                                <strong>Transaction Date:</strong>
                                                {{ $property->transaction_date ? \Carbon\Carbon::parse($property->transaction_date)->toFormattedDateString() : 'N/A' }}
                                            </div>
                                            <div>
                                                <strong>Registration No:</strong> {{ $property->regNo ?? 'N/A' }}
                                            </div>
                                            <div>
                                                <strong>Instrument Type:</strong>
                                                {{ $property->instrument_type ?? $property->transaction_type ?? 'N/A' }}
                                            </div>
                                        </div>
                                    </div>
                                    @php
                                        $fromParty = $toParty = $thirdParty = $fourthParty = $fifthParty = $fromLabel = $toLabel = $thirdLabel = $fourthLabel = $fifthLabel = '';
                                        $txType = strtolower($property->transaction_type ?? '');
                                        
                                        switch ($txType) {
                                            case 'assignment':
                                                $fromParty = $property->Assignor ?? '';
                                                $toParty = $property->Assignee ?? '';
                                                $fromLabel = 'Assignor';
                                                $toLabel = 'Assignee';
                                                break;
                                            case 'mortgage':
                                            case 'deed of mortgage':
                                                $fromParty = $property->Mortgagor ?? '';
                                                $toParty = $property->Mortgagee ?? '';
                                                $fromLabel = 'Mortgagor';
                                                $toLabel = 'Mortgagee';
                                                break;
                                            case 'tripartite mortgage':
                                                $fromParty = $property->Mortgagor ?? '';
                                                $toParty = $property->Mortgagee ?? '';
                                                $thirdParty = $property->Mortgagor_2 ?? $property->party_3 ?? '';
                                                $fourthParty = $property->Mortgagor_3 ?? $property->party_4 ?? '';
                                                $fifthParty = $property->fourth_Party ?? $property->party_5 ?? '';
                                                $fromLabel = 'Mortgagor';
                                                $toLabel = 'Mortgagee';
                                                $thirdLabel = 'Mortgagor 2';
                                                $fourthLabel = 'Mortgagor 3';
                                                $fifthLabel = 'Fourth Party';
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
                                    @if($fromParty || $toParty || $thirdParty || $fourthParty || $fifthParty)
                                        <div class="border-t pt-3">
                                            <div class="grid grid-cols-2 gap-4 text-sm">
                                                @if($fromParty)
                                                    <div><strong>{{ $fromLabel }}:</strong> {{ $fromParty }}</div>
                                                @endif
                                                @if($toParty)
                                                    <div><strong>{{ $toLabel }}:</strong> {{ $toParty }}</div>
                                                @endif
                                                @if($thirdParty)
                                                    <div><strong>{{ $thirdLabel }}:</strong> {{ $thirdParty }}</div>
                                                @endif
                                                @if($fourthParty)
                                                    <div><strong>{{ $fourthLabel }}:</strong> {{ $fourthParty }}</div>
                                                @endif
                                                @if($fifthParty)
                                                    <div><strong>{{ $fifthLabel }}:</strong> {{ $fifthParty }}</div>
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
                                        <div>MLPP: {{ $property->mlsFNo }}</div>
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
                                        $tempDisplay = $property->temp_fileno ?? null;
                                    @endphp
                                    @if($tempDisplay)
                                        <div>Temp: {{ $tempDisplay }}</div>
                                    @endif
                                </div>
                                <div class="flex gap-2">
                                    <button
                                        class="px-3 py-1 border rounded-md text-sm flex items-center view-property-details bg-blue-600 text-white hover:bg-blue-700"
                                        data-id="{{ $property->id }}">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                                            fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                            stroke-linejoin="round" class="h-4 w-4 mr-1">
                                            <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"></path>
                                            <circle cx="12" cy="12" r="3"></circle>
                                        </svg>
                                        View Full Details
                                    </button>
                                    @if(!empty($property->prop_id))
                                    <button type="button"
                                        class="px-3 py-1 border rounded-md text-sm flex items-center bg-indigo-600 text-white hover:bg-indigo-700"
                                        onclick="openPropertyTimeline('{{ $property->prop_id }}', '{{ $fallbackFileNumber ?? '' }}')">
                                        <i class="fas fa-history mr-1"></i> Timeline
                                    </button>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="border rounded-lg shadow-sm p-6 bg-white">
                            <div class="text-center text-gray-500">
                                <p class="font-medium">No Property Records Found</p>
                                <p class="text-sm">Use the search or add a new record to get started.</p>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
            <br>
            <hr>
            {{-- <button type="button"
                class="btn btn-success flex items-center whitespace-nowrap shadow-lg border-2 border-green-400 bg-gradient-to-r from-green-500 to-green-700 text-white hover:from-green-600 hover:to-green-800 transition-all px-4 py-2 rounded-md text-sm mr-3"
                onclick="window.location.href='{{ $cofoRoute }}'">
                <i class="fas fa-certificate mr-2"></i> COFO
            </button> --}}
            <br>
            <hr>
            <!-- Property Table -->
            <div class="table-container">
                <table id="property-records-table" class="table datatable-nowrap">
                    <thead>
                        <tr>
                            <th>S/N</th>
                            <th>File Number</th>
                            <th>Registration Particulars</th>
                            <th>Instrument Type</th>
                            <th>Party 1</th>
                            <th>Party 2</th>
                            <th>Party 3</th>
                            <th>Party 4</th>
                            <th>Location</th>
                            <th>Date Captured</th>
                            <th>Captured By</th>
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
    document.addEventListener('DOMContentLoaded', function () {
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
        const fallbackText = '<span class="text-gray-400">N/A</span>';

        function debounce(fn, delay = 300) {
            let timeoutId;
            return function (...args) {
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
            || row.mlsFNo
            || row.kangisFileNo
            || row.NewKANGISFileno
            || row.fileno
            || row.temp_fileno
            || '';

        const renderFileNumberCell = (row, type) => {
            const fileNumber = resolveFileNumber(row);
            const timelineCount = row.timeline_count || 1;
            const propId = row.prop_id || '';

            if (type === 'display' || type === 'filter') {
                if (!fileNumber) {
                    return '<span class="text-gray-400">No File Number</span>';
                }

                const safeCount = Number.isFinite(Number(timelineCount)) && Number(timelineCount) > 0 ? Number(timelineCount) : 1;
                const timelineBtn = `<button type="button" class="timeline-badge timeline-tier-1 pra-timeline-btn" data-prop-id="${escapeHtml(String(propId))}" data-file-number="${escapeHtml(fileNumber)}" title="View full property timeline">Timeline (${safeCount})</button>`;
                return `
                    <div class="flex flex-col">
                        <span class="font-medium" title="${escapeHtml(fileNumber)}">
                            ${escapeHtml(fileNumber)}
                        </span>
                        <div class="mt-1 scale-90 origin-left opacity-80">${timelineBtn}</div>
                    </div>
                `;
            }

            return fileNumber;
        };

        const renderPropIdCell = (value, type) => {
            if (type === 'display' || type === 'filter') {
                if (!value) {
                    return '<span class="text-gray-400">No Prop ID</span>';
                }

                return `<span class="inline-flex items-center px-2.5 py-0.5 rounded-full border text-xs font-semibold text-blue-800 border-blue-200 bg-blue-50">${escapeHtml(String(value))}</span>`;
            }

            return value || '';
        };

        const renderTimelineBadge = (count, type) => {
            const numeric = Number(count);
            const safeCount = Number.isFinite(numeric) && numeric > 0 ? numeric : 1;

            if (type === 'sort' || type === 'type') {
                return safeCount;
            }

            const title = `Timeline entries: ${safeCount}`;
            // Use timeline-tier-1 class which now has the same blue color as Prop ID
            return `<span class="timeline-badge timeline-tier-1" title="${escapeHtml(title)}">Timeline (${safeCount})</span>`;
        };

        const toTitleCase = (str) => {
            if (!str) return str;
            return String(str).toLowerCase().replace(/\b\w/g, c => c.toUpperCase());
        };

        const renderPartyCell = (value, type, limit = 60) => {
            if (type === 'display' || type === 'filter') {
                if (!value) {
                    return fallbackText;
                }

                return renderTruncatedCell(toTitleCase(value), limit);
            }

            return value || '';
        };

        const renderDateCell = (value, type) => {
            if (!value) {
                return type === 'sort' || type === 'type' ? 0 : fallbackText;
            }

            // Try parsing — handle ISO, US text ("Apr 5 2026 4:24PM"), etc.
            let date = new Date(value);
            if (Number.isNaN(date.getTime())) {
                // Retry: replace common SQL Server text format quirks
                const cleaned = String(value).replace(/\s+/g, ' ').trim();
                date = new Date(cleaned);
            }

            if (Number.isNaN(date.getTime())) {
                return type === 'sort' || type === 'type' ? 0 : fallbackText;
            }

            if (type === 'sort' || type === 'type') {
                return date.getTime();
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

        const resetActionButtons = () => {
            document.querySelectorAll('.view-property, .edit-property, .delete-property').forEach(button => {
                const cloned = button.cloneNode(true);
                button.replaceWith(cloned);
            });

            if (typeof window.setupPropertyActions === 'function') {
                window.setupPropertyActions();
            }
        };

        // Kebab menu for row actions that need more than an icon. Empty when the user has no
        // item to show (txnFileNoCorrectionMenuItem returns '' without permission). Only PRA
        // rows qualify: in index mode (record_mode=index) this table lists `pic`, not `pra`.
        const isPraRecordMode = new URL(window.location.href).searchParams.get('record_mode') !== 'index';
        const renderPraActionMenu = (id) => {
            const correctItem = (isPraRecordMode && window.txnFileNoCorrectionMenuItem)
                ? window.txnFileNoCorrectionMenuItem('pra', id)
                : '';
            if (!correctItem) {
                return '';
            }
            return `
                <div class="relative inline-block text-left">
                    <button type="button" class="pra-row-menu-toggle text-gray-500 hover:text-gray-700" title="More actions">
                        <i data-lucide="more-vertical" class="h-4 w-4"></i>
                    </button>
                    <div class="txn-fileno-menu hidden absolute right-0 z-20 mt-2 w-56 rounded-md bg-white shadow-lg ring-1 ring-black ring-opacity-5">
                        <div class="py-1">${correctItem}</div>
                    </div>
                </div>`;
        };

        document.addEventListener('click', function (e) {
            const toggle = e.target.closest('.pra-row-menu-toggle');
            const menus = document.querySelectorAll('#property-records-table .txn-fileno-menu');
            if (!toggle) {
                if (!e.target.closest('.txn-fileno-menu')) {
                    menus.forEach(m => m.classList.add('hidden'));
                }
                return;
            }
            e.preventDefault();
            e.stopPropagation();
            const menu = toggle.parentElement.querySelector('.txn-fileno-menu');
            const willOpen = menu && menu.classList.contains('hidden');
            menus.forEach(m => m.classList.add('hidden'));
            if (menu && willOpen) {
                // Fixed, so the scrollX body of the table cannot clip it.
                const rect = toggle.getBoundingClientRect();
                menu.style.position = 'fixed';
                menu.style.top = (rect.bottom + 4) + 'px';
                menu.style.left = Math.max(8, rect.right - 224) + 'px';
                menu.style.right = 'auto';
                menu.classList.remove('hidden');
            }
        }, true);

        window.addEventListener('scroll', function () {
            document.querySelectorAll('#property-records-table .txn-fileno-menu').forEach(m => m.classList.add('hidden'));
        }, true);

        window.TxnFileNoCorrection = Object.assign(window.TxnFileNoCorrection || {}, {
            onChanged: () => {
                if (window.propertyRecordsTableInstance) {
                    window.propertyRecordsTableInstance.ajax.reload(null, false);
                }
            },
        });

        const propertyTable = $('#property-records-table').DataTable({
            dom: 'lrtip',
            processing: true,
            serverSide: true,
            deferRender: true,
            searchDelay: 400,
            autoWidth: false,
            scrollX: true,
            pageLength: 20,
            lengthMenu: [20, 50, 100],
            columnDefs: [
                { targets: '_all', className: 'align-middle' }
            ],
            ajax: {
                url: dataUrl,
                type: 'GET',
                data: function (d) {
                    d.instrument_type = $('#filter-instrument-type').val();
                    // Add other potentially useful filters here
                    const currentUrl = new URL(window.location.href);
                    if (currentUrl.searchParams.has('record_mode')) {
                        d.record_mode = currentUrl.searchParams.get('record_mode');
                    }
                },
                dataSrc: 'data',
                error: function (xhr, error, thrown) {
                    // Ignore AbortError - this is normal when requests are cancelled (e.g., fast typing in search)
                    if (error === 'abort' || thrown === 'abort' || (thrown && thrown.name === 'AbortError')) {
                        console.debug('DataTables request cancelled (normal behavior)');
                        toggleSearchIndicator(false);
                        return;
                    }

                    console.error('DataTables Ajax error:', error, thrown, xhr && xhr.responseText);

                    toggleSearchIndicator(false);

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
            columns: [
                {
                    data: null,
                    name: 'serial',
                    orderable: false,
                    searchable: false,
                    className: 'text-center',
                    render: function (data, type, row, meta) {
                        return meta.row + meta.settings._iDisplayStart + 1;
                    }
                },
                {
                    data: null,
                    name: 'kangisFileNo',
                    render: function (data, type, row) {
                        return renderFileNumberCell(row, type);
                    }
                },
                {
                    data: 'registration_particulars',
                    name: 'regNo',
                    render: function (data, type, row) {
                        const value = data || row.regNo || '0/0/0';

                        if (type === 'display' || type === 'filter') {
                            return renderTruncatedCell(value, 40);
                        }

                        return value;
                    }
                },
                {
                    data: 'transaction_type',
                    name: 'transaction_type',
                    render: function (data, type, row) {
                        const displayValue = data || row.instrument_type;

                        if (type === 'display' || type === 'filter') {
                            return renderTruncatedCell(displayValue, 40);
                        }

                        return displayValue || '';
                    }
                },
                {
                    data: 'party_1',
                    name: 'party_1',
                    render: function (data, type, row) {
                        return renderPartyCell(data, type, 60);
                    }
                },
                {
                    data: 'party_2',
                    name: 'party_2',
                    render: function (data, type, row) {
                        return renderPartyCell(data, type, 60);
                    }
                },
                {
                    data: 'party_3',
                    name: 'party_3',
                    render: function (data, type, row) {
                        return renderPartyCell(data, type, 60);
                    }
                },
                {
                    data: 'party_4',
                    name: 'party_4',
                    render: function (data, type, row) {
                        return renderPartyCell(data, type, 60);
                    }
                },
                {
                    data: 'location_display',
                    name: 'property_description',
                    render: function (data, type, row) {
                        const value = data || row.property_description || row.location || '';
                        if (type === 'display' || type === 'filter') {
                            const upperData = value ? String(value).toUpperCase() : value;
                            return renderTruncatedCell(upperData, 60);
                        }

                        return value || '';
                    }
                },
                {
                    data: 'created_at',
                    name: 'created_at',
                    render: function (data, type, row) {
                        const candidate = data || row.transaction_date || row.deeds_date || null;
                        return renderDateCell(candidate, type);
                    }
                },
                {
                    // captured_by/created_by holds a user id on PRA's own rows and a typed
                    // name on File Indexing's, so send whichever shape it is to the card.
                    data: 'captured_by_name',
                    name: 'captured_by',
                    render: function (data, type, row) {
                        const raw = row.captured_by ?? row.created_by ?? '';
                        const label = data || (String(raw).trim() !== '' && !/^\d+$/.test(String(raw)) ? String(raw) : '');

                        if (type !== 'display') {
                            return label;
                        }

                        if (!label) {
                            return '<span class="text-gray-400">N/A</span>';
                        }

                        const attr = /^\d+$/.test(String(raw).trim())
                            ? 'data-user-id="' + escapeHtml(String(raw).trim()) + '"'
                            : 'data-user-name="' + escapeHtml(label) + '"';

                        return '<span class="upc-trigger" data-user-card ' + attr + ' title="View profile">' + escapeHtml(label) + '</span>';
                    }
                },
                {
                    data: 'id',
                    name: 'actions',
                    orderable: false,
                    searchable: false,
                    render: function (data) {
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
                                ${renderPraActionMenu(data)}
                            </div>
                        `;
                    }
                }
            ],
            order: [[9, 'desc']],
            language: {
                processing: 'Searching...',
                emptyTable: 'No property records found',
                zeroRecords: 'No matching property records found'
            },
            drawCallback: function () {
                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }

                resetActionButtons();
            }
        });

        propertyTable.on('processing.dt', function (e, settings, processing) {
            toggleSearchIndicator(processing);
        });

        propertyTable.one('draw.dt', function () {
            const firstRowNode = $('#property-records-table tbody tr').first();
            const firstRowData = propertyTable.row(firstRowNode).data();

            if (!firstRowData || !firstRowData.id) {
                return;
            }

            $('#property-records-table tbody tr').removeClass('selected-row');
            firstRowNode.addClass('selected-row');

            if (typeof window.loadPropertyDetailsInCards === 'function') {
                window.loadPropertyDetailsInCards(firstRowData.id);
            }
        });

        propertyTable.on('xhr.dt', function () {
            toggleSearchIndicator(false);
        });

        window.propertyRecordsTableInstance = propertyTable;

        // Listen for filter changes
        $('#filter-instrument-type').on('change', function () {
            propertyTable.ajax.reload();
        });

        const executeSearch = (term) => {
            propertyTable.search(term).draw();
        };

        const debouncedSearch = debounce(function (term) {
            executeSearch(term);
        }, 400);

        if (propertySearchInput) {
            propertySearchInput.addEventListener('input', function () {
                debouncedSearch(this.value);
            });
        }

        window.propertyRecordsTableSearch = function (term) {
            debouncedSearch(term);
        };

        $('#property-records-table tbody').on('click', 'tr', function (e) {
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

        window.addEventListener('resize', debounce(function () {
            propertyTable.columns.adjust();
        }, 200));

        // Timeline badge click — open cross-table property timeline modal
        document.getElementById('property-records-table').addEventListener('click', function (e) {
            const btn = e.target.closest('.pra-timeline-btn');
            if (!btn) return;
            e.stopPropagation();
            const propId     = btn.getAttribute('data-prop-id')     || '';
            const fileNumber = btn.getAttribute('data-file-number') || '';
            if (typeof openPropertyTimeline === 'function') {
                openPropertyTimeline(propId, fileNumber);
            }
        });

        // Additional shared functions live in propertycard.js.javascript.
    });
</script>