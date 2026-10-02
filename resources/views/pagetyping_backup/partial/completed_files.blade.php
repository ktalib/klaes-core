 
    <style>
        /* CSS Variables */
        :root {
            --primary: #3b82f6;
            --success: #10b981;
            --border: #e5e7eb;
            --muted: #6b7280;
            --light-bg: #f8fafc;
        }
        
        /* Clean Cards Design */
        .Cards {
            border-radius: 8px;
            border: 1px solid var(--border);
            background-color: white;
            overflow: hidden;
        }
        
        .CardsHeader {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--border);
        }
        
        .CardsTitle {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        
        .CardsDescription {
            font-size: 0.875rem;
            color: var(--muted);
        }
        
        .CardsContent {
            padding: 1.5rem;
        }
        
        /* Improved Search Box */
        .search-container {
            position: relative;
            width: 100%;
            max-width: 300px;
        }
        
        .search-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--muted);
        }
        
        .search-input {
            width: 100%;
            padding: 0.625rem 1rem 0.625rem 2.5rem;
            border-radius: 6px;
            border: 1px solid var(--border);
            font-size: 0.875rem;
            transition: border-color 0.2s;
        }
        
        .search-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.1);
        }
        
        /* Professional Table */
        .Table {
            width: 100%;
            font-size: 0.875rem;
            text-align: left;
            border-collapse: separate;
            border-spacing: 0;
        }
        
        .TableHeader {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            border-bottom: 2px solid var(--primary);
        }
        
        .TableHead {
            padding: 1rem 1rem;
            font-weight: 700;
            color: #374151;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: none;
            position: relative;
        }
        
        .TableHead::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: var(--primary);
        }
        
        .TableRow {
            border-bottom: 1px solid var(--border);
        }
        
        .TableCell {
            padding: 1rem;
            vertical-align: middle;
        }
        
        /* Status Badge */
        .Badge {
            display: inline-flex;
            align-items: center;
            border-radius: 9999px;
            padding: 0.375rem 0.875rem;
            font-size: 0.75rem;
            font-weight: 600;
            gap: 0.375rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        
        .Badge-success {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            border: 1px solid #059669;
        }
        
        .status-icon {
            width: 12px;
            height: 12px;
        }
        
        /* Simple Button */
        .Button {
            display: inline-flex;
            align-items: center;
            border-radius: 6px;
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
            font-weight: 500;
            border: 1px solid var(--border);
            background-color: white;
            cursor: pointer;
        }
        
        .Button-sm {
            padding: 0.375rem 0.75rem;
            font-size: 0.75rem;
        }
        
        /* Expanded Content - No Scrollbar */
        .expanded-content {
            padding: 1rem;
            background-color: var(--light-bg);
            border-top: 1px solid var(--border);
            overflow: hidden; /* Prevent scrollbar */
        }
        
        .page-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
            overflow: visible; /* Ensure no scrollbar */
        }
        
        .page-Cards {
            border: 1px solid var(--border);
            border-radius: 6px;
            overflow: hidden;
            background-color: white;
        }
        
        .page-image-container {
            height: 160px;
            background-color: #f8fafc;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .page-image {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
        
        .page-details {
            padding: 0.75rem;
            border-top: 1px solid var(--border);
        }
        
        .page-meta {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.25rem;
        }
        
        .page-code {
            font-size: 0.75rem;
            background-color: var(--primary);
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            max-width: 70%;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .page-type {
            font-size: 0.75rem;
            color: var(--muted);
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            background-color: var(--light-bg);
        }
        
        .page-subtype {
            font-size: 0.75rem;
            color: var(--muted);
        }
        
        /* Empty State */
        #empty-state {
            display: none;
            text-align: center;
            padding: 2rem;
            border: 1px solid var(--border);
            border-radius: 8px;
            background-color: white;
        }
        
        /* Progress Indicator */
        .progress-container {
            width: 100%;
            height: 4px;
            background-color: var(--border);
            border-radius: 2px;
            margin-top: 4px;
        }
        
        .progress-bar {
            height: 100%;
            background-color: var(--success);
            border-radius: 2px;
        }
        
        /* File Icon */
        .file-icon {
            color: var(--primary);
            margin-right: 0.5rem;
        }
        
        /* Typed By indicator */
        .typed-by {
            font-size: 0.75rem;
            color: var(--muted);
            margin-top: 0.25rem;
        }
    </style>
 
    <div class="TabsContent mt-6">
        <div class="Cards">
            <div class="CardsHeader">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <h3 class="CardsTitle">Completed Files</h3>
                        <p class="CardsDescription">Documents that have been fully processed and typed</p>
                    </div>
                    <div class="search-container">
                        <svg class="search-icon h-4 w-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="11" cy="11" r="8"></circle>
                            <path d="m21 21-4.3-4.3"></path>
                        </svg>
                        <input type="search" placeholder="Search files..." class="search-input" id="search-input">
                    </div>
                </div>
            </div>
            <div class="CardsContent">
                <div id="empty-state" class="rounded-lg border p-8 text-center">
                    <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-gray-100">
                        <svg class="h-6 w-6 text-gray-400" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"></path>
                            <polyline points="14 2 14 8 20 8"></polyline>
                        </svg>
                    </div>
                    <h3 class="mb-2 text-lg font-medium">No completed files yet</h3>
                    <p class="mb-4 text-sm text-gray-500">
                        Files will appear here once they've been fully processed
                    </p>
                </div>
                
                <div id="table-view" class="rounded-lg overflow-x-auto">
                    <table class="Table">
                        <thead class="TableHeader">
                            <tr class="TableRow">
                                <th class="TableHead min-w-[160px]">File Number</th>
                                <th class="TableHead min-w-[220px]">File Name</th>
                                <th class="TableHead min-w-[140px]">Date Typed</th>
                                <th class="TableHead min-w-[140px]">Typed By</th>
                                <th class="TableHead min-w-[120px]">Status</th>
                                <th class="TableHead min-w-[140px]">Pages</th>
                                <th class="TableHead text-right min-w-[100px]">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="table-body" class="TableBody">
                            @php
                                $completedFiles = [
                                    [
                                        'id' => "FILE-2023-004",
                                        'fileNumber' => "KNGP 01478",
                                        'name' => "Right of Occupancy - Musa Usman Bayero",
                                        'type' => "Right of Occupancy",
                                        'pages' => 4,
                                        'completed' => 4,
                                        'date' => "2023-06-12",
                                        'typedBy' => "Admin User",
                                        'status' => "Completed",
                                        'processedPages' => [
                                            ['pageNumber' => 1, 'pageCode' => "KNGP 01478-1-1-01", 'pageType' => "File Cover", 'pageSubType' => "New File Cover" ],
                                            [
                                                'pageNumber' => 2,
                                                'pageCode' => "KNGP 01478-5-5-02",
                                                'pageType' => "Land Title",
                                                'pageSubType' => "Certificate of Occupancy",
                                            ],
                                            ['pageNumber' => 3, 'pageCode' => "KNGP 01478-9-25-03", 'pageType' => "Survey", 'pageSubType' => "Survey Plan" ],
                                            [
                                                'pageNumber' => 4,
                                                'pageCode' => "KNGP 01478-4-8-04",
                                                'pageType' => "Correspondence",
                                                'pageSubType' => "Acknowledgment Letter",
                                            ],
                                        ],
                                        'pageImages' => [
                                            "https://images.unsplash.com/photo-1589998059171-988d887df646?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&h=1131&q=80",
                                            "https://images.unsplash.com/photo-1518455027359-f3f8164ba6bd?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&h=1131&q=80",
                                            "https://images.unsplash.com/photo-1544947950-fa07a98d237f?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&h=1131&q=80",
                                            "https://images.unsplash.com/photo-1589261454707-4341a1ff5e6e?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&h=1131&q=80"
                                        ]
                                    ],
                                    [
                                        'id' => "FILE-2023-005",
                                        'fileNumber' => "KNML 37925",
                                        'name' => "Deed of Assignment - Hajiya Fatima Mohammed",
                                        'type' => "Deed of Assignment",
                                        'pages' => 6,
                                        'completed' => 6,
                                        'date' => "2023-06-10",
                                        'typedBy' => "Data Entry Clerk",
                                        'status' => "Completed",
                                        'processedPages' => [
                                            [ 'pageNumber' => 1, 'pageCode' => "KNML 37925-1-1-01", 'pageType' => "File Cover", 'pageSubType' => "New File Cover" ],
                                            [ 'pageNumber' => 2, 'pageCode' => "KNML 37925-6-53-02", 'pageType' => "Legal", 'pageSubType' => "Deed of Assignment" ],
                                            [ 'pageNumber' => 3, 'pageCode' => "KNML 37925-6-53-03", 'pageType' => "Legal", 'pageSubType' => "Deed of Assignment" ],
                                            [ 'pageNumber' => 4, 'pageCode' => "KNML 37925-7-20-04", 'pageType' => "Payment Evidence", 'pageSubType' => "Bank Teller" ],
                                            [ 'pageNumber' => 5, 'pageCode' => "KNML 37925-7-78-05", 'pageType' => "Payment Evidence", 'pageSubType' => "Receipts" ],
                                            [
                                                'pageNumber' => 6,
                                                'pageCode' => "KNML 37925-4-72-06",
                                                'pageType' => "Correspondence",
                                                'pageSubType' => "Letter of Acceptance",
                                            ],
                                        ],
                                        'pageImages' => [
                                            "https://images.unsplash.com/photo-1544716278-ca5e3f4abd8c?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&h=1131&q=80",
                                            "https://images.unsplash.com/photo-1544947950-fa07a98d237f?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&h=1131&q=80",
                                            "https://images.unsplash.com/photo-1518455027359-f3f8164ba6bd?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&h=1131&q=80",
                                            "https://images.unsplash.com/photo-1589998059171-988d887df646?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&h=1131&q=80",
                                            "https://images.unsplash.com/photo-1541963463532-d68292c34b19?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&h=1131&q=80",
                                            "https://images.unsplash.com/photo-1589261454707-4341a1ff5e6e?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&h=1131&q=80"
                                        ]
                                    ]
                                ];
                            @endphp
                            @foreach($completedFiles as $file)
                                <tr class="TableRow" data-file-id="{{ $file['id'] }}">
                                    <td class="TableCell font-medium">
                                        <div class="flex items-center">
                                            <svg class="file-icon h-5 w-5 mr-2" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"></path>
                                                <polyline points="14 2 14 8 20 8"></polyline>
                                            </svg>
                                            <span>{{ $file['fileNumber'] }}</span>
                                        </div>
                                    </td>
                                    <td class="TableCell">
                                        <div class="font-medium">{{ explode(' - ', $file['name'])[1] ?? $file['name'] }}</div>
                                    </td>
                                    <td class="TableCell">
                                        <div>{{ $file['date'] }}</div>
                                    </td>
                                    <td class="TableCell">
                                        <div>{{ $file['typedBy'] }}</div>
                                    </td>
                                    <td class="TableCell">
                                        <div class="Badge Badge-success">
                                            <svg class="status-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <polyline points="20 6 9 17 4 12"></polyline>
                                            </svg>
                                            {{ $file['status'] }}
                                        </div>
                                        <div class="progress-container">
                                            <div class="progress-bar" style="width: {{ ($file['completed'] / $file['pages']) * 100 }}%"></div>
                                        </div>
                                    </td>
                                    <td class="TableCell">
                                        <div>{{ $file['completed'] }}/{{ $file['pages'] }}</div>
                                    </td>
                                    <td class="TableCell text-right">
                                        <button class="Button Button-sm toggle-expand-btn" data-id="{{ $file['id'] }}">
                                            Show Pages
                                        </button>
                                    </td>
                                </tr>
                                <tr class="TableRow hidden" data-expanded-id="{{ $file['id'] }}">
                                    <td colspan="7" class="TableCell p-0">
                                        <div class="expanded-content">
                                            <h4 class="text-sm font-medium text-gray-700">Processed Pages</h4>
                                            <div class="page-grid">
                                                @foreach($file['processedPages'] as $index => $page)
                                                    <div class="page-Cards">
                                                        <div class="page-image-container">
                                                            <img src="{{ $file['pageImages'][$index] }}" alt="Page {{ $page['pageNumber'] }}" class="page-image">
                                                        </div>
                                                        <div class="page-details">
                                                            <div class="page-meta">
                                                                <span class="page-code" title="{{ $page['pageCode'] }}">{{ $page['pageCode'] }}</span>
                                                                <span class="page-type">{{ $page['pageType'] }}</span>
                                                            </div>
                                                            <div class="page-subtype">{{ $page['pageSubType'] }}</div>
                                                            <div class="typed-by">Typed by: {{ $file['typedBy'] }}</div>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
     

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const searchInput = document.getElementById('search-input');
        const tableBody = document.getElementById('table-body');
        const tableRows = tableBody.querySelectorAll('tr[data-file-id]');
        const emptyState = document.getElementById('empty-state');

        function handleSearch() {
            const searchTerm = searchInput.value.toLowerCase();
            let visibleRows = 0;

            tableRows.forEach(row => {
                const fileNumber = row.querySelector('td:nth-child(1) span').textContent.toLowerCase();
                const fileName = row.querySelector('td:nth-child(2) div').textContent.toLowerCase();
                const dateTyped = row.querySelector('td:nth-child(3) div').textContent.toLowerCase();
                const typedBy = row.querySelector('td:nth-child(4) div').textContent.toLowerCase();
                
                const expandedRow = tableBody.querySelector(`tr[data-expanded-id="${row.dataset.fileId}"]`);

                if (
                    fileNumber.includes(searchTerm) ||
                    fileName.includes(searchTerm) ||
                    dateTyped.includes(searchTerm) ||
                    typedBy.includes(searchTerm)
                ) {
                    row.classList.remove('hidden');
                    visibleRows++;
                } else {
                    row.classList.add('hidden');
                    if (expandedRow) {
                        expandedRow.classList.add('hidden');
                    }
                }
            });

            if (visibleRows === 0) {
                emptyState.style.display = 'block';
                tableBody.parentElement.style.display = 'none';
            } else {
                emptyState.style.display = 'none';
                tableBody.parentElement.style.display = '';
            }
        }

        searchInput.addEventListener('input', handleSearch);

        document.querySelectorAll('.toggle-expand-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const fileId = e.target.closest('button').getAttribute('data-id');
                const expandedRow = tableBody.querySelector(`tr[data-expanded-id="${fileId}"]`);
                
                if (expandedRow) {
                    expandedRow.classList.toggle('hidden');
                    e.target.textContent = expandedRow.classList.contains('hidden') ? 'Show Pages' : 'Hide Pages';
                }
            });
        });
        
        // Initial check for empty state
        if (tableRows.length === 0) {
            emptyState.style.display = 'block';
            tableBody.parentElement.style.display = 'none';
        } else {
            emptyState.style.display = 'none';
            tableBody.parentElement.style.display = '';
        }
    });
</script>
                    </table>
                </div>
            </div>
        </div>
    </div>

     


