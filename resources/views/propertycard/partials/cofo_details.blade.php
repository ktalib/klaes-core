<div id="cofo-details-content" class="tab-content">
    <div class="card">
        <div class="card-header">
            <h2 class="text-lg font-medium">Certificate of Occupancy (CofO) Records</h2>
            <div class="flex items-center gap-2">
                <input type="text" id="cofo-search" class="form-input w-64" placeholder="Search CofO records...">
                <button id="reset-cofo-cards-view" class="btn btn-secondary" style="display: none;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 mr-2">
                        <path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"></path>
                        <path d="M21 3v5h-5"></path>
                        <path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"></path>
                        <path d="M3 21v-5h5"></path>
                    </svg>
                    Reset View
                </button>
                <!-- Add New CofO Record Button -->
                <div class="relative inline-block text-left">
                    <button type="button" class="btn btn-primary flex items-center whitespace-nowrap shadow-lg border-2 border-green-400 bg-gradient-to-r from-green-500 to-green-700 text-white hover:from-green-600 hover:to-green-800 transition-all" id="cofo-dropdown-toggle">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5 mr-2">
                            <path d="M12 5v14M5 12h14"></path>
                        </svg>
                        Add New CofO
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 ml-2">
                            <path d="m6 9 6 6 6-6"></path>
                        </svg>
                    </button>
                    
                    <div class="absolute right-0 z-10 mt-2 w-56 origin-top-right rounded-md bg-white shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none hidden" id="cofo-dropdown-menu" role="menu">
                        <div class="py-1" role="none">
                            <button class="text-gray-700 block w-full px-4 py-2 text-left text-sm hover:bg-gray-100 hover:text-gray-900" role="menuitem" id="add-cofo-btn">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 mr-2 inline">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                    <polyline points="14,2 14,8 20,8"></polyline>
                                    <line x1="16" y1="13" x2="8" y2="13"></line>
                                    <line x1="16" y1="17" x2="8" y2="17"></line>
                                    <polyline points="10,9 9,9 8,9"></polyline>
                                </svg>
                                Add New CofO Record
                            </button>
                        </div>
                    </div>
                </div>

                <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const cofoDropdownToggle = document.getElementById('cofo-dropdown-toggle');
                    const cofoDropdownMenu = document.getElementById('cofo-dropdown-menu');
                    
                    if (cofoDropdownToggle && cofoDropdownMenu) {
                        cofoDropdownToggle.addEventListener('click', function(e) {
                            e.stopPropagation();
                            cofoDropdownMenu.classList.toggle('hidden');
                        });
                        
                        // Close dropdown when clicking outside
                        document.addEventListener('click', function() {
                            cofoDropdownMenu.classList.add('hidden');
                        });
                        
                        // Prevent dropdown from closing when clicking inside
                        cofoDropdownMenu.addEventListener('click', function(e) {
                            e.stopPropagation();
                        });
                    }
                });
                </script>
            </div>
        </div>
        <div class="card-body">
            <!-- CofO Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-6" id="cofo-cards-container">
                <!-- Add New CofO Card -->
                <div class="border-2 border-dashed border-green-400 rounded-lg shadow-lg cursor-pointer hover:bg-green-50 transition-all flex flex-col items-center justify-center p-8 bg-gradient-to-br from-green-50 to-white" id="add-cofo-card">
                    <div class="h-16 w-16 rounded-full bg-green-200 flex items-center justify-center mb-4 shadow">
                        <span class="text-green-700 text-3xl font-bold">+</span>
                    </div>
                    <h3 class="text-xl font-semibold text-center text-green-800">Add New CofO Record</h3>
                    <p class="text-base text-green-600 text-center mt-2 font-medium">Click here to create a new Certificate of Occupancy record</p>
                </div>
                <!-- Selected CofO Detail Card will be injected here by JS -->
                <div id="selected-cofo-detail-card" class="col-span-2">
                    @php
                        // Get the first CofO record if any exist
                        $cofoRecords = DB::connection('sqlsrv')->table('CofO')->orderBy('created_at', 'desc')->get();
                        $cofoRecord = $cofoRecords->first();
                    @endphp
                    @if($cofoRecord)
                        <div class="border rounded-lg shadow-lg overflow-hidden bg-green-50 border-green-200">
                            <div class="bg-green-100 p-4 border-b border-green-200">
                                <div class="flex justify-between items-center">
                                    <span class="bg-green-200 text-green-800 border-green-300 px-3 py-1 rounded-full text-sm font-medium">
                                        {{ $cofoRecord->cofo_type ?? 'CofO' }} - Selected Record
                                    </span>
                                    <button class="text-green-600 hover:text-green-800 cofo-options" data-id="{{ $cofoRecord->id }}">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4">
                                            <circle cx="12" cy="12" r="1"></circle>
                                            <circle cx="12" cy="5" r="1"></circle>
                                            <circle cx="12" cy="19" r="1"></circle>
                                        </svg>
                                    </button>
                                </div>
                                <h3 class="mt-2 font-bold text-lg text-green-900">
                                    @if($cofoRecord->kangisFileNo)
                                        {{ $cofoRecord->kangisFileNo }}
                                    @elseif($cofoRecord->mlsFNo)
                                        {{ $cofoRecord->mlsFNo }}
                                    @elseif($cofoRecord->NewKANGISFileno)
                                        {{ $cofoRecord->NewKANGISFileno }}
                                    @else
                                        No File Number
                                    @endif
                                </h3>
                            </div>
                            <div class="p-4">
                                <div class="space-y-4">
                                    <div class="text-sm">
                                        <strong>Description:</strong> {{ $cofoRecord->property_description ?? 'No description available' }}
                                    </div>
                                    <div class="grid grid-cols-2 gap-4 text-sm">
                                        <div>
                                            <strong>LGA/City:</strong> {{ $cofoRecord->lgsaOrCity ?? 'N/A' }}
                                        </div>
                                        <div>
                                            <strong>Plot Number:</strong> {{ $cofoRecord->plot_no ?? 'N/A' }}
                                        </div>
                                        <div>
                                            <strong>Layout:</strong> {{ $cofoRecord->layout ?? 'N/A' }}
                                        </div>
                                        <div>
                                            <strong>Location:</strong> {{ $cofoRecord->location ?? 'N/A' }}
                                        </div>
                                        <div>
                                            <strong>Land Use:</strong> {{ $cofoRecord->land_use ?? 'N/A' }}
                                        </div>
                                        <div>
                                            <strong>CofO Type:</strong> {{ $cofoRecord->cofo_type ?? 'N/A' }}
                                        </div>
                                    </div> 
 
                                    <div class="border-t pt-3">
                                        <div class="grid grid-cols-2 gap-4 text-sm">
                                            <div>
                                                <strong>Transaction Type:</strong> {{ $cofoRecord->transaction_type ?? 'N/A' }}
                                            </div>
                                            <div>
                                                <strong>Transaction Date:</strong> {{ $cofoRecord->transaction_date ? \Carbon\Carbon::parse($cofoRecord->transaction_date)->toFormattedDateString() : 'N/A' }}
                                            </div>
                                            <div>
                                                <strong>Registration No:</strong> {{ $cofoRecord->regNo ?? 'N/A' }}
                                            </div>
                                            <div>
                                                <strong>Instrument Type:</strong> {{ $cofoRecord->instrument_type ?? 'N/A' }}
                                            </div>
                                        </div>
                                    </div>
                                    @php
                                        $fromParty = $toParty = $fromLabel = $toLabel = '';
                                        switch(strtolower($cofoRecord->transaction_type ?? '')) {
                                            case 'assignment':
                                                $fromParty = $cofoRecord->Assignor ?? '';
                                                $toParty = $cofoRecord->Assignee ?? '';
                                                $fromLabel = 'Assignor';
                                                $toLabel = 'Assignee';
                                                break;
                                            case 'mortgage':
                                                $fromParty = $cofoRecord->Mortgagor ?? '';
                                                $toParty = $cofoRecord->Mortgagee ?? '';
                                                $fromLabel = 'Mortgagor';
                                                $toLabel = 'Mortgagee';
                                                break;
                                            case 'surrender':
                                                $fromParty = $cofoRecord->Surrenderor ?? '';
                                                $toParty = $cofoRecord->Surrenderee ?? '';
                                                $fromLabel = 'Surrenderor';
                                                $toLabel = 'Surrenderee';
                                                break;
                                            case 'sub-lease':
                                            case 'lease':
                                                $fromParty = $cofoRecord->Lessor ?? '';
                                                $toParty = $cofoRecord->Lessee ?? '';
                                                $fromLabel = 'Lessor';
                                                $toLabel = 'Lessee';
                                                break;
                                            default:
                                                $fromParty = $cofoRecord->Grantor ?? '';
                                                $toParty = $cofoRecord->Grantee ?? '';
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
                                    @if($cofoRecord->mlsFNo)
                                        <div>MLPP: {{ $cofoRecord->mlsFNo }}</div>
                                    @endif
                                    @if($cofoRecord->kangisFileNo)
                                        <div>KANGIS: {{ $cofoRecord->kangisFileNo }}</div>
                                    @endif
                                    @if($cofoRecord->NewKANGISFileno)
                                        <div>New KANGIS: {{ $cofoRecord->NewKANGISFileno }}</div>
                                    @endif
                                </div>
                                <div class="flex gap-2">
                                    <button class="px-3 py-1 border rounded-md text-sm flex items-center view-cofo-details bg-green-600 text-white hover:bg-green-700" data-id="{{ $cofoRecord->id }}">
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
                        <div class="border rounded-lg shadow-lg overflow-hidden bg-gray-50 border-gray-200 p-8 text-center">
                            <div class="text-gray-500">
                                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mx-auto mb-4 text-gray-400">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                    <polyline points="14,2 14,8 20,8"></polyline>
                                    <line x1="16" y1="13" x2="8" y2="13"></line>
                                    <line x1="16" y1="17" x2="8" y2="17"></line>
                                    <polyline points="10,9 9,9 8,9"></polyline>
                                </svg>
                                <h3 class="text-lg font-medium text-gray-900 mb-2">No CofO Records Found</h3>
                                <p class="text-gray-600">Start by adding your first Certificate of Occupancy record.</p>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
<br>
<hr>
<a href="{{ route('propertycard.index') }}" class="flex items-center text-green-600 hover:text-green-800 bg-green-50 px-2 py-1 rounded border border-green-200 mr-2 text-sm">
    <i class="fas fa-certificate mr-1"></i>Records
</a>
                
                <br>
            <!-- CofO Table -->
            <div class="table-container">
                <table id="cofo-records-table" class="table">
                    <thead>
                        <tr>
                            <th>File Number</th>
                            <th>Description</th>
                            <th>Location</th>
                            <th>Registration Particulars</th>
                            <th>CofO Type</th>
                            <th>Land Use</th>
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

<script>
    document.addEventListener('DOMContentLoaded', function() {
        console.log('CofO details script loaded');
        
        // Initialize CofO DataTable immediately
        let cofoTable = null;
        
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
                    deferLoading: 0, // Don't load data initially
                    ajax: {
                        url: '{{ route("propertycard.getCofOData") }}',
                        type: 'GET',
                        error: function(xhr, error, thrown) {
                            console.error('CofO DataTables Ajax error:', error, thrown);
                            console.error('Response text:', xhr.responseText);
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
                            } else if (row.fileno) {
                                return row.fileno;
                            } else if (row.temp_fileno) {
                                return row.temp_fileno;
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
                        data: 'cofo_type',
                        name: 'cofo_type',
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
                                    <button class="text-blue-500 hover:text-blue-700 transition-colors view-cofo" data-id="${data}">
                                        <i data-lucide="eye" class="h-4 w-4 text-blue-500"></i>
                                    </button>
                                    <button class="text-green-500 hover:text-green-700 transition-colors edit-cofo" data-id="${data}">
                                        <i data-lucide="pencil" class="h-4 w-4 text-green-500"></i>
                                    </button>
                                    <button class="text-red-500 hover:text-red-700 transition-colors delete-cofo" data-id="${data}">
                                        <i data-lucide="trash-2" class="h-4 w-4 text-red-500"></i>
                                    </button>
                                </div>
                            `;
                        }
                    }
                ],
                order: [[7, 'desc']], // Order by Date Captured column (index 7) in descending order
                pageLength: 25,
                responsive: true,
                language: {
                    processing: "Loading CofO records...",
                    emptyTable: "No CofO records found",
                    zeroRecords: "No matching CofO records found"
                },
                drawCallback: function(settings) {
                    // Re-initialize Lucide icons after table redraw
                    if (typeof lucide !== 'undefined') {
                        lucide.createIcons();
                    }
                    
                    // Re-attach event listeners to action buttons
                    setupCofOActions();
                }
            });
            
            console.log('CofO DataTable initialized successfully');
            } catch (error) {
            console.error('Error initializing CofO DataTable:', error);
            }
            }
            
            function loadCofOData() {
            if (cofoTable) {
            console.log('Loading CofO data...');
            cofoTable.ajax.reload();
            }
            }
            
            // Make loadCofOData available globally
            window.loadCofOData = loadCofOData;

        // Setup CofO action buttons
        function setupCofOActions() {
            // Remove existing event listeners to prevent duplicates
            document.querySelectorAll('.view-cofo, .edit-cofo, .delete-cofo').forEach(button => {
                button.replaceWith(button.cloneNode(true));
            });

            // View CofO details
            document.querySelectorAll('.view-cofo').forEach(button => {
                button.addEventListener('click', function() {
                    const cofoId = this.getAttribute('data-id');
                    viewCofODetails(cofoId);
                });
            });

            // Edit CofO
            document.querySelectorAll('.edit-cofo').forEach(button => {
                button.addEventListener('click', function() {
                    const cofoId = this.getAttribute('data-id');
                    editCofO(cofoId);
                });
            });

            // Delete CofO
            document.querySelectorAll('.delete-cofo').forEach(button => {
                button.addEventListener('click', function() {
                    const cofoId = this.getAttribute('data-id');
                    deleteCofO(cofoId);
                });
            });
        }

        // Initialize table when CofO tab becomes active
        const cofoTab = document.getElementById('cofo-tab');
        if (cofoTab) {
            cofoTab.addEventListener('click', function() {
                setTimeout(initializeCofOTable, 100); // Small delay to ensure tab content is visible
            });
        }
        
        // Also listen for the custom event
        document.addEventListener('cofoTabActivated', function() {
            console.log('CofO tab activated event received');
            initializeCofOTable();
        });
        
        // Force initialize the table if the CofO tab is already visible
        if (document.getElementById('cofo-content') && document.getElementById('cofo-content').style.display !== 'none') {
            console.log('CofO tab is already visible, initializing table immediately');
            setTimeout(initializeCofOTable, 500);
        }

        // Delegate row click to load selected CofO details card
        $(document).on('click', '#cofo-records-table tbody tr', function(e) {
            // Ignore clicks on action buttons
            if ($(e.target).closest('.view-cofo, .edit-cofo, .delete-cofo').length) {
                return;
            }
            const $btn = $(this).find('.view-cofo');
            const cofoId = $btn.data('id');
            if (cofoId && typeof window.loadCofODetailsInCards === 'function') {
                // Highlight selection
                $('#cofo-records-table tbody tr').removeClass('selected-row');
                $(this).addClass('selected-row');
                // Load details into the selected-cofo-detail-card
                window.loadCofODetailsInCards(cofoId);
            }
        });
    });

    // Placeholder functions for CofO operations (to be implemented)
    function viewCofODetails(id) {
        console.log('View CofO details for ID:', id);
        // TODO: Implement view CofO details modal
    }

    function editCofO(id) {
        console.log('Edit CofO for ID:', id);
        // TODO: Implement edit CofO modal
    }

    function deleteCofO(id) {
        console.log('Delete CofO for ID:', id);
        // TODO: Implement delete CofO functionality
    }

    // Function to load CofO details in cards (to be implemented)
    window.loadCofODetailsInCards = function(cofoId) {
        console.log('Load CofO details in cards for ID:', cofoId);
        // TODO: Implement loading CofO details in the selected card
    };
</script>