<div id="property-details-content" class="tab-content active" style="display: block;">
    <div class="card">
        <div class="card-header">
            <h2 class="text-lg font-medium">Property Records</h2>
            <div class="flex items-center gap-2">
                <input type="text" id="property-search" class="form-input w-64" placeholder="Search properties...">
                <button id="reset-cards-view" class="btn btn-secondary" style="display: none;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 mr-2">
                        <path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"></path>
                        <path d="M21 3v5h-5"></path>
                        <path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"></path>
                        <path d="M3 21v-5h5"></path>
                    </svg>
                    Reset View
                </button>
                <!-- Improved Add New Property Card Button -->
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
                            <button class="text-gray-700 block w-full px-4 py-2 text-left text-sm hover:bg-gray-100 hover:text-gray-900" role="menuitem" id="add-property-btn">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 mr-2 inline">
                                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                                    <polyline points="9,22 9,12 15,12 15,22"></polyline>
                                </svg>
                                Add New Property Record
                            </button>
                            <button class="text-gray-700 block w-full px-4 py-2 text-left text-sm hover:bg-gray-100 hover:text-gray-900" role="menuitem">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 mr-2 inline">
                                    <rect width="18" height="18" x="3" y="3" rx="2"></rect>
                                    <path d="M9 8h6m-6 4h6m-6 4h6"></path>
                                </svg>
                                Index Card
                            </button>
                        </div>
                    </div>
                </div>

                <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const dropdownToggle = document.getElementById('dropdown-toggle');
                    const dropdownMenu = document.getElementById('dropdown-menu');
                    
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
                });
                </script>
            </div>
        </div>
        <div class="card-body">
            <!-- Property Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-6" id="property-cards-container">
                <!-- Improved Add New Property Card -->
                <div class="border-2 border-dashed border-blue-400 rounded-lg shadow-lg cursor-pointer hover:bg-blue-50 transition-all flex flex-col items-center justify-center p-8 bg-gradient-to-br from-blue-50 to-white" id="add-property-card">
                    <div class="h-16 w-16 rounded-full bg-blue-200 flex items-center justify-center mb-4 shadow">
                        <span class="text-blue-700 text-3xl font-bold">+</span>
                    </div>
                    <h3 class="text-xl font-semibold text-center text-blue-800">Add New Property Record</h3>
                    <p class="text-base text-blue-600 text-center mt-2 font-medium">Click here to create a new property record</p>
                </div>
                <!-- Selected Property Detail Card will be injected here by JS -->
                <div id="selected-property-detail-card" class="col-span-2">
                    @if($Property_records->count())
                        @php $property = $Property_records->first(); @endphp
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
                                    @if($property->kangisFileNo)
                                        {{ $property->kangisFileNo }}
                                    @elseif($property->mlsFNo)
                                        {{ $property->mlsFNo }}
                                    @elseif($property->NewKANGISFileno)
                                        {{ $property->NewKANGISFileno }}
                                    @else
                                        No File Number
                                    @endif
                                </h3>
                            </div>
                            <div class="p-4">
                                <div class="space-y-4">
                                    <div class="text-sm">
                                        <strong>Description:</strong> {{ $property->property_description ?? 'No description available' }}
                                    </div>
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
                                            <strong>Location:</strong> {{ $property->property_description ?? 'N/A' }}
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
                    @endif
                </div>
            </div>

            <!-- Property Table -->
            <div class="table-container">
                <table id="property-records-table" class="table">
                    <thead>
                        <tr>
                            <th>File Number</th>
                            <th>Description</th>
                            <th>Location</th>
                            <th>Registration Particulars</th>
                       
                            <th>Instrument Type</th>
                            <th>Transaction Date</th>
                            <th>DATE CAPTURED</th>
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

        // Initialize DataTable
        $('#property-records-table').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: '{{ route("propertycard.getData") }}',
                type: 'GET',
                error: function(xhr, error, thrown) {
                    console.error('DataTables Ajax error:', error, thrown, xhr && xhr.responseText);
                    try {
                        var table = $('#property-records-table').DataTable();
                        table.ajax.url('{{ route("propertycard.data.fallback") }}').load();
                    } catch (e) {
                        console.error('Failed to switch to fallback data source:', e);
                    }
                }
            },
            columns: [
                {
                    data: null,
                    name: 'file_number',
                    render: function(data, type, row) {
                        if (row.kangisFileNo) {
                            return row.kangisFileNo;
                        } else if (row.mlsFNo) {
                            return row.mlsFNo;
                        } else if (row.NewKANGISFileno) {
                            return row.NewKANGISFileno;
                        } else {
                            return 'No File Number';
                        }
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
                    data: 'property_description',
                    name: 'property_description',
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
                    data: 'transaction_type',
                    name: 'transaction_type',
                    render: function(data, type, row) {
                        return data || 'N/A';
                    }
                },
                {
                    data: 'transaction_date',
                    name: 'transaction_date',
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
            order: [[6, 'desc']], // Order by Date Captured column (index 6) in descending order
            pageLength: 25,
            responsive: true,
            language: {
                processing: "Loading property records...",
                emptyTable: "No property records found",
                zeroRecords: "No matching property records found"
            },
            drawCallback: function(settings) {
                // Re-initialize Lucide icons after table redraw
                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }
                
                // Re-attach event listeners to action buttons
                setupPropertyActions();
            }
        });

        // Delegate row click to load selected property details card
        $('#property-records-table tbody').on('click', 'tr', function(e) {
            // Ignore clicks on action buttons
 
            if ($(e.target).closest('.view-property, .edit-property, .delete-property').length) {
                return;
            }
            const $btn = $(this).find('.view-property');
            const propertyId = $btn.data('id');
            if (propertyId && typeof window.loadPropertyDetailsInCards === 'function') {
                // Highlight selection
                $('#property-records-table tbody tr').removeClass('selected-row');
                $(this).addClass('selected-row');
                // Load details into the selected-property-detail-card
                window.loadPropertyDetailsInCards(propertyId);
            }
        });

        // Setup property action buttons
        function setupPropertyActions() {
            // Remove existing event listeners to prevent duplicates
            document.querySelectorAll('.view-property, .edit-property, .delete-property').forEach(button => {
                button.replaceWith(button.cloneNode(true));
            });

            // View property details
            document.querySelectorAll('.view-property').forEach(button => {
                button.addEventListener('click', function() {
                    const propertyId = this.getAttribute('data-id');
                    viewPropertyDetails(propertyId);
                });
            });

            // Edit property
            document.querySelectorAll('.edit-property').forEach(button => {
                button.addEventListener('click', function() {
                    const propertyId = this.getAttribute('data-id');
                    editProperty(propertyId);
                });
            });

            // Delete property
            document.querySelectorAll('.delete-property').forEach(button => {
                button.addEventListener('click', function() {
                    const propertyId = this.getAttribute('data-id');
                    deleteProperty(propertyId);
                });
            });
        }

        // Functions are defined in the shared JS include; no placeholders here to avoid conflicts.
    });
</script>