    <script>
        // Encumbrance type descriptions mapping
        const encumbranceDescriptions = {
            'mortgage': 'A legal interest placed by a lender as security for a loan.',
            'lien': 'A right to retain property until a debt owed is settled.',
            'charge': 'A registered financial claim or interest over the property.',
            'leasehold-interest': 'Where a property is leased to another party, limiting the rights of the freeholder.',
            'sub-lease': 'Further lease interests carved out of the primary lease.',
            'easement': 'A right for a third party to use part of the property (e.g., access, passage).',
            'court-order': 'A judicial restriction on property transactions.',
            'pending-litigation': 'A notice that the property is subject to an ongoing court case.',
            'power-of-attorney': 'Where legal authority is granted to another party to act on property matters.',
            'caution': 'A warning entered to restrict dealings until lifted.',
            'dispute-investigation': 'Where the property is under review by DCIV or another regulatory unit.',
            'deed-assignment': 'Restriction until transfer registration is finalized.',
            'probate': 'Restrictions pending estate administration or inheritance claim.',
            'government-acquisition': 'If the land falls under acquisition or designated government use.',
            'unpaid-charges': 'Encumbrance for outstanding ground rent, service charges, or development levies.'
        };

        // Mock data
        const mockFileNumbers = [
            {
                value: "CON-COM-2024-001",
                label: "CON-COM-2024-001",
                regParticulars: { serialNo: "1", pageNo: "1", volumeNo: "2" },
            },
            {
                value: "CON-COM-2024-002",
                label: "CON-COM-2024-002",
                regParticulars: { serialNo: "1", pageNo: "1", volumeNo: "3" },
            },
            {
                value: "CON-COM-2019-296",
                label: "CON-COM-2019-296",
                regParticulars: { serialNo: "1", pageNo: "1", volumeNo: "4" },
            },
            {
                value: "RES-2024-001",
                label: "RES-2024-001",
                regParticulars: { serialNo: "1", pageNo: "1", volumeNo: "5" },
            },
            {
                value: "RES-2024-002",
                label: "RES-2024-002",
                regParticulars: { serialNo: "1", pageNo: "1", volumeNo: "6" },
            },
            {
                value: "RES-2015-4859",
                label: "RES-2015-4859",
                regParticulars: { serialNo: "1", pageNo: "1", volumeNo: "7" },
            },
            {
                value: "COM-2024-001",
                label: "COM-2024-001",
                regParticulars: { serialNo: "1", pageNo: "1", volumeNo: "8" },
            },
            {
                value: "COM-2024-002",
                label: "COM-2024-002",
                regParticulars: { serialNo: "1", pageNo: "1", volumeNo: "9" },
            },
            {
                value: "COM-91-249",
                label: "COM-91-249",
                regParticulars: { serialNo: "1", pageNo: "1", volumeNo: "10" },
            },
            {
                value: "IND-2024-001",
                label: "IND-2024-001",
                regParticulars: { serialNo: "1", pageNo: "1", volumeNo: "11" },
            },
            {
                value: "IND-2024-002",
                label: "IND-2024-002",
                regParticulars: { serialNo: "1", pageNo: "1", volumeNo: "12" },
            },
            {
                value: "AGR-2024-001",
                label: "AGR-2024-001",
                regParticulars: { serialNo: "1", pageNo: "1", volumeNo: "13" },
            },
        ];

        let mockCaveats = [
            {
                id: "1",
                caveatNumber: "CAV-2024-001",
                encumbranceType: "Mortgage",
                typeOfDeed: "Assignment",
                fileNumber: "RES-2024-001",
                location: "Plot 123, Block A, Kano Layout",
                petitioner: "First Bank Nigeria Ltd",
                grantee: "Musa Ali",
                regParticulars: {
                    serialNo: "1",
                    pageNo: "1",
                    volumeNo: "2",
                },
                startDate: "2024-01-15",
                instructions: "Mortgage security for loan facility",
                remarks: "Standard mortgage caveat",
                status: "active",
                createdBy: "Officer A",
                dateCreated: "2024-01-15T10:30:00Z",
            },
            {
                id: "2",
                caveatNumber: "CAV-2024-002",
                encumbranceType: "Court Order",
                typeOfDeed: "Sublease",
                fileNumber: "COM-2024-001",
                location: "Plot 456, Block B, Kano Layout",
                petitioner: "Kano State High Court",
                grantee: "Jane Smith",
                regParticulars: {
                    serialNo: "1",
                    pageNo: "1",
                    volumeNo: "6",
                },
                startDate: "2024-02-10",
                releaseDate: "2024-03-15",
                instructions: "Court injunction pending litigation",
                remarks: "Released after court settlement",
                status: "released",
                createdBy: "Officer B",
                dateCreated: "2024-02-10T14:20:00Z",
            },
            {
                id: "3",
                caveatNumber: "CAV-2024-003",
                encumbranceType: "Litigation",
                typeOfDeed: "Gift",
                fileNumber: "CON-COM-2019-296",
                location: "Plot 789, Block C, Kano Layout",
                petitioner: "ABC Legal Chambers",
                grantee: "Michael Johnson",
                regParticulars: {
                    serialNo: "1",
                    pageNo: "1",
                    volumeNo: "9",
                },
                startDate: "2024-03-05",
                instructions: "Property dispute litigation",
                remarks: "Pending court resolution",
                status: "active",
                createdBy: "Officer C",
                dateCreated: "2024-03-05T09:15:00Z",
            },
            {
                id: "4",
                caveatNumber: "CAV-2024-004",
                encumbranceType: "Charge",
                typeOfDeed: "Lease",
                fileNumber: "RES-2024-002",
                location: "Plot 321, Block D, Kano Layout",
                petitioner: "Unity Bank Plc",
                grantee: "Sarah Williams",
                regParticulars: {
                    serialNo: "1",
                    pageNo: "1",
                    volumeNo: "12",
                },
                startDate: "2024-03-20",
                instructions: "Bank charge for overdraft facility",
                remarks: "Active charge on property",
                status: "active",
                createdBy: "Officer D",
                dateCreated: "2024-03-20T11:45:00Z",
            },
            {
                id: "5",
                caveatNumber: "CAV-2024-005",
                encumbranceType: "Lien",
                typeOfDeed: "Power of Attorney",
                fileNumber: "COM-2024-002",
                location: "Plot 654, Block E, Kano Layout",
                petitioner: "Construction Company Ltd",
                grantee: "David Brown",
                regParticulars: {
                    serialNo: "1",
                    pageNo: "1",
                    volumeNo: "15",
                },
                startDate: "2024-04-01",
                releaseDate: "2024-04-20",
                instructions: "Contractor's lien for unpaid work",
                remarks: "Lifted after payment settlement",
                status: "lifted",
                createdBy: "Officer E",
                dateCreated: "2024-04-01T08:30:00Z",
            }
        ];

        // State variables
        var caveats = [...mockCaveats];
        var searchTerm = "";
        var statusFilter = "all";
        var selectedCaveat = null;
        var activeTab = "place";
        var isLoading = false;
        var fileNumberMode = "selector"; // "selector" or "manual"

        // Mock data arrays (existing)
        

        // Form data
        const formData = {
            encumbranceType: "",
            typeOfDeed: "",
            fileNumber: "",
            location: "",
            petitioner: "",
            grantee: "",
            serialNo: "",
            pageNo: "",
            volumeNo: "",
            registrationNumber: "",
            startDate: "",
            releaseDate: "",
            instructions: "",
            remarks: "",
        };

        // Initialize the application
        document.addEventListener('DOMContentLoaded', function() {
            initializeTabs();
            initializeEventListeners();
            updateStats();
            renderCaveatsList();
            renderActiveCaveatsList();
            renderCaveatsTable();
            updateCaveatNumber();
            updateDateCreated();
            setDefaultStartDate();
        });

        // Initialize tab functionality
        function initializeTabs() {
            const tabTriggers = document.querySelectorAll('.tab-trigger');
            tabTriggers.forEach(trigger => {
                trigger.addEventListener('click', function() {
                    const tab = this.getAttribute('data-tab');
                    setActiveTab(tab);
                });
            });
        }

        // Set active tab
        function setActiveTab(tab) {
            activeTab = tab;
            
            // Update UI
            document.querySelectorAll('.tab-trigger').forEach(trigger => {
                if (trigger.getAttribute('data-tab') === tab) {
                    trigger.classList.add('bg-white', 'text-blue-600');
                    trigger.classList.remove('text-gray-600');
                } else {
                    trigger.classList.remove('bg-white', 'text-blue-600');
                    trigger.classList.add('text-gray-600');
                }
            });
            
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            
            document.getElementById(`tab-${tab}`).classList.add('active');
            
            // Update content based on tab
            if (tab === 'place') {
                updateCaveatNumber();
                updateDateCreated();
                setDefaultStartDate();
            }
        }

        // Hide the file number dropdown and clear selections
        function hideFileNumberDropdown() {
            console.log('hideFileNumberDropdown called');
            
            const modal = document.getElementById('file-number-modal');
            if (modal) {
                modal.classList.add('hidden');
            }
            
            // Reset selected file number
            if (typeof selectedFileNumber !== 'undefined') {
                selectedFileNumber = null;
            }
            
            // Clear search input
            const searchInput = document.getElementById('file-number-search');
            if (searchInput) {
                searchInput.value = '';
            }
        }

        // Initialize file number selector
        function initializeFileNumberSelector() {
            console.log('Initializing file number selector...');
            
            const trigger = document.getElementById('file-number-trigger');
            const popover = document.getElementById('file-number-popover');
            const searchInput = document.getElementById('file-number-search');
            const loadingDiv = document.getElementById('file-number-loading');
            const resultsDiv = document.getElementById('file-number-results');
            const noResultsDiv = document.getElementById('file-number-no-results');

            console.log('Elements found:', {
                trigger: !!trigger,
                popover: !!popover, 
                searchInput: !!searchInput,
                loadingDiv: !!loadingDiv,
                resultsDiv: !!resultsDiv,
                noResultsDiv: !!noResultsDiv
            });

            if (!trigger || !popover) {
                console.error('File number selector elements not found');
                return;
            }

            // Toggle dropdown when trigger is clicked
            trigger.addEventListener('click', function() {
                console.log('Trigger clicked');
                popover.classList.toggle('hidden');
                if (!popover.classList.contains('hidden')) {
                    console.log('Loading top file numbers...');
                    // Load top file numbers when dropdown opens
                    loadTopFileNumbers();
                    // Focus on search input
                    if (searchInput) {
                        setTimeout(() => searchInput.focus(), 100);
                    }
                }
            });

            // Search input event listener
            if (searchInput) {
                searchInput.addEventListener('input', function(e) {
                    const query = e.target.value.trim();
                    console.log('Search input changed:', query);
                    if (query.length >= 2) {
                        debounceFileNumberSearch(query);
                    } else if (query.length === 0) {
                        loadTopFileNumbers();
                    } else {
                        hideFileNumberDropdown();
                    }
                });

                searchInput.addEventListener('focus', function() {
                    const query = this.value.trim();
                    console.log('Search input focused, query:', query);
                    if (query.length >= 2) {
                        debounceFileNumberSearch(query);
                    } else if (query.length === 0) {
                        loadTopFileNumbers();
                    }
                });
            }

            // Close popover when clicking outside
            document.addEventListener('click', function(event) {
                if (!trigger.contains(event.target) && !popover.contains(event.target)) {
                    popover.classList.add('hidden');
                }
            });

            // Clear selection button
            const clearBtn = document.getElementById('clear-selection-btn');
            if (clearBtn) {
                clearBtn.addEventListener('click', function() {
                    clearSelectedFileNumber();
                });
            }

            // Change file number button
            const changeBtn = document.getElementById('change-file-number');
            if (changeBtn) {
                changeBtn.addEventListener('click', function() {
                    clearSelectedFileNumber();
                    // Open dropdown for new selection
                    if (popover) {
                        popover.classList.remove('hidden');
                        loadTopFileNumbers();
                        if (searchInput) {
                            setTimeout(() => searchInput.focus(), 100);
                        }
                    }
                });
            }
        }

        // Debounced search function
        let searchTimeout;
        function debounceFileNumberSearch(query) {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                searchFileNumbers(query);
            }, 300);
        }

        function loadTopFileNumbers() {
            console.log('loadTopFileNumbers called');
            
            const loadingDiv = document.getElementById('file-number-loading');
            const resultsDiv = document.getElementById('file-number-results');
            const noResultsDiv = document.getElementById('file-number-no-results');

            console.log('Loading elements found:', {
                loadingDiv: !!loadingDiv,
                resultsDiv: !!resultsDiv,
                noResultsDiv: !!noResultsDiv
            });

            if (!loadingDiv || !resultsDiv || !noResultsDiv) {
                console.error('Required elements not found for loadTopFileNumbers');
                return;
            }

            // Show loading state
            loadingDiv.classList.remove('hidden');
            resultsDiv.classList.add('hidden');
            noResultsDiv.classList.add('hidden');

            console.log('Making API call to /file-numbers/api/top');

            // Make API call to get top 10 file numbers
            fetch('/file-numbers/api/top')
                .then(response => {
                    console.log('API response status:', response.status);
                    return response.json();
                })
                .then(data => {
                    console.log('API response data:', data);
                    loadingDiv.classList.add('hidden');

                    if (data.success && data.data && data.data.length > 0) {
                        console.log('Rendering', data.data.length, 'results');
                        renderFileNumberResults(data.data);
                        resultsDiv.classList.remove('hidden');
                    } else {
                        console.log('No results or API call failed');
                        noResultsDiv.classList.remove('hidden');
                    }
                })
                .catch(error => {
                    console.error('Top file numbers load error:', error);
                    loadingDiv.classList.add('hidden');
                    
                    // Show error message in no results div
                    noResultsDiv.innerHTML = `
                        <div class="p-3 text-center text-red-600">
                            <i class="fas fa-exclamation-triangle mb-2"></i>
                            <div>Failed to load file numbers. Please try again.</div>
                            <div class="text-xs mt-1">Error: ${error.message}</div>
                        </div>
                    `;
                    noResultsDiv.classList.remove('hidden');
                });
        }

        function searchFileNumbers(query) {
            console.log('searchFileNumbers called with query:', query);
            
            const loadingDiv = document.getElementById('file-number-loading');
            const resultsDiv = document.getElementById('file-number-results');
            const noResultsDiv = document.getElementById('file-number-no-results');

            if (!loadingDiv || !resultsDiv || !noResultsDiv) {
                console.error('Required elements not found for searchFileNumbers');
                return;
            }

            // Show loading state
            loadingDiv.classList.remove('hidden');
            resultsDiv.classList.add('hidden');
            noResultsDiv.classList.add('hidden');

            console.log('Making API call to /file-numbers/api/search?query=' + query);

            // Make API call to search file numbers
            fetch(`/file-numbers/api/search?query=${encodeURIComponent(query)}&limit=10`)
                .then(response => {
                    console.log('Search API response status:', response.status);
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Search API response data:', data);
                    loadingDiv.classList.add('hidden');

                    if (data.success && data.data && data.data.length > 0) {
                        console.log('Rendering', data.data.length, 'search results');
                        renderFileNumberResults(data.data);
                        resultsDiv.classList.remove('hidden');
                    } else {
                        console.log('No search results found');
                        noResultsDiv.classList.remove('hidden');
                    }
                })
                .catch(error => {
                    console.error('File number search error:', error);
                    loadingDiv.classList.add('hidden');
                    
                    // Show error message in no results div
                    noResultsDiv.innerHTML = `
                        <div class="p-3 text-center text-red-600">
                            <i class="fas fa-exclamation-triangle mb-2"></i>
                            <div>Search failed. Please try again.</div>
                            <div class="text-xs mt-1">Error: ${error.message}</div>
                        </div>
                    `;
                    noResultsDiv.classList.remove('hidden');
                });
        }

        function renderFileNumberResults(results) {
            console.log('renderFileNumberResults called with', results.length, 'results');
            
            const resultsDiv = document.getElementById('file-number-results');
            if (!resultsDiv) {
                console.error('Results div not found');
                return;
            }

            resultsDiv.innerHTML = results.map(result => `
                <div class="file-number-option p-3 hover:bg-gray-50 cursor-pointer border-b border-gray-100" 
                     onclick="selectFileNumber('${result.kangis_file_no || result.mlsf_no || result.new_kangis_file_no || 'N/A'}', '${result.file_name || 'Property File'}', '${result.status}', ${result.id})">
                    <div class="flex justify-between items-start">
                        <div class="flex-1">
                            <div class="font-semibold text-gray-900">${result.kangis_file_no || result.mlsf_no || result.new_kangis_file_no || 'N/A'}</div>
                            <div class="text-sm text-gray-600 mt-1">${result.file_name || 'Property File'}</div>
                            ${result.mlsf_no && result.kangis_file_no !== result.mlsf_no ? `<div class="text-xs text-gray-500 mt-1">MLSF: ${result.mlsf_no}</div>` : ''}
                        </div>
                        <div class="ml-3">
                            <span class="px-2 py-1 text-xs rounded-full ${result.status === 'Active' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'}">
                                ${result.status}
                            </span>
                        </div>
                    </div>
                </div>
            `).join('');
        }

        function selectFileNumber(fileNumber, property, status, id = null) {
            console.log('selectFileNumber called:', fileNumber, property, status, id);
            
            selectedFileNumber = fileNumber;
            
            // Update hidden input
            const hiddenInput = document.getElementById('file_number');
            if (hiddenInput) {
                hiddenInput.value = fileNumber;
            }

            // Update trigger button text
            const triggerValue = document.getElementById('file-number-value');
            if (triggerValue) {
                triggerValue.textContent = fileNumber;
                triggerValue.classList.remove('text-gray-500');
                triggerValue.classList.add('text-gray-900');
            }

            // Show selected file info
            const selectedInfo = document.getElementById('selected-file-info');
            const selectedFileNumber = document.getElementById('selected-file-number');
            if (selectedInfo && selectedFileNumber) {
                selectedFileNumber.textContent = fileNumber;
                selectedInfo.classList.remove('hidden');
            }

            // Close dropdown
            const popover = document.getElementById('file-number-popover');
            if (popover) {
                popover.classList.add('hidden');
            }

            // Clear search input
            const searchInput = document.getElementById('file-number-search');
            if (searchInput) {
                searchInput.value = '';
            }
        }

        function clearSelectedFileNumber() {
            console.log('clearSelectedFileNumber called');
            
            selectedFileNumber = "";
            
            // Clear hidden input
            const hiddenInput = document.getElementById('file_number');
            if (hiddenInput) {
                hiddenInput.value = '';
            }

            // Reset trigger button text
            const triggerValue = document.getElementById('file-number-value');
            if (triggerValue) {
                triggerValue.textContent = 'Search and select file number...';
                triggerValue.classList.add('text-gray-500');
                triggerValue.classList.remove('text-gray-900');
            }

            // Hide selected file info
            const selectedInfo = document.getElementById('selected-file-info');
            if (selectedInfo) {
                selectedInfo.classList.add('hidden');
            }

            // Clear search input
            const searchInput = document.getElementById('file-number-search');
            if (searchInput) {
                searchInput.value = '';
            }
        }

        function loadTopFileNumbers() {
            console.log('loadTopFileNumbers called');
            
            const loadingDiv = document.getElementById('file-number-loading');
            const resultsDiv = document.getElementById('file-number-results');
            const noResultsDiv = document.getElementById('file-number-no-results');

            console.log('Loading elements found:', {
                loadingDiv: !!loadingDiv,
                resultsDiv: !!resultsDiv,
                noResultsDiv: !!noResultsDiv
            });

            if (!loadingDiv || !resultsDiv || !noResultsDiv) {
                console.error('Required elements not found for loadTopFileNumbers');
                return;
            }

            // Show loading state
            loadingDiv.classList.remove('hidden');
            resultsDiv.classList.add('hidden');
            noResultsDiv.classList.add('hidden');

            console.log('Making API call to /file-numbers/api/top');

            // Make API call to get top 10 file numbers
            fetch('/file-numbers/api/top')
                .then(response => {
                    console.log('API response status:', response.status);
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('API response data:', data);
                    loadingDiv.classList.add('hidden');

                    if (data.success && data.data && data.data.length > 0) {
                        console.log('Rendering', data.data.length, 'results');
                        renderFileNumberResults(data.data);
                        resultsDiv.classList.remove('hidden');
                    } else {
                        console.log('No results or API call failed');
                        noResultsDiv.classList.remove('hidden');
                    }
                })
                .catch(error => {
                    console.error('Top file numbers load error:', error);
                    loadingDiv.classList.add('hidden');
                    
                    // Show error message in no results div
                    noResultsDiv.innerHTML = `
                        <div class="p-3 text-center text-red-600">
                            <i class="fas fa-exclamation-triangle mb-2"></i>
                            <div>Failed to load file numbers. Please try again.</div>
                            <div class="text-xs mt-1">Error: ${error.message}</div>
                        </div>
                    `;
                    noResultsDiv.classList.remove('hidden');
                });
        }

        function searchFileNumbers(query) {
            console.log('searchFileNumbers called with query:', query);
            
            const loadingDiv = document.getElementById('file-number-loading');
            const resultsDiv = document.getElementById('file-number-results');
            const noResultsDiv = document.getElementById('file-number-no-results');

            if (!loadingDiv || !resultsDiv || !noResultsDiv) {
                console.error('Required elements not found for searchFileNumbers');
                return;
            }

            // Show loading state
            loadingDiv.classList.remove('hidden');
            resultsDiv.classList.add('hidden');
            noResultsDiv.classList.add('hidden');

            console.log('Making API call to /file-numbers/api/search');

            // Make API call to search file numbers
            fetch(`/file-numbers/api/search?query=${encodeURIComponent(query)}&limit=10`)
                .then(response => {
                    console.log('Search API response status:', response.status);
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Search API response data:', data);
                    loadingDiv.classList.add('hidden');

                    if (data.success && data.data && data.data.length > 0) {
                        console.log('Rendering', data.data.length, 'search results');
                        renderFileNumberResults(data.data);
                        resultsDiv.classList.remove('hidden');
                    } else {
                        console.log('No search results found');
                        noResultsDiv.classList.remove('hidden');
                    }
                })
                .catch(error => {
                    console.error('File number search error:', error);
                    loadingDiv.classList.add('hidden');
                    
                    // Show error message in no results div
                    noResultsDiv.innerHTML = `
                        <div class="p-3 text-center text-red-600">
                            <i class="fas fa-exclamation-triangle mb-2"></i>
                            <div>Search failed. Please try again.</div>
                            <div class="text-xs mt-1">Error: ${error.message}</div>
                        </div>
                    `;
                    noResultsDiv.classList.remove('hidden');
                });
        }

        function renderFileNumberResults(results) {
            console.log('renderFileNumberResults called with', results.length, 'results');
            const resultsDiv = document.getElementById('file-number-results');
            if (!resultsDiv) return;

            resultsDiv.innerHTML = results.map(result => `
                <div class="file-number-option p-3 hover:bg-gray-50 cursor-pointer border-b border-gray-100" 
                     onclick="selectFileNumber('${result.kangis_file_no || result.mlsf_no || result.new_kangis_file_no || 'N/A'}', '${result.file_name || 'Property File'}', '${result.status}', ${result.id})">
                    <div class="flex justify-between items-start">
                        <div class="flex-1">
                            <div class="font-semibold text-gray-900">${result.kangis_file_no || result.mlsf_no || result.new_kangis_file_no || 'N/A'}</div>
                            <div class="text-sm text-gray-600 mt-1">${result.file_name || 'Property File'}</div>
                            ${result.mlsf_no && result.kangis_file_no !== result.mlsf_no ? `<div class="text-xs text-gray-500 mt-1">MLSF: ${result.mlsf_no}</div>` : ''}
                        </div>
                        <div class="ml-3">
                            <span class="px-2 py-1 text-xs rounded-full ${result.status === 'Active' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'}">
                                ${result.status}
                            </span>
                        </div>
                    </div>
                </div>
            `).join('');
        }

        function selectFileNumber(fileNumber, property, status, id = null) {
            console.log('selectFileNumber called:', fileNumber, property, status, id);
            selectedFileNumber = fileNumber;
            
            // Update hidden input
            const hiddenInput = document.getElementById('file_number');
            if (hiddenInput) {
                hiddenInput.value = fileNumber;
            }

            // Update trigger button text
            const triggerValue = document.getElementById('file-number-value');
            if (triggerValue) {
                triggerValue.textContent = fileNumber;
                triggerValue.classList.remove('text-gray-500');
                triggerValue.classList.add('text-gray-900');
            }

            // Show selected file info
            const selectedInfo = document.getElementById('selected-file-info');
            const selectedFileNumber = document.getElementById('selected-file-number');
            if (selectedInfo && selectedFileNumber) {
                selectedFileNumber.textContent = fileNumber;
                selectedInfo.classList.remove('hidden');
            }

            // Close dropdown
            const popover = document.getElementById('file-number-popover');
            if (popover) {
                popover.classList.add('hidden');
            }

            // Clear search input
            const searchInput = document.getElementById('file-number-search');
            if (searchInput) {
                searchInput.value = '';
            }
        }

        function clearSelectedFileNumber() {
            console.log('clearSelectedFileNumber called');
            selectedFileNumber = "";
            
            // Clear hidden input
            const hiddenInput = document.getElementById('file_number');
            if (hiddenInput) {
                hiddenInput.value = '';
            }

            // Reset trigger button text
            const triggerValue = document.getElementById('file-number-value');
            if (triggerValue) {
                triggerValue.textContent = 'Search and select file number...';
                triggerValue.classList.add('text-gray-500');
                triggerValue.classList.remove('text-gray-900');
            }

            // Hide selected file info
            const selectedInfo = document.getElementById('selected-file-info');
            if (selectedInfo) {
                selectedInfo.classList.add('hidden');
            }

            // Clear search input
            const searchInput = document.getElementById('file-number-search');
            if (searchInput) {
                searchInput.value = '';
            }
        }

        function loadTopFileNumbers() {
            console.log('loadTopFileNumbers called');
            
            const loadingDiv = document.getElementById('file-number-loading');
            const resultsDiv = document.getElementById('file-number-results');
            const noResultsDiv = document.getElementById('file-number-no-results');

            console.log('Loading elements found:', {
                loadingDiv: !!loadingDiv,
                resultsDiv: !!resultsDiv,
                noResultsDiv: !!noResultsDiv
            });

            if (!loadingDiv || !resultsDiv || !noResultsDiv) {
                console.error('Required elements not found for loadTopFileNumbers');
                return;
            }

            // Show loading state
            loadingDiv.classList.remove('hidden');
            resultsDiv.classList.add('hidden');
            noResultsDiv.classList.add('hidden');

            console.log('Making API call to /file-numbers/api/top');

            // Make API call to get top 10 file numbers
            fetch('/file-numbers/api/top')
                .then(response => {
                    console.log('API response status:', response.status);
                    return response.json();
                })
                .then(data => {
                    console.log('API response data:', data);
                    loadingDiv.classList.add('hidden');

                    if (data.success && data.data && data.data.length > 0) {
                        console.log('Rendering', data.data.length, 'results');
                        renderFileNumberResults(data.data);
                        resultsDiv.classList.remove('hidden');
                    } else {
                        console.log('No results or API call failed');
                        noResultsDiv.classList.remove('hidden');
                    }
                })
                .catch(error => {
                    console.error('Top file numbers load error:', error);
                    loadingDiv.classList.add('hidden');
                    
                    // Show error message in no results div
                    noResultsDiv.innerHTML = `
                        <div class="p-3 text-center text-red-600">
                            <i class="fas fa-exclamation-triangle mb-2"></i>
                            <div>Failed to load file numbers. Please try again.</div>
                            <div class="text-xs mt-1">Error: ${error.message}</div>
                        </div>
                    `;
                    noResultsDiv.classList.remove('hidden');
                });
        }

        function searchFileNumbers(query) {
            console.log('searchFileNumbers called with query:', query);
            
            const loadingDiv = document.getElementById('file-number-loading');
            const resultsDiv = document.getElementById('file-number-results');
            const noResultsDiv = document.getElementById('file-number-no-results');

            if (!loadingDiv || !resultsDiv || !noResultsDiv) {
                console.error('Required elements not found for searchFileNumbers');
                return;
            }

            // Show loading state
            loadingDiv.classList.remove('hidden');
            resultsDiv.classList.add('hidden');
            noResultsDiv.classList.add('hidden');

            const apiUrl = `/file-numbers/api/search?query=${encodeURIComponent(query)}&limit=10`;
            console.log('Making API call to:', apiUrl);

            // Make API call to search file numbers
            fetch(apiUrl)
                .then(response => {
                    console.log('Search API response status:', response.status);
                    return response.json();
                })
                .then(data => {
                    console.log('Search API response data:', data);
                    loadingDiv.classList.add('hidden');

                    if (data.success && data.data && data.data.length > 0) {
                        console.log('Rendering', data.data.length, 'search results');
                        renderFileNumberResults(data.data);
                        resultsDiv.classList.remove('hidden');
                    } else {
                        console.log('No search results found');
                        noResultsDiv.classList.remove('hidden');
                    }
                })
                .catch(error => {
                    console.error('File number search error:', error);
                    loadingDiv.classList.add('hidden');
                    
                    // Show error message in no results div
                    noResultsDiv.innerHTML = `
                        <div class="p-3 text-center text-red-600">
                            <i class="fas fa-exclamation-triangle mb-2"></i>
                            <div>Search failed. Please try again.</div>
                            <div class="text-xs mt-1">Error: ${error.message}</div>
                        </div>
                    `;
                    noResultsDiv.classList.remove('hidden');
                });
        }

        function renderFileNumberResults(results) {
            console.log('renderFileNumberResults called with', results.length, 'results');
            
            const resultsDiv = document.getElementById('file-number-results');
            if (!resultsDiv) {
                console.error('Results div not found');
                return;
            }

            resultsDiv.innerHTML = results.map(result => `
                <div class="file-number-option p-3 hover:bg-gray-50 cursor-pointer border-b border-gray-100" 
                     onclick="selectFileNumber('${result.kangis_file_no || result.mlsf_no || result.new_kangis_file_no || 'N/A'}', '${result.file_name || 'Property File'}', '${result.status}', ${result.id})">
                    <div class="flex justify-between items-start">
                        <div class="flex-1">
                            <div class="font-semibold text-gray-900">${result.kangis_file_no || result.mlsf_no || result.new_kangis_file_no || 'N/A'}</div>
                            <div class="text-sm text-gray-600 mt-1">${result.file_name || 'Property File'}</div>
                            ${result.mlsf_no && result.kangis_file_no !== result.mlsf_no ? `<div class="text-xs text-gray-500 mt-1">MLSF: ${result.mlsf_no}</div>` : ''}
                        </div>
                        <div class="ml-3">
                            <span class="px-2 py-1 text-xs rounded-full ${result.status === 'Active' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'}">
                                ${result.status}
                            </span>
                        </div>
                    </div>
                </div>
            `).join('');
            
            console.log('Results rendered to DOM');
        }

        function selectFileNumber(fileNumber, property, status, id = null) {
            console.log('selectFileNumber called:', { fileNumber, property, status, id });
            
            selectedFileNumber = fileNumber;
            
            // Update hidden input
            const hiddenInput = document.getElementById('file_number');
            if (hiddenInput) {
                hiddenInput.value = fileNumber;
                console.log('Updated hidden input value to:', fileNumber);
            }

            // Update trigger button text
            const triggerValue = document.getElementById('file-number-value');
            if (triggerValue) {
                triggerValue.textContent = fileNumber;
                triggerValue.classList.remove('text-gray-500');
                triggerValue.classList.add('text-gray-900');
                console.log('Updated trigger button text');
            }

            // Show selected file info
            const selectedInfo = document.getElementById('selected-file-info');
            const selectedFileNumber = document.getElementById('selected-file-number');
            if (selectedInfo && selectedFileNumber) {
                selectedFileNumber.textContent = fileNumber;
                selectedInfo.classList.remove('hidden');
                console.log('Showed selected file info');
            }

            // Close dropdown
            const popover = document.getElementById('file-number-popover');
            if (popover) {
                popover.classList.add('hidden');
                console.log('Closed dropdown');
            }

            // Clear search input
            const searchInput = document.getElementById('file-number-search');
            if (searchInput) {
                searchInput.value = '';
                console.log('Cleared search input');
            }
        }

        function clearSelectedFileNumber() {
            console.log('clearSelectedFileNumber called');
            
            selectedFileNumber = "";
            
            // Clear hidden input
            const hiddenInput = document.getElementById('file_number');
            if (hiddenInput) {
                hiddenInput.value = '';
            }

            // Reset trigger button text
            const triggerValue = document.getElementById('file-number-value');
            if (triggerValue) {
                triggerValue.textContent = 'Search and select file number...';
                triggerValue.classList.add('text-gray-500');
                triggerValue.classList.remove('text-gray-900');
            }

            // Hide selected file info
            const selectedInfo = document.getElementById('selected-file-info');
            if (selectedInfo) {
                selectedInfo.classList.add('hidden');
            }

            // Clear search input
            const searchInput = document.getElementById('file-number-search');
            if (searchInput) {
                searchInput.value = '';
            }
            
            console.log('File number selection cleared');
        }

        // Render file number options (legacy function - keeping for backward compatibility)
        function renderFileNumberOptions() {
            // This function is kept for backward compatibility
            // New functionality is handled by the enhanced file number selector above
            console.log('Legacy renderFileNumberOptions called - using new enhanced selector instead');
        }

        // Initialize event listeners
        function initializeEventListeners() {
            // Encumbrance info icon click handler
            document.getElementById('encumbrance-info-icon').addEventListener('click', function() {
                const descriptionDiv = document.getElementById('encumbrance-description');
                descriptionDiv.classList.toggle('hidden');
            });

            // Search input
            document.getElementById('search-input').addEventListener('input', function(e) {
                searchTerm = e.target.value.toLowerCase();
                renderCaveatsList();
            });
            
            document.getElementById('lift-search-input').addEventListener('input', function(e) {
                searchTerm = e.target.value.toLowerCase();
                renderActiveCaveatsList();
            });
            
            document.getElementById('log-search-input').addEventListener('input', function(e) {
                searchTerm = e.target.value.toLowerCase();
                renderCaveatsTable();
            });
            
            // Status filter
            document.getElementById('status-filter').addEventListener('change', function(e) {
                statusFilter = e.target.value;
                renderCaveatsList();
            });
            
            document.getElementById('log-status-filter').addEventListener('change', function(e) {
                statusFilter = e.target.value;
                renderCaveatsTable();
            });
            
            // Form inputs
            document.getElementById('encumbrance-type').addEventListener('change', function(e) {
                formData.encumbranceType = e.target.value;
                updateEncumbranceDescription(e.target.value);
            });
            
            document.getElementById('instrument-type').addEventListener('change', function(e) {
                formData.typeOfDeed = e.target.value;
            });
            
            document.getElementById('location').addEventListener('input', function(e) {
                formData.location = e.target.value;
            });
            
            document.getElementById('petitioner').addEventListener('input', function(e) {
                formData.petitioner = e.target.value;
            });
            
            document.getElementById('grantee').addEventListener('input', function(e) {
                formData.grantee = e.target.value;
            });

            // Registration fields
            document.getElementById('serial-no').addEventListener('input', function(e) {
                formData.serialNo = e.target.value;
                updatePageNo();
                updateRegistrationNumber();
            });

            document.getElementById('volume-no').addEventListener('input', function(e) {
                formData.volumeNo = e.target.value;
                updateRegistrationNumber();
            });
            
            document.getElementById('start-date').addEventListener('change', function(e) {
                formData.startDate = e.target.value;
            });
            
            document.getElementById('release-date').addEventListener('change', function(e) {
                formData.releaseDate = e.target.value;
            });
            
            document.getElementById('instructions').addEventListener('input', function(e) {
                formData.instructions = e.target.value;
            });
            
            document.getElementById('remarks').addEventListener('input', function(e) {
                formData.remarks = e.target.value;
            });
            
            // Lift form inputs
            document.getElementById('lift-release-date').addEventListener('change', function(e) {
                formData.releaseDate = e.target.value;
            });
            
            document.getElementById('lift-instructions').addEventListener('input', function(e) {
                formData.instructions = e.target.value;
            });
            
            document.getElementById('lift-remarks').addEventListener('input', function(e) {
                formData.remarks = e.target.value;
            });
            
            // Buttons
            document.getElementById('save-draft').addEventListener('click', function() {
                handleFormSubmit('draft');
            });
            
            document.getElementById('place-caveat').addEventListener('click', function() {
                handleFormSubmit('submit');
            });
            
            document.getElementById('generate-acknowledgement').addEventListener('click', function() {
                handleGenerateAcknowledgement();
            });
            
            document.getElementById('reset-form').addEventListener('click', function() {
                handleFormReset();
            });
            
            document.getElementById('save-lift-draft').addEventListener('click', function() {
                handleFormSubmit('draft');
            });
            
            document.getElementById('lift-caveat').addEventListener('click', function() {
                handleFormSubmit('submit');
            });
            
            document.getElementById('generate-lift-acknowledgement').addEventListener('click', function() {
                handleGenerateAcknowledgement();
            });
            
            document.getElementById('reset-lift-form').addEventListener('click', function() {
                handleFormReset();
            });
            
            // Modal
            document.getElementById('close-modal').addEventListener('click', function() {
                document.getElementById('view-caveat-modal').classList.add('hidden');
            });
        }

        // Update stats
        function updateStats() {
            const total = caveats.length;
            const active = caveats.filter(c => c.status === 'active').length;
            const released = caveats.filter(c => c.status === 'released').length;
            const draft = caveats.filter(c => c.status === 'draft').length;
            
            document.getElementById('total-caveats').textContent = total;
            document.getElementById('active-caveats').textContent = active;
            document.getElementById('released-caveats').textContent = released;
            document.getElementById('draft-caveats').textContent = draft;
        }

        // Render caveats list for search
        function renderCaveatsList() {
            const container = document.getElementById('search-results');
            const countElement = document.getElementById('search-results-count');
            
            const filteredCaveats = filterCaveats();
            
            countElement.textContent = filteredCaveats.length;
            container.innerHTML = '';
            
            filteredCaveats.forEach(caveat => {
                const card = document.createElement('div');
                card.className = 'bg-white border rounded-md p-3 cursor-pointer hover:bg-gray-50 transition-colors';
                card.innerHTML = `
                    <div class="space-y-1">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium">${caveat.caveatNumber}</span>
                            ${getStatusBadge(caveat.status)}
                        </div>
                        <p class="text-xs text-gray-600">${caveat.fileNumber}</p>
                        <p class="text-xs text-gray-600">${caveat.petitioner}</p>
                    </div>
                `;
                
                card.addEventListener('click', function() {
                    populateFormFromCaveat(caveat);
                });
                
                container.appendChild(card);
            });
        }

        // Render active caveats list for lifting
        function renderActiveCaveatsList() {
            const container = document.getElementById('active-caveats-list');
            const countElement = document.getElementById('active-caveats-count');
            
            const activeCaveats = caveats.filter(c => c.status === 'active');
            const filteredCaveats = activeCaveats.filter(caveat => {
                return caveat.caveatNumber.toLowerCase().includes(searchTerm) ||
                       caveat.fileNumber.toLowerCase().includes(searchTerm) ||
                       caveat.petitioner.toLowerCase().includes(searchTerm) ||
                       caveat.grantee.toLowerCase().includes(searchTerm) ||
                       caveat.location.toLowerCase().includes(searchTerm) ||
                       caveat.encumbranceType.toLowerCase().includes(searchTerm) ||
                       caveat.typeOfDeed.toLowerCase().includes(searchTerm);
            });
            
            countElement.textContent = filteredCaveats.length;
            container.innerHTML = '';
            
            filteredCaveats.forEach(caveat => {
                const isSelected = selectedCaveat && selectedCaveat.id === caveat.id;
                
                const card = document.createElement('div');
                card.className = `bg-white border rounded-md p-3 cursor-pointer hover:bg-gray-50 transition-colors ${isSelected ? 'ring-2 ring-blue-500 bg-blue-50' : ''}`;
                card.innerHTML = `
                    <div class="space-y-1">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium">${caveat.caveatNumber}</span>
                            ${getStatusBadge(caveat.status)}
                        </div>
                        <p class="text-xs text-gray-600">${caveat.fileNumber}</p>
                        <p class="text-xs text-gray-600">${caveat.petitioner}</p>
                    </div>
                `;
                
                card.addEventListener('click', function() {
                    selectedCaveat = caveat;
                    renderActiveCaveatsList();
                    showSelectedCaveatInfo(caveat);
                });
                
                container.appendChild(card);
            });
        }

        // Render caveats table
        function renderCaveatsTable() {
            const container = document.getElementById('caveats-table-body');
            const countElement = document.getElementById('log-results-count');
            const totalElement = document.getElementById('total-caveats-count');
            const noResultsElement = document.getElementById('no-caveats-found');
            
            const filteredCaveats = filterCaveats();
            
            countElement.textContent = filteredCaveats.length;
            totalElement.textContent = caveats.length;
            container.innerHTML = '';
            
            if (filteredCaveats.length === 0) {
                noResultsElement.classList.remove('hidden');
                return;
            }
            
            noResultsElement.classList.add('hidden');
            
            filteredCaveats.forEach(caveat => {
                const row = document.createElement('tr');
                row.className = 'hover:bg-gray-50 border-b';
                row.innerHTML = `
                    <td class="p-3 font-medium">${caveat.caveatNumber}</td>
                    <td class="p-3">${caveat.fileNumber}</td>
                    <td class="p-3">
                        <div class="flex items-center gap-2">
                            <i class="fa-regular fa-user text-gray-400"></i>
                            <span class="truncate max-w-32" title="${caveat.petitioner}">${caveat.petitioner}</span>
                        </div>
                    </td>
                    <td class="p-3">
                        <div class="flex items-center gap-2">
                            <i class="fa-regular fa-building text-gray-400"></i>
                            <span class="truncate max-w-32" title="${caveat.grantee}">${caveat.grantee}</span>
                        </div>
                    </td>
                    <td class="p-3">${caveat.encumbranceType}</td>
                    <td class="p-3">${getStatusBadge(caveat.status)}</td>
                    <td class="p-3">
                        <div class="flex items-center gap-2">
                            <i class="fa-regular fa-calendar text-gray-400"></i>
                            ${caveat.startDate}
                        </div>
                    </td>
                    <td class="p-3">
                        ${caveat.releaseDate ? `
                            <div class="flex items-center gap-2">
                                <i class="fa-regular fa-calendar text-gray-400"></i>
                                ${caveat.releaseDate}
                            </div>
                        ` : '<span class="text-gray-400">-</span>'}
                    </td>
                    <td class="p-3">
                        <div class="flex items-center gap-2">
                            <button class="view-caveat p-1 text-gray-600 hover:text-blue-600" data-id="${caveat.id}">
                                <i class="fa-regular fa-eye"></i>
                            </button>
                            <button class="p-1 text-gray-600 hover:text-blue-600">
                                <i class="fa-regular fa-pen-to-square"></i>
                            </button>
                        </div>
                    </td>
                `;
                
                container.appendChild(row);
            });
            
            // Add event listeners to view buttons
            document.querySelectorAll('.view-caveat').forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    const caveat = caveats.find(c => c.id === id);
                    if (caveat) {
                        showCaveatDetails(caveat);
                    }
                });
            });
        }

        // Filter caveats based on search term and status
        function filterCaveats() {
            return caveats.filter(caveat => {
                const matchesSearch = 
                    caveat.caveatNumber.toLowerCase().includes(searchTerm) ||
                    caveat.fileNumber.toLowerCase().includes(searchTerm) ||
                    caveat.petitioner.toLowerCase().includes(searchTerm) ||
                    caveat.grantee.toLowerCase().includes(searchTerm) ||
                    caveat.location.toLowerCase().includes(searchTerm) ||
                    caveat.encumbranceType.toLowerCase().includes(searchTerm) ||
                    caveat.typeOfDeed.toLowerCase().includes(searchTerm);
                
                const matchesStatus = statusFilter === "all" || caveat.status === statusFilter;
                
                return matchesSearch && matchesStatus;
            });
        }

        // Get status badge HTML
        function getStatusBadge(status) {
            switch (status) {
                case "active":
                    return `<span class="status-badge badge-active"><i class="fa-regular fa-circle-check"></i>Active</span>`;
                case "released":
                    return `<span class="status-badge badge-released"><i class="fa-regular fa-file-check"></i>Released</span>`;
                case "lifted":
                    return `<span class="status-badge badge-lifted"><i class="fa-solid fa-rotate-left"></i>Lifted</span>`;
                case "expired":
                    return `<span class="status-badge badge-expired"><i class="fa-regular fa-circle-xmark"></i>Expired</span>`;
                case "draft":
                    return `<span class="status-badge badge-draft"><i class="fa-regular fa-file-lines"></i>Draft</span>`;
                default:
                    return `<span class="status-badge badge-draft">${status}</span>`;
            }
        }

        // Populate form from caveat data
        function populateFormFromCaveat(caveat) {
            const encumbranceType = caveat.encumbranceType.toLowerCase().replace(' ', '-');
            document.getElementById('encumbrance-type').value = encumbranceType;
            updateEncumbranceDescription(encumbranceType);
            
            document.getElementById('instrument-type').value = caveat.typeOfDeed.toLowerCase().replace(' ', '-');
            document.getElementById('file-number-value').textContent = caveat.fileNumber;
            document.getElementById('location').value = caveat.location;
            document.getElementById('petitioner').value = caveat.petitioner;
            document.getElementById('grantee').value = caveat.grantee;
            
            // Registration fields
            if (caveat.regParticulars) {
                document.getElementById('serial-no').value = caveat.regParticulars.serialNo || '';
                document.getElementById('page-no').value = caveat.regParticulars.pageNo || caveat.regParticulars.serialNo || '';
                document.getElementById('volume-no').value = caveat.regParticulars.volumeNo || '';
                updateRegistrationNumber();
            }
            
            document.getElementById('start-date').value = caveat.startDate;
            document.getElementById('release-date').value = caveat.releaseDate || '';
            document.getElementById('instructions').value = caveat.instructions;
            document.getElementById('remarks').value = caveat.remarks;
            
            // Update form data object
            formData.encumbranceType = caveat.encumbranceType;
            formData.typeOfDeed = caveat.typeOfDeed;
            formData.fileNumber = caveat.fileNumber;
            formData.location = caveat.location;
            formData.petitioner = caveat.petitioner;
            formData.grantee = caveat.grantee;
            formData.startDate = caveat.startDate;
            formData.releaseDate = caveat.releaseDate || '';
            formData.instructions = caveat.instructions;
            formData.remarks = caveat.remarks;
        }

        // Show selected caveat info for lifting
        function showSelectedCaveatInfo(caveat) {
            const container = document.getElementById('selected-caveat-info');
            const noCaveatElement = document.getElementById('no-caveat-selected');
            const liftingDetails = document.getElementById('lifting-details');
            
            noCaveatElement.classList.add('hidden');
            container.classList.remove('hidden');
            liftingDetails.classList.remove('hidden');
            
            container.innerHTML = `
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <h4 class="font-semibold text-blue-800 mb-2">Selected Caveat for Lifting</h4>
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div><span class="font-medium">Caveat Number:</span> ${caveat.caveatNumber}</div>
                        <div><span class="font-medium">File Number:</span> ${caveat.fileNumber}</div>
                        <div><span class="font-medium">Petitioner:</span> ${caveat.petitioner}</div>
                        <div><span class="font-medium">Grantee:</span> ${caveat.grantee}</div>
                    </div>
                </div>
            `;
            
            // Set today's date as release date
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('lift-release-date').value = today;
            formData.releaseDate = today;
        }

        // Show caveat details in modal
        function showCaveatDetails(caveat) {
            const container = document.getElementById('caveat-details');
            const modal = document.getElementById('view-caveat-modal');
            
            container.innerHTML = `
                <!-- Section A: Caveat Information -->
                <div class="space-y-4">
                    <h3 class="text-lg font-semibold border-b pb-2">Section A: Caveat Information</h3>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="text-sm font-medium text-gray-600">Caveat Number</label>
                            <p class="text-sm font-semibold">${caveat.caveatNumber}</p>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-gray-600">Status</label>
                            <div class="mt-1">${getStatusBadge(caveat.status)}</div>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-gray-600">Encumbrance Type</label>
                            <p class="text-sm">${caveat.encumbranceType}</p>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-gray-600">Type of Deed</label>
                            <p class="text-sm">${caveat.typeOfDeed}</p>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-gray-600">File Number</label>
                            <p class="text-sm">${caveat.fileNumber}</p>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-gray-600">Location</label>
                            <p class="text-sm">${caveat.location}</p>
                        </div>
                    </div>
                </div>

                <!-- Section B: Parties Involved -->
                <div class="space-y-4">
                    <h3 class="text-lg font-semibold border-b pb-2">Section B: Parties Involved</h3>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="text-sm font-medium text-gray-600">Petitioner</label>
                            <p class="text-sm">${caveat.petitioner}</p>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-gray-600">Grantee</label>
                            <p class="text-sm">${caveat.grantee}</p>
                        </div>
                    </div>
                </div>

                <!-- Section C: Registration & Tracking -->
                <div class="space-y-4">
                    <h3 class="text-lg font-semibold border-b pb-2">Section C: Registration & Tracking</h3>
                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label class="text-sm font-medium text-gray-600">Registration Particulars</label>
                            <div class="text-sm space-y-1">
                                <p class="font-mono">${caveat.regParticulars.serialNo}/${caveat.regParticulars.pageNo}/${caveat.regParticulars.volumeNo}</p>
                                <div class="text-xs text-gray-500">
                                    <p>Serial No: ${caveat.regParticulars.serialNo}</p>
                                    <p>Page No: ${caveat.regParticulars.pageNo}</p>
                                    <p>Volume No: ${caveat.regParticulars.volumeNo}</p>
                                </div>
                            </div>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-gray-600">Start Date</label>
                            <p class="text-sm">${caveat.startDate}</p>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-gray-600">Release Date</label>
                            <p class="text-sm">${caveat.releaseDate || "Not released"}</p>
                        </div>
                    </div>
                </div>

                <!-- Section D: Administrative Details -->
                <div class="space-y-4">
                    <h3 class="text-lg font-semibold border-b pb-2">Section D: Administrative Details</h3>
                    <div class="space-y-4">
                        <div>
                            <label class="text-sm font-medium text-gray-600">Instructions</label>
                            <p class="text-sm">${caveat.instructions}</p>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-gray-600">Remarks</label>
                            <p class="text-sm">${caveat.remarks}</p>
                        </div>
                    </div>
                </div>

                <!-- System Metadata -->
                <div class="space-y-4">
                    <h3 class="text-lg font-semibold border-b pb-2">System Metadata</h3>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="text-sm font-medium text-gray-600">Created By</label>
                            <p class="text-sm">${caveat.createdBy}</p>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-gray-600">Date Created</label>
                            <p class="text-sm">${new Date(caveat.dateCreated).toLocaleDateString()}</p>
                        </div>
                    </div>
                </div>
            `;
            
            modal.classList.remove('hidden');
        }

        // Handle form submission
        function handleFormSubmit(action) {
            setIsLoading(true);

            try {
                if (activeTab === "place") {
                    // Place new caveat
                    if (action === "submit") {
                        const requiredFields = [
                            "encumbranceType",
                            "typeOfDeed",
                            "fileNumber",
                            "location",
                            "petitioner",
                            "grantee",
                            "startDate",
                        ];
                        const missingFields = requiredFields.filter(field => !formData[field]);

                        if (missingFields.length > 0) {
                            showToast("Validation Error", `Please fill in all required fields: ${missingFields.join(", ")}`, "error");
                            setIsLoading(false);
                            return;
                        }
                    }

                    // Get reg particulars based on selected file number
                    const selectedFile = mockFileNumbers.find(file => file.value === formData.fileNumber);
                    const regParticulars = selectedFile?.regParticulars || generateRegParticulars();

                    // Create new caveat
                    const newCaveat = {
                        id: (caveats.length + 1).toString(),
                        caveatNumber: generateNextCaveatNumber(),
                        encumbranceType: formData.encumbranceType,
                        typeOfDeed: formData.typeOfDeed,
                        fileNumber: formData.fileNumber,
                        location: formData.location,
                        petitioner: formData.petitioner,
                        grantee: formData.grantee,
                        regParticulars,
                        startDate: formData.startDate,
                        instructions: formData.instructions,
                        remarks: formData.remarks,
                        status: action === "draft" ? "draft" : "active",
                        createdBy: "Current User",
                        dateCreated: new Date().toISOString(),
                    };

                    // Simulate API call
                    setTimeout(() => {
                        // Update caveats list
                        caveats.push(newCaveat);

                        showToast(
                            action === "draft" ? "Draft Saved" : "Caveat Placed",
                            `Caveat ${newCaveat.caveatNumber} has been ${action === "draft" ? "saved as draft" : "successfully placed"}.`,
                            "success"
                        );

                        // Reset form if submitted
                        if (action === "submit") {
                            handleFormReset();
                        }

                        // Update UI
                        updateStats();
                        renderCaveatsList();
                        renderActiveCaveatsList();
                        renderCaveatsTable();
                        updateCaveatNumber();
                        
                        setIsLoading(false);
                    }, 2000);
                } else {
                    // Lift existing caveat
                    if (!selectedCaveat) {
                        showToast("No Caveat Selected", "Please select an active caveat from the left panel to lift.", "error");
                        setIsLoading(false);
                        return;
                    }

                    // Update the selected caveat status to lifted
                    const updatedCaveats = caveats.map(caveat => 
                        caveat.id === selectedCaveat.id
                            ? {
                                ...caveat,
                                status: "lifted",
                                releaseDate: new Date().toISOString().split("T")[0],
                                remarks: formData.remarks || caveat.remarks,
                                instructions: formData.instructions || caveat.instructions,
                            }
                            : caveat
                    );

                    // Simulate API call
                    setTimeout(() => {
                        caveats = updatedCaveats;

                        showToast(
                            "Caveat Lifted",
                            `Caveat ${selectedCaveat.caveatNumber} has been successfully lifted.`,
                            "success"
                        );

                        handleFormReset();

                        // Update UI
                        updateStats();
                        renderCaveatsList();
                        renderActiveCaveatsList();
                        renderCaveatsTable();
                        
                        setIsLoading(false);
                    }, 2000);
                }
            } catch (error) {
                showToast("Error", "An error occurred while processing the caveat. Please try again.", "error");
                setIsLoading(false);
            }
        }

        // Handle generate acknowledgement
        function handleGenerateAcknowledgement() {
            setIsLoading(true);

            try {
                // Validate that we have enough data to generate acknowledgement
                if (activeTab === "place") {
                    if (!formData.fileNumber || !formData.petitioner || !formData.grantee) {
                        showToast("Missing Information", "Please fill in File Number, Petitioner, and Grantee to generate acknowledgement sheet.", "error");
                        setIsLoading(false);
                        return;
                    }
                } else {
                    if (!selectedCaveat) {
                        showToast("No Caveat Selected", "Please select a caveat to generate acknowledgement sheet.", "error");
                        setIsLoading(false);
                        return;
                    }
                }

                // Simulate acknowledgement generation
                setTimeout(() => {
                    // Create acknowledgement data
                    const acknowledgementData =
                        activeTab === "place"
                            ? {
                                caveatNumber: generateNextCaveatNumber(),
                                fileNumber: formData.fileNumber,
                                petitioner: formData.petitioner,
                                grantee: formData.grantee,
                                encumbranceType: formData.encumbranceType,
                                typeOfDeed: formData.typeOfDeed,
                                location: formData.location,
                                regParticulars:
                                    mockFileNumbers.find(file => file.value === formData.fileNumber)?.regParticulars ||
                                    generateRegParticulars(),
                                dateGenerated: new Date().toLocaleDateString(),
                                generatedBy: "Current User",
                                action: "Placement",
                            }
                            : {
                                caveatNumber: selectedCaveat.caveatNumber,
                                fileNumber: selectedCaveat.fileNumber,
                                petitioner: selectedCaveat.petitioner,
                                grantee: selectedCaveat.grantee,
                                encumbranceType: selectedCaveat.encumbranceType,
                                typeOfDeed: selectedCaveat.typeOfDeed,
                                location: selectedCaveat.location,
                                regParticulars: selectedCaveat.regParticulars,
                                dateGenerated: new Date().toLocaleDateString(),
                                generatedBy: "Current User",
                                action: "Lifting",
                            };

                    // Generate PDF
                    const doc = new jspdf.jsPDF();

                    // Header
                    doc.setFontSize(20);
                    doc.setFont("helvetica", "bold");
                    doc.text(`CAVEAT ${acknowledgementData.action.toUpperCase()} ACKNOWLEDGEMENT SHEET`, 105, 30, { align: "center" });

                    // Subheader
                    doc.setFontSize(14);
                    doc.setFont("helvetica", "normal");
                    doc.text("Kano Land Administration and Enterprise System (KLAES)", 105, 45, { align: "center" });

                    // Line separator
                    doc.line(20, 55, 190, 55);

                    // Content
                    doc.setFontSize(12);
                    doc.setFont("helvetica", "bold");

                    let yPosition = 75;
                    const lineHeight = 8;

                    // Caveat Information
                    doc.text("CAVEAT INFORMATION", 20, yPosition);
                    yPosition += lineHeight + 5;

                    doc.setFont("helvetica", "normal");
                    doc.text(`Action: ${acknowledgementData.action}`, 25, yPosition);
                    yPosition += lineHeight;
                    doc.text(`Caveat Number: ${acknowledgementData.caveatNumber}`, 25, yPosition);
                    yPosition += lineHeight;
                    doc.text(`File Number: ${acknowledgementData.fileNumber}`, 25, yPosition);
                    yPosition += lineHeight;
                    doc.text(`Encumbrance Type: ${acknowledgementData.encumbranceType}`, 25, yPosition);
                    yPosition += lineHeight;
                    doc.text(`Type of Deed: ${acknowledgementData.typeOfDeed}`, 25, yPosition);
                    yPosition += lineHeight;
                    doc.text(`Location: ${acknowledgementData.location}`, 25, yPosition);
                    yPosition += lineHeight + 10;

                    // Parties Information
                    doc.setFont("helvetica", "bold");
                    doc.text("PARTIES INVOLVED", 20, yPosition);
                    yPosition += lineHeight + 5;

                    doc.setFont("helvetica", "normal");
                    doc.text(`Petitioner: ${acknowledgementData.petitioner}`, 25, yPosition);
                    yPosition += lineHeight;
                    doc.text(`Grantee: ${acknowledgementData.grantee}`, 25, yPosition);
                    yPosition += lineHeight + 10;

                    // Registration Particulars
                    doc.setFont("helvetica", "bold");
                    doc.text("REGISTRATION PARTICULARS", 20, yPosition);
                    yPosition += lineHeight + 5;

                    doc.setFont("helvetica", "normal");
                    doc.text(`Serial No: ${acknowledgementData.regParticulars.serialNo}`, 25, yPosition);
                    yPosition += lineHeight;
                    doc.text(`Page No: ${acknowledgementData.regParticulars.pageNo}`, 25, yPosition);
                    yPosition += lineHeight;
                    doc.text(`Volume No: ${acknowledgementData.regParticulars.volumeNo}`, 25, yPosition);
                    yPosition += lineHeight;
                    doc.text(
                        `Format: ${acknowledgementData.regParticulars.serialNo}/${acknowledgementData.regParticulars.pageNo}/${acknowledgementData.regParticulars.volumeNo}`,
                        25,
                        yPosition
                    );
                    yPosition += lineHeight + 15;

                    // System Information
                    doc.setFont("helvetica", "bold");
                    doc.text("SYSTEM INFORMATION", 20, yPosition);
                    yPosition += lineHeight + 5;

                    doc.setFont("helvetica", "normal");
                    doc.text(`Date Generated: ${acknowledgementData.dateGenerated}`, 25, yPosition);
                    yPosition += lineHeight;
                    doc.text(`Generated By: ${acknowledgementData.generatedBy}`, 25, yPosition);
                    yPosition += lineHeight + 20;

                    // Footer
                    doc.line(20, yPosition, 190, yPosition);
                    yPosition += 10;
                    doc.setFontSize(10);
                    doc.text(`This is a caveat ${acknowledgementData.action.toLowerCase()} acknowledgement sheet.`, 105, yPosition, {
                        align: "center",
                    });
                    yPosition += 5;
                    doc.text("No signature is required.", 105, yPosition, { align: "center" });

                    // Save the PDF
                    doc.save(
                        `caveat-${acknowledgementData.action.toLowerCase()}-acknowledgement-${acknowledgementData.caveatNumber}.pdf`
                    );

                    showToast(
                        "Acknowledgement Generated",
                        `Acknowledgement sheet for caveat ${acknowledgementData.action.toLowerCase()} ${acknowledgementData.caveatNumber} has been generated and downloaded as PDF.`,
                        "success"
                    );
                    
                    setIsLoading(false);
                }, 1500);
            } catch (error) {
                showToast("Error", "An error occurred while generating the acknowledgement sheet. Please try again.", "error");
                setIsLoading(false);
            }
        }

        // Handle form reset
        function handleFormReset() {
            // Reset form data
            formData.encumbranceType = "";
            formData.typeOfDeed = "";
            formData.fileNumber = "";
            formData.location = "";
            formData.petitioner = "";
            formData.grantee = "";
            formData.serialNo = "";
            formData.pageNo = "";
            formData.volumeNo = "";
            formData.registrationNumber = "";
            formData.startDate = "";
            formData.releaseDate = "";
            formData.instructions = "";
            formData.remarks = "";
            
            // Reset form fields
            document.getElementById('encumbrance-type').value = "";
            document.getElementById('instrument-type').value = "";
            document.getElementById('file-number-value').textContent = "Search and select file number...";
            document.getElementById('location').value = "";
            document.getElementById('petitioner').value = "";
            document.getElementById('grantee').value = "";
            document.getElementById('serial-no').value = "";
            document.getElementById('page-no').value = "";
            document.getElementById('volume-no').value = "";
            document.getElementById('registration-number').textContent = "Enter Serial No. and Volume No. to generate";
            document.getElementById('release-date').value = "";
            document.getElementById('instructions').value = "";
            document.getElementById('remarks').value = "";
            
            // Hide encumbrance description
            document.getElementById('encumbrance-description').classList.add('hidden');
            document.getElementById('encumbrance-info-icon').classList.add('hidden');
            
            // Set default start date
            setDefaultStartDate();
            
            // Reset lift form
            document.getElementById('lift-release-date').value = "";
            document.getElementById('lift-instructions').value = "";
            document.getElementById('lift-remarks').value = "";
            
            // Reset selected caveat
            selectedCaveat = null;
            
            // Update UI for lift tab
            document.getElementById('no-caveat-selected').classList.remove('hidden');
            document.getElementById('selected-caveat-info').classList.add('hidden');
            document.getElementById('lifting-details').classList.add('hidden');
            
            showToast("Form Reset", "All form fields have been cleared.", "success");
        }

        // Generate next caveat number
        function generateNextCaveatNumber() {
            const year = new Date().getFullYear();
            const nextNumber = (caveats.length + 1).toString().padStart(3, "0");
            return `CAV-${year}-${nextNumber}`;
        }

        // Update caveat number field
        function updateCaveatNumber() {
            document.getElementById('caveat-number').value = generateNextCaveatNumber();
        }

        // Update date created field
        function updateDateCreated() {
            document.getElementById('date-created').value = new Date().toLocaleDateString();
        }

        // Set default start date and time to current date/time
        function setDefaultStartDate() {
            const now = new Date();
            const year = now.getFullYear();
            const month = String(now.getMonth() + 1).padStart(2, '0');
            const day = String(now.getDate()).padStart(2, '0');
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            
            const dateTimeString = `${year}-${month}-${day}T${hours}:${minutes}`;
            document.getElementById('start-date').value = dateTimeString;
        }

        // Update encumbrance description based on selected type
        function updateEncumbranceDescription(encumbranceType) {
            const descriptionDiv = document.getElementById('encumbrance-description');
            const descriptionText = document.getElementById('encumbrance-description-text');
            const infoIcon = document.getElementById('encumbrance-info-icon');
            
            if (encumbranceType && encumbranceDescriptions[encumbranceType]) {
                descriptionText.textContent = encumbranceDescriptions[encumbranceType];
                descriptionDiv.classList.remove('hidden');
                infoIcon.classList.remove('hidden');
            } else {
                descriptionDiv.classList.add('hidden');
                infoIcon.classList.add('hidden');
            }
        }

        // Update page number to match serial number
        function updatePageNo() {
            const serialNo = document.getElementById('serial-no').value;
            document.getElementById('page-no').value = serialNo;
            formData.pageNo = serialNo;
        }

        // Update registration number format: [Serial No]/[Page No]/[Volume No]
        function updateRegistrationNumber() {
            const serialNo = document.getElementById('serial-no').value;
            const pageNo = document.getElementById('page-no').value;
            const volumeNo = document.getElementById('volume-no').value;
            const registrationDiv = document.getElementById('registration-number');
            
            if (serialNo && volumeNo) {
                const registrationNumber = `${serialNo}/${pageNo || serialNo}/${volumeNo}`;
                registrationDiv.textContent = registrationNumber;
                registrationDiv.classList.remove('text-gray-500');
                registrationDiv.classList.add('text-blue-700', 'font-medium');
                formData.registrationNumber = registrationNumber;
            } else {
                registrationDiv.textContent = "Enter Serial No. and Volume No. to generate";
                registrationDiv.classList.add('text-gray-500');
                registrationDiv.classList.remove('text-blue-700', 'font-medium');
                formData.registrationNumber = "";
            }
        }

        // Set loading state
        function setIsLoading(loading) {
            isLoading = loading;
            
            // Update button states
            const buttons = document.querySelectorAll('button');
            buttons.forEach(button => {
                if (button.id !== 'close-modal') {
                    button.disabled = loading;
                }
            });
            
            // Show/hide loading indicators
            const loadingIcons = document.querySelectorAll('.loading-icon');
            loadingIcons.forEach(icon => {
                icon.classList.toggle('hidden', !loading);
            });
            
            const normalIcons = document.querySelectorAll('.normal-icon');
            normalIcons.forEach(icon => {
                icon.classList.toggle('hidden', loading);
            });
        }

        // Show toast notification
        function showToast(title, description, variant = "default") {
            const toast = document.getElementById('toast');
            const toastIcon = document.getElementById('toast-icon');
            const toastTitle = document.getElementById('toast-title');
            const toastDescription = document.getElementById('toast-description');
            
            // Set content
            toastTitle.textContent = title;
            toastDescription.textContent = description;
            
            // Set style based on variant
            if (variant === "error") {
                toast.classList.add('border-red-200', 'bg-red-50');
                toastIcon.className = 'fa-solid fa-circle-exclamation text-red-600';
            } else {
                toast.classList.add('border-green-200', 'bg-green-50');
                toastIcon.className = 'fa-solid fa-circle-check text-green-600';
            }
            
            // Show toast
            toast.classList.remove('hidden');
            
            // Hide after 5 seconds
            setTimeout(() => {
                toast.classList.add('hidden');
                toast.classList.remove('border-red-200', 'bg-red-50', 'border-green-200', 'bg-green-50');
            }, 5000);
        }
</script>