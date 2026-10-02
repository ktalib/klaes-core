<style>
    /* Scanned Files Tab Specific Styles */
    .scanned-files-container {
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        line-height: 1.5;
        color: #333;
    }
    
    /* Utility classes */
    .mt-6 { margin-top: 1.5rem; }
    .flex { display: flex; }
    .flex-col { flex-direction: column; }
    .flex-row { flex-direction: row; }
    .items-center { align-items: center; }
    .justify-between { justify-content: space-between; }
    .gap-2 { gap: 0.5rem; }
    .gap-4 { gap: 1rem; }
    .w-full { width: 100%; }
    .w-auto { width: auto; }
    .relative { position: relative; }
    .absolute { position: absolute; }
    .left-2\.5 { left: 0.625rem; }
    .top-2\.5 { top: 0.625rem; }
    .h-4 { height: 1rem; }
    .w-4 { width: 1rem; }
    .text-muted-foreground { color: #6b7280; }
    .pl-8 { padding-left: 2rem; }
    .bg-transparent { background-color: transparent; }
    .rounded-md { border-radius: 0.375rem; }
    .border { border: 1px solid #e5e7eb; }
    .p-8 { padding: 2rem; }
    .text-center { text-align: center; }
    .mx-auto { margin-left: auto; margin-right: auto; }
    .mb-4 { margin-bottom: 1rem; }
    .h-12 { height: 3rem; }
    .w-12 { width: 3rem; }
    .bg-muted { background-color: #f3f4f6; }
    .rounded-full { border-radius: 9999px; }
    .h-6 { height: 1.5rem; }
    .w-6 { width: 1.5rem; }
    .mb-2 { margin-bottom: 0.5rem; }
    .text-lg { font-size: 1.125rem; line-height: 1.75rem; }
    .font-medium { font-weight: 500; }
    .text-sm { font-size: 0.875rem; line-height: 1.25rem; }
    .overflow-x-auto { overflow-x: auto; }
    .min-w-\[150px\] { min-width: 150px; }
    .min-w-\[200px\] { min-width: 200px; }
    .min-w-\[120px\] { min-width: 120px; }
    .min-w-\[100px\] { min-width: 100px; }
    .cursor-pointer { cursor: pointer; }
    .ml-2 { margin-left: 0.5rem; }
    .inline { display: inline; }
    .text-right { text-align: right; }
    .justify-end { justify-content: flex-end; }
    .border-t { border-top: 1px solid #e5e7eb; }
    .pt-4 { padding-top: 1rem; }
    .mr-2 { margin-right: 0.5rem; }
    .whitespace-nowrap { white-space: nowrap; }
    .md\:flex-row { flex-direction: row; }
    .md\:items-center { align-items: center; }
    .md\:w-auto { width: auto; }
    .md\:w-64 { width: 16rem; }

    /* Card styles */
    .scanned-card {
        border-radius: 0.5rem;
        border: 1px solid #e5e7eb;
        background-color: white;
        box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    }
    
    .scanned-card-header {
        padding: 1.5rem;
        border-bottom: 1px solid #e5e7eb;
    }
    
    .scanned-card-title {
        font-size: 1.25rem;
        font-weight: 600;
        line-height: 1.75rem;
        margin-bottom: 0.25rem;
    }
    
    .scanned-card-description {
        font-size: 0.875rem;
        color: #6b7280;
    }
    
    .scanned-card-content {
        padding: 1.5rem;
    }
    
    .scanned-card-footer {
        padding: 1.5rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-top: 1px solid #e5e7eb;
    }
    
    /* Table styles */
    .scanned-table {
        width: 100%;
        font-size: 0.875rem;
        text-align: left;
        border-collapse: collapse;
    }
    
    .scanned-table-header {
        background-color: #f9fafb;
    }
    
    .scanned-table-row {
        border-bottom: 1px solid #e5e7eb;
    }
    
    .scanned-table-head {
        padding: 0.75rem 1rem;
        font-weight: 500;
        color: #6b7280;
        text-transform: uppercase;
        font-size: 0.75rem;
        letter-spacing: 0.05em;
    }
    
    .scanned-table-cell {
        padding: 1rem;
        vertical-align: middle;
    }
    
    /* Button styles */
    .scanned-button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 0.375rem;
        font-weight: 500;
        font-size: 0.875rem;
        line-height: 1.25rem;
        padding: 0.5rem 1rem;
        transition: all 0.2s;
        cursor: pointer;
        border: none;
    }
    
    .scanned-button:disabled {
        opacity: 0.5;
        pointer-events: none;
    }
    
    .scanned-button-outline {
        background-color: white;
        border: 1px solid #e5e7eb;
        color: #374151;
    }
    
    .scanned-button-outline:hover {
        background-color: #f9fafb;
        border-color: #d1d5db;
    }
    
    .scanned-button-primary {
        background-color: #3b82f6;
        border: 1px solid #3b82f6;
        color: white;
    }
    
    .scanned-button-primary:hover {
        background-color: #2563eb;
        border-color: #2563eb;
    }
    
    .scanned-button-sm {
        padding: 0.25rem 0.5rem;
        font-size: 0.75rem;
        line-height: 1rem;
    }
    
    /* Badge styles */
    .scanned-badge {
        display: inline-flex;
        align-items: center;
        border-radius: 9999px;
        padding: 0.25rem 0.75rem;
        font-size: 0.75rem;
        font-weight: 500;
        line-height: 1rem;
    }
    
    .scanned-badge-primary {
        background-color: #3b82f6;
        color: white;
    }
    
    .scanned-badge-secondary {
        background-color: #e5e7eb;
        color: #374151;
    }
    
    .scanned-badge-destructive {
        background-color: #ef4444;
        color: white;
    }

    /* Input styles */
    .scanned-input {
        border-radius: 0.375rem;
        border: 1px solid #e5e7eb;
        background-color: white;
        padding: 0.5rem 0.75rem;
        font-size: 0.875rem;
        line-height: 1.25rem;
        width: 100%;
        transition: border-color 0.2s;
    }
    
    .scanned-input:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 1px #3b82f6;
    }
    
    /* Initially hide empty state and footer */
    #scanned-empty-state {
        display: none;
    }
    
    #scanned-card-footer {
        display: none;
    }

    /* Enhanced Folder View Styles */
    .scanned-folder-view {
        display: none;
        padding: 1rem;
    }

    .scanned-folder-item {
        margin-bottom: 1.5rem;
        border: 1px solid #e5e7eb;
        border-radius: 0.5rem;
        background-color: white;
        box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    }

    .scanned-folder-header {
        padding: 1rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1px solid #e5e7eb;
        cursor: pointer;
        background-color: #f9fafb;
    }

    .scanned-folder-header:hover {
        background-color: #f3f4f6;
    }

    .scanned-folder-title {
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .scanned-folder-icon {
        color: #f59e0b;
    }

    .scanned-folder-content {
        padding: 1rem;
        display: none;
    }

    .scanned-folder-content.show {
        display: block;
    }

    .scanned-document-item {
        padding: 1rem;
        border-bottom: 1px solid #e5e7eb;
        display: flex;
        gap: 1rem;
        align-items: flex-start;
    }

    .scanned-document-item:last-child {
        border-bottom: none;
    }

    .scanned-document-thumbnail {
        width: 80px;
        height: 100px;
        border: 1px solid #e5e7eb;
        border-radius: 0.25rem;
        object-fit: cover;
        flex-shrink: 0;
    }

    .scanned-document-details {
        flex-grow: 1;
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
    }

    .scanned-document-title {
        font-weight: 600;
        color: #111827;
    }

    .scanned-document-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        align-items: center;
    }

    .scanned-document-paper-size {
        font-size: 0.875rem;
        padding: 0.25rem 0.5rem;
        border-radius: 0.25rem;
        color: white;
    }

    .scanned-document-type {
        font-size: 0.875rem;
        color: #3b82f6;
        font-weight: 500;
    }

    .scanned-document-actions {
        display: flex;
        gap: 0.5rem;
        align-self: center;
    }

    .scanned-folder-actions {
        display: flex;
        justify-content: flex-end;
        padding: 1rem;
        border-top: 1px solid #e5e7eb;
        background-color: #f9fafb;
    }

    /* Paper size color classes */
    .size-A4 { background-color: #3b82f6; }
    .size-A5 { background-color: #10b981; }
    .size-A3 { background-color: #8b5cf6; }
    .size-Letter { background-color: #f59e0b; }
    .size-Legal { background-color: #f43f5e; }
    .size-Custom { background-color: #6b7280; }

    /* Active view indicator */
    .active-view {
        background-color: #e5e7eb;
    }
</style>

<div class="scanned-files-container">
    <div class="scanned-card">
        <div class="scanned-card-header">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h3 class="scanned-card-title">Scanned Documents</h3>
                    <p class="scanned-card-description">Documents scanned and ready for processing</p>
                </div>
                <div class="flex flex-col md:flex-row items-end md:items-center gap-2">
                    <div class="relative w-full md:w-64">
                        <svg class="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="11" cy="11" r="8"></circle>
                            <path d="m21 21-4.3-4.3"></path>
                        </svg>
                        <input type="search" placeholder="Search files..." class="scanned-input w-full pl-8" id="scanned-search-input">
                    </div>
                    <button class="scanned-button scanned-button-outline whitespace-nowrap" id="scanned-toggle-view">
                        <svg class="h-4 w-4 mr-2" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M4 20h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.93a2 2 0 0 1-1.66-.9l-.82-1.2A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13c0 1.1.9 2 2 2Z"></path>
                        </svg>
                        Folder View
                    </button>
                </div>
            </div>
        </div>
        <div class="scanned-card-content">
            <div id="scanned-empty-state" class="rounded-md border p-8 text-center">
                <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-muted">
                    <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"></path>
                        <polyline points="14 2 14 8 20 8"></polyline>
                        <line x1="16" y1="13" x2="8" y2="13"></line>
                        <line x1="16" y1="17" x2="8" y2="17"></line>
                        <line x1="10" y1="9" x2="8" y2="9"></line>
                    </svg>
                </div>
                <h3 class="mb-2 text-lg font-medium">No scanned documents yet</h3>
                <p class="mb-4 text-sm text-muted-foreground">
                    Scan documents to see them listed here
                </p>
                <button class="scanned-button scanned-button-primary gap-2" id="scanned-start-scanning">
                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 7V5a2 2 0 0 1 2-2h2"></path>
                        <path d="M17 3h2a2 2 0 0 1 2 2v2"></path>
                        <path d="M21 17v2a2 2 0 0 1-2 2h-2"></path>
                        <path d="M7 21H5a2 2 0 0 1-2-2v-2"></path>
                        <rect width="7" height="5" x="3" y="8" rx="1"></rect>
                        <rect width="7" height="5" x="14" y="8" rx="1"></rect>
                    </svg>
                    Start Scanning
                </button>
            </div>
            
            <!-- Table View (default) -->
            <div id="scanned-table-view" class="rounded-md border overflow-x-auto">
                <table class="scanned-table">
                    <thead class="scanned-table-header">
                        <tr class="scanned-table-row">
                            <th class="scanned-table-head cursor-pointer min-w-[150px]" data-sort="fileNumber">
                                File Number
                                <svg class="ml-2 h-4 w-4 inline" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="m21 16-4 4-4-4"></path>
                                    <path d="M17 20V4"></path>
                                    <path d="m3 8 4-4 4 4"></path>
                                    <path d="M7 4v16"></path>
                                </svg>
                            </th>
                            <th class="scanned-table-head cursor-pointer min-w-[200px]" data-sort="name">
                                Name
                                <svg class="ml-2 h-4 w-4 inline" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="m21 16-4 4-4-4"></path>
                                    <path d="M17 20V4"></path>
                                    <path d="m3 8 4-4 4 4"></path>
                                    <path d="M7 4v16"></path>
                                </svg>
                            </th>
                            <th class="scanned-table-head cursor-pointer min-w-[120px]" data-sort="date">
                                Scan Date
                                <svg class="ml-2 h-4 w-4 inline" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="m21 16-4 4-4-4"></path>
                                    <path d="M17 20V4"></path>
                                    <path d="m3 8 4-4 4 4"></path>
                                    <path d="M7 4v16"></path>
                                </svg>
                            </th>
                            <th class="scanned-table-head min-w-[120px]">
                                Status
                            </th>
                            <th class="scanned-table-head cursor-pointer min-w-[120px]" data-sort="pages">
                                Pages
                                <svg class="ml-2 h-4 w-4 inline" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="m21 16-4 4-4-4"></path>
                                    <path d="M17 20V4"></path>
                                    <path d="m3 8 4-4 4 4"></path>
                                    <path d="M7 4v16"></path>
                                </svg>
                            </th>
                            <th class="scanned-table-head cursor-pointer min-w-[120px]" data-sort="scannedBy">
                                Scanned By
                                <svg class="ml-2 h-4 w-4 inline" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="m21 16-4 4-4-4"></path>
                                    <path d="M17 20V4"></path>
                                    <path d="m3 8 4-4 4 4"></path>
                                    <path d="M7 4v16"></path>
                                </svg>
                            </th>
                            <th class="scanned-table-head text-right min-w-[100px]">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="scanned-table-body" class="scanned-table-body">
                        <!-- Table rows will be inserted here by JavaScript -->
                    </tbody>
                </table>
            </div>
            
            <!-- Folder View -->
            <div id="scanned-folder-view" class="scanned-folder-view">
                <!-- Folder items will be inserted here by JavaScript -->
            </div>
        </div>
        <div id="scanned-card-footer" class="scanned-card-footer">
            <button class="scanned-button scanned-button-outline" id="scanned-scan-more">
                <svg class="h-4 w-4 mr-2" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 7V5a2 2 0 0 1 2-2h2"></path>
                    <path d="M17 3h2a2 2 0 0 1 2 2v2"></path>
                    <path d="M21 17v2a2 2 0 0 1-2 2h-2"></path>
                    <path d="M7 21H5a2 2 0 0 1-2-2v-2"></path>
                    <rect width="7" height="5" x="3" y="8" rx="1"></rect>
                    <rect width="7" height="5" x="14" y="8" rx="1"></rect>
                </svg>
                Scan More
            </button>
            <a href="{{route('pagetyping.index')}}" class="scanned-button scanned-button-primary">
                <svg class="h-4 w-4 mr-2" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                    <polyline points="7 10 12 15 17 10"></polyline>
                    <line x1="12" y1="15" x2="12" y2="3"></line>
                </svg>
                Proceed to Page Typing
            </a>
        </div>
    </div>
</div>

<script>
    // Enhanced sample data with more realistic document images
    const scannedDocuments = [
        {
            id: "KNML 09846-01",
            fileNumber: "KNML 09846",
            name: "Alhaji Ibrahim Dantata",
            status: "Scanned",
            scanDate: "2023-06-15",
            pages: 5,
            paperSize: "A4",
            documentType: "Certificate",
            scannedBy: "Admin User",
            pageImages: [
                "{{ asset('storage/upload/dummy/1.jpg') }}",
                "{{ asset('storage/upload/dummy/2.jpg') }}",
                "{{ asset('storage/upload/dummy/3.jpg') }}",
                "{{ asset('storage/upload/dummy/4.jpg') }}",
                "{{ asset('storage/upload/dummy/5.jpg') }}"
            ],
            isProblematic: false
        },
        {
            id: "KNML 09846-02",
            fileNumber: "KNML 09846",
            name: "Alhaji Ibrahim Dantata",
            status: "Scanned",
            scanDate: "2023-06-14",
            pages: 3,
            paperSize: "Legal",
            documentType: "Deed",
            scannedBy: "Scanner Operator",
            pageImages: [
                "{{ asset('storage/upload/dummy/1.jpg') }}",
                "{{ asset('storage/upload/dummy/2.jpg') }}",
                "{{ asset('storage/upload/dummy/3.jpg') }}"
            ],
            isProblematic: true,
            problemDescription: "Page 3 is blurry"
        },
        {
            id: "KNML 09846-03",
            fileNumber: "KNML 09846",
            name: "Alhaji Ibrahim Dantata",
            status: "Scanned",
            scanDate: "2023-06-10",
            pages: 7,
            paperSize: "A3",
            documentType: "Letter",
            scannedBy: "Data Entry Clerk",
            pageImages: [
                "{{ asset('storage/upload/dummy/1.jpg') }}",
                "{{ asset('storage/upload/dummy/2.jpg') }}",
                "{{ asset('storage/upload/dummy/3.jpg') }}",
                "{{ asset('storage/upload/dummy/4.jpg') }}",
                "{{ asset('storage/upload/dummy/5.jpg') }}",
                "{{ asset('storage/upload/dummy/1.jpg') }}",
                "{{ asset('storage/upload/dummy/2.jpg') }}"
            ],
            isProblematic: false
        },
        {
            id: "MLKN 03888-01",
            fileNumber: "MLKN 03888",
            name: "Hajiya Fatima Mohammed",
            status: "Scanned",
            scanDate: "2023-06-18",
            pages: 2,
            paperSize: "Letter",
            documentType: "Application Form",
            scannedBy: "Admin User",
            pageImages: [
                "{{ asset('storage/upload/dummy/1.jpg') }}",
                "{{ asset('storage/upload/dummy/2.jpg') }}"
            ],
            isProblematic: false
        },
        {
            id: "KNML 08722-01",
            fileNumber: "KNML 08722",
            name: "Community Development Plan",
            status: "Scanned",
            scanDate: "2023-06-20",
            pages: 1,
            paperSize: "A5",
            documentType: "Receipt",
            scannedBy: "Scanner Operator",
            pageImages: [
                "{{ asset('storage/upload/dummy/1.jpg') }}"
            ],
            isProblematic: false
        }
    ];

    // DOM elements
    const scannedEmptyState = document.getElementById('scanned-empty-state');
    const scannedTableBody = document.getElementById('scanned-table-body');
    const scannedFolderView = document.getElementById('scanned-folder-view');
    const scannedCardFooter = document.getElementById('scanned-card-footer');
    const scannedSearchInput = document.getElementById('scanned-search-input');
    const scannedStartScanningBtn = document.getElementById('scanned-start-scanning');
    const scannedScanMoreBtn = document.getElementById('scanned-scan-more');
    const scannedToggleViewBtn = document.getElementById('scanned-toggle-view');
    const scannedSortableHeaders = document.querySelectorAll('[data-sort]');

    // Current state
    let scannedCurrentSort = {
        field: 'scanDate',
        direction: 'desc'
    };
    let scannedCurrentFilter = {
        searchTerm: ''
    };
    let scannedFilteredAndSortedDocuments = [...scannedDocuments];

    // Initialize the scanned files tab
    function initScannedFiles() {
        if (scannedFolderView) scannedFolderView.style.display = 'none';
        renderScannedTable();
        updateScannedView();
        
        // Set up event listeners
        if (scannedSearchInput) scannedSearchInput.addEventListener('input', handleScannedSearch);
        if (scannedStartScanningBtn) scannedStartScanningBtn.addEventListener('click', startScanning);
        if (scannedScanMoreBtn) scannedScanMoreBtn.addEventListener('click', startScanning);
        if (scannedToggleViewBtn) scannedToggleViewBtn.addEventListener('click', toggleScannedView);
        
        scannedSortableHeaders.forEach(header => {
            header.addEventListener('click', () => {
                const field = header.getAttribute('data-sort');
                handleScannedSort(field);
            });
        });
    }

    // Update view based on data
    function updateScannedView() {
        if (scannedFilteredAndSortedDocuments.length === 0) {
            if (scannedEmptyState) scannedEmptyState.style.display = 'block';
            const tableView = document.getElementById('scanned-table-view');
            if (tableView) tableView.style.display = 'none';
            if (scannedFolderView) scannedFolderView.style.display = 'none';
            if (scannedCardFooter) scannedCardFooter.style.display = 'none';
        } else {
            if (scannedEmptyState) scannedEmptyState.style.display = 'none';
            if (scannedCardFooter) scannedCardFooter.style.display = 'flex';
        }
    }

    // Render the table with current data
    function renderScannedTable() {
        if (!scannedTableBody) return;
        
        scannedTableBody.innerHTML = '';
        
        scannedFilteredAndSortedDocuments.forEach(doc => {
            const row = document.createElement('tr');
            row.className = 'scanned-table-row';
            
            // Add problematic document styling
            if (doc.isProblematic) {
                row.style.backgroundColor = '#fff1f1';
            }
            
            row.innerHTML = `
                <td class="scanned-table-cell font-medium">${doc.fileNumber}</td>
                <td class="scanned-table-cell">${doc.name}</td>
                <td class="scanned-table-cell">${doc.scanDate}</td>
                <td class="scanned-table-cell">
                    <span class="scanned-badge ${doc.isProblematic ? 'scanned-badge-destructive' : 'scanned-badge-primary'}">
                        ${doc.status}
                    </span>
                </td>
                <td class="scanned-table-cell">${doc.pages}</td>
                <td class="scanned-table-cell">${doc.scannedBy}</td>
                <td class="scanned-table-cell text-right">
                    <div class="flex justify-end">
                        <button class="scanned-button scanned-button-outline scanned-button-sm scanned-preview-btn" data-id="${doc.id}">
                            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </button>
                    </div>
                </td>
            `;
            scannedTableBody.appendChild(row);
        });
        
        // Add event listeners to buttons
        document.querySelectorAll('.scanned-preview-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const docId = e.target.closest('button').getAttribute('data-id');
                showScannedDocumentPreview(docId);
            });
        });
    }

    // Toggle between table and folder views
    function toggleScannedView() {
        const tableView = document.getElementById('scanned-table-view');
        const folderView = document.getElementById('scanned-folder-view');
        const toggleBtn = document.getElementById('scanned-toggle-view');
        
        if (!tableView || !folderView || !toggleBtn) return;
        
        if (tableView.style.display === 'none') {
            // Switch to table view
            tableView.style.display = 'block';
            folderView.style.display = 'none';
            toggleBtn.innerHTML = `
                <svg class="h-4 w-4 mr-2" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M4 20h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.93a2 2 0 0 1-1.66-.9l-.82-1.2A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13c0 1.1.9 2 2 2Z"></path>
                </svg>
                Folder View
            `;
            toggleBtn.classList.remove('active-view');
        } else {
            // Switch to folder view
            tableView.style.display = 'none';
            folderView.style.display = 'block';
            renderScannedFolderView();
            toggleBtn.innerHTML = `
                <svg class="h-4 w-4 mr-2" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="8" y1="6" x2="21" y2="6"></line>
                    <line x1="8" y1="12" x2="21" y2="12"></line>
                    <line x1="8" y1="18" x2="21" y2="18"></line>
                    <line x1="3" y1="6" x2="3.01" y2="6"></line>
                    <line x1="3" y1="12" x2="3.01" y2="12"></line>
                    <line x1="3" y1="18" x2="3.01" y2="18"></line>
                </svg>
                List View
            `;
            toggleBtn.classList.add('active-view');
        }
    }

    // Render the folder view
    function renderScannedFolderView() {
        if (!scannedFolderView) return;
        
        scannedFolderView.innerHTML = '';
        
        // Group documents by file number
        const groupedDocs = {};
        scannedFilteredAndSortedDocuments.forEach(doc => {
            if (!groupedDocs[doc.fileNumber]) {
                groupedDocs[doc.fileNumber] = {
                    name: doc.name,
                    documents: []
                };
            }
            groupedDocs[doc.fileNumber].documents.push(doc);
        });
        
        // Create folder items for each group
        for (const fileNumber in groupedDocs) {
            const group = groupedDocs[fileNumber];
            const folderItem = document.createElement('div');
            folderItem.className = 'scanned-folder-item';
            
            folderItem.innerHTML = `
                <div class="scanned-folder-header">
                    <div class="scanned-folder-title">
                        <svg class="h-5 w-5 scanned-folder-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M4 20h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.93a2 2 0 0 1-1.66-.9l-.82-1.2A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13c0 1.1.9 2 2 2Z"></path>
                        </svg>
                        <span>${fileNumber} - ${group.name}</span>
                    </div>
                    <div>
                        <span class="text-sm text-muted-foreground">${group.documents.length} documents</span>
                    </div>
                </div>
                <div class="scanned-folder-content">
                    ${group.documents.map((doc, index) => `
                        <div class="scanned-document-item">
                            <img src="${doc.pageImages[0]}" alt="Document thumbnail" class="scanned-document-thumbnail">
                            <div class="scanned-document-details">
                                <div class="scanned-document-title">${doc.id}</div>
                                <div class="scanned-document-meta">
                                    <span class="scanned-document-paper-size size-${doc.paperSize}">${doc.paperSize}</span>
                                    <span class="scanned-document-type">${doc.documentType}</span>
                                </div>
                                <div class="document-pages">Pages: ${doc.pages}</div>
                                <div class="document-scanned-by">Scanned By: ${doc.scannedBy}</div>
                                ${doc.isProblematic ? '<div class="text-sm text-rose-500">Problem: ' + doc.problemDescription + '</div>' : ''}
                            </div>
                            <div class="scanned-document-actions">
                                <button class="scanned-button scanned-button-outline scanned-button-sm scanned-preview-btn" data-id="${doc.id}">
                                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                        <circle cx="12" cy="12" r="3"></circle>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    `).join('')}
                    <div class="scanned-folder-actions">
                        <a href="{{route('pagetyping.index')}}" class="scanned-button scanned-button-primary scanned-start-typing-btn" data-filenumber="${fileNumber}">
                            <svg class="h-4 w-4 mr-2" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                <polyline points="7 10 12 15 17 10"></polyline>
                                <line x1="12" y1="15" x2="12" y2="3"></line>
                            </svg>
                            Start Page Typing for All Documents
                        </a>
                    </div>
                </div>
            `;
            
            scannedFolderView.appendChild(folderItem);
        }
        
        // Add click event to folder headers to toggle content
        document.querySelectorAll('.scanned-folder-header').forEach(header => {
            header.addEventListener('click', () => {
                const content = header.nextElementSibling;
                content.classList.toggle('show');
            });
        });
        
        // Add event listeners to preview buttons
        document.querySelectorAll('.scanned-preview-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const docId = e.target.closest('button').getAttribute('data-id');
                showScannedDocumentPreview(docId);
            });
        });
    }

    // Show document preview (placeholder function)
    function showScannedDocumentPreview(docId) {
        const doc = scannedDocuments.find(d => d.id === docId);
        if (doc) {
            alert(`Preview for document: ${doc.name} (${doc.id})\nPages: ${doc.pages}\nType: ${doc.documentType}`);
        }
    }

    // Handle search input
    function handleScannedSearch() {
        if (!scannedSearchInput) return;
        scannedCurrentFilter.searchTerm = scannedSearchInput.value.toLowerCase();
        applyScannedFilters();
    }

    // Apply all filters
    function applyScannedFilters() {
        scannedFilteredAndSortedDocuments = scannedDocuments.filter(doc => {
            // Search term filter
            const searchMatch = scannedCurrentFilter.searchTerm === '' || 
                doc.fileNumber.toLowerCase().includes(scannedCurrentFilter.searchTerm) ||
                doc.name.toLowerCase().includes(scannedCurrentFilter.searchTerm) ||
                doc.documentType.toLowerCase().includes(scannedCurrentFilter.searchTerm) ||
                doc.id.toLowerCase().includes(scannedCurrentFilter.searchTerm) ||
                doc.scannedBy.toLowerCase().includes(scannedCurrentFilter.searchTerm);
            
            return searchMatch;
        });
        
        // Re-apply sorting
        sortScannedDocuments(scannedCurrentSort.field, scannedCurrentSort.direction);
        
        // Update current view
        const tableView = document.getElementById('scanned-table-view');
        if (tableView && tableView.style.display !== 'none') {
            renderScannedTable();
        } else {
            renderScannedFolderView();
        }
        
        updateScannedView();
    }

    // Handle sorting
    function handleScannedSort(field) {
        // Toggle direction if same field is clicked
        const direction = scannedCurrentSort.field === field && scannedCurrentSort.direction === 'asc' ? 'desc' : 'asc';
        
        scannedCurrentSort = { field, direction };
        sortScannedDocuments(field, direction);
        
        const tableView = document.getElementById('scanned-table-view');
        if (tableView && tableView.style.display !== 'none') {
            renderScannedTable();
        } else {
            renderScannedFolderView();
        }
    }

    // Sort documents by field and direction
    function sortScannedDocuments(field, direction) {
        scannedFilteredAndSortedDocuments.sort((a, b) => {
            // Handle different field types
            if (field === 'scanDate') {
                const dateA = new Date(a[field]);
                const dateB = new Date(b[field]);
                return direction === 'asc' ? dateA - dateB : dateB - dateA;
            } else if (field === 'pages') {
                return direction === 'asc' ? a.pages - b.pages : b.pages - a.pages;
            } else {
                // Default string comparison
                const valueA = String(a[field] || '').toLowerCase();
                const valueB = String(b[field] || '').toLowerCase();
                return direction === 'asc' 
                    ? valueA.localeCompare(valueB) 
                    : valueB.localeCompare(valueA);
            }
        });
    }

    // Start scanning process
    function startScanning() {
        alert('Opening scanning interface...');
        // In a real app, this would launch the scanning dialog
    }

    // Initialize the scanned files tab when the document is loaded
    document.addEventListener('DOMContentLoaded', function() {
        // Small delay to ensure all elements are rendered
        setTimeout(initScannedFiles, 100);
    });
</script>