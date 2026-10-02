@php
    $Property_records = $Property_records ?? ($pra ?? collect());
    $dataRoute = $dataRoute ?? route('propertycard.getData');
    $fallbackRoute = $fallbackRoute ?? route('propertycard.data.fallback');
    $cofoRoute = $cofoRoute ?? route('propertycard.cofo');
@endphp

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
                         
                            <button class="text-gray-700 block w-full px-4 py-2 text-left text-sm hover:bg-gray-100 hover:text-gray-900" role="menuitem" id="index-card-btn">
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
                        $('#property-records-table').DataTable({
                            processing: true,
                            serverSide: true,
                            ajax: {
                                url: '{{ $dataRoute }}',
                                type: 'GET',
                                error: function(xhr, error, thrown) {
                                    console.error('DataTables Ajax error:', error, thrown, xhr && xhr.responseText);
                                    try {
                                        var table = $('#property-records-table').DataTable();
                                        table.ajax.url('{{ $fallbackRoute }}').load();
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
                                        // Prefer official file numbers, but fall back to temporary numbers when missing
                                        const displayFileNumber = row.kangisFileNo
                                            || row.mlsFNo
                                            || row.NewKANGISFileno
                                            || row.fileno
                                            || row.temp_fileno
                                            || row.tempFileno
                                            || row.tempFileNo
                                            || row.tempFileNumber;
                                        return displayFileNumber || 'No File Number';
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
