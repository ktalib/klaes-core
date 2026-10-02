@extends('layouts.app')
@section('page-title')
    {{ __('Document Upload') }}
@endsection

@php
    // Check if URL parameter 'url' is set to 'scmore' to show scan more features
    $showScanMore = request()->get('url') === 'scmore';
@endphp

@section('content')
    <!-- Main Content -->
    <div class="flex-1 overflow-auto">
        <!-- Header --> 
        @include('admin.header') 
        <!-- Dashboard Content -->
        <div class="p-6">
            @include('scanning.assets.style')
            
            <!-- Switch Button Styles -->
            <style>
                .switch-container {
                    position: relative;
                    display: inline-block;
                }
                
                .switch-input {
                    display: none;
                }
                
                .switch-label {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    width: 120px;
                    height: 32px;
                    background-color: #e5e7eb;
                    border-radius: 16px;
                    cursor: pointer;
                    transition: background-color 0.3s;
                    position: relative;
                    padding: 2px;
                }
                
                .switch-input:checked + .switch-label {
                    background-color: #3b82f6;
                }
                
                .switch-handle {
                    width: 28px;
                    height: 28px;
                    background-color: white;
                    border-radius: 50%;
                    transition: transform 0.3s;
                    position: absolute;
                    left: 2px;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
                }
                
                .switch-input:checked + .switch-label .switch-handle {
                    transform: translateX(88px);
                }
                
                .switch-text {
                    font-size: 12px;
                    font-weight: 500;
                    color: #374151;
                    position: absolute;
                    width: 100%;
                    text-align: center;
                    z-index: 1;
                }
                
                .switch-input:checked + .switch-label .switch-text {
                    color: white;
                }
                
                .switch-text::before {
                    content: attr(data-off);
                }
                
                .switch-input:checked + .switch-label .switch-text::before {
                    content: attr(data-on);
                }
                
                /* Dropdown Menu Styles */
                .table-container {
                    overflow: visible !important;
                }
                
                .dropdown-menu {
                    position: fixed !important;
                    z-index: 10000 !important;
                    min-width: 12rem;
                    background: white;
                    border: 1px solid #e5e7eb;
                    border-radius: 0.375rem;
                    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
                    transform: translateY(0);
                    transition: opacity 0.15s ease-in-out, transform 0.15s ease-in-out;
                }
                
                .dropdown-menu.show {
                    display: block !important;
                    opacity: 1;
                    transform: translateY(0);
                }
                
                .dropdown-menu.hidden {
                    opacity: 0;
                    transform: translateY(-10px);
                }
                
                /* Responsive adjustments */
                @media (max-width: 768px) {
                    .dropdown-menu {
                        right: 1rem !important;
                        left: auto !important;
                        min-width: 10rem;
                    }
                }
            </style>
            
            <div class="container mx-auto py-6 space-y-6">
                <!-- Page Header -->
                <div class="flex flex-col space-y-4">
                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                        <div>
                            <h1 class="text-2xl font-bold tracking-tight" id="page-title">
                                @if($showScanMore)
                                    Scan more File
                                @else
                                    Upload Indexed Scanned File
                                @endif
                            </h1>
                            <p class="text-muted-foreground" id="page-description">
                                @if($showScanMore)
                                    Upload additional scanned documents to existing digital folders
                                @else
                                    Upload scanned documents to their digital folders
                                @endif
                            </p>
                        </div>
                        
                        <!-- Switch Button for Indexed/Unindexed -->
                        <div class="flex items-center gap-4">
                            <div class="flex items-center gap-2">
                              
                                <div class="flex items-center gap-2">
                                  
                                 
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    @if($selectedFileIndexing)
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4" id="selected-file-info">
                            <div class="flex items-center">
                                <i data-lucide="folder-open" class="h-5 w-5 text-blue-600 mr-2"></i>
                                <div>
                                    <p class="font-medium text-blue-900">Selected File: {{ $selectedFileIndexing->file_number }}</p>
                                    <p class="text-sm text-blue-700">{{ $selectedFileIndexing->file_title }}</p>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>

                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                    <!-- Today's Uploads -->
                    <div class="card">
                        <div class="p-4 pb-2">
                            <h3 class="text-sm font-medium">Today's Uploads</h3>
                        </div>
                        <div class="p-4 pt-0">
                            <div class="text-2xl font-bold" id="uploads-count">{{ $stats['uploads_today'] ?? 0 }}</div>
                            <p class="text-xs text-muted-foreground mt-1">Batches uploaded today</p>
                        </div>
                    </div>

                    <!-- Pending Page Typing -->
                    <div class="card">
                        <div class="p-4 pb-2">
                            <h3 class="text-sm font-medium">Pending Page Typing</h3>
                        </div>
                        <div class="p-4 pt-0">
                            <div class="text-2xl font-bold" id="pending-count">{{ $stats['pending_page_typing'] ?? 0 }}</div>
                            <p class="text-xs text-muted-foreground mt-1">Documents waiting for page typing</p>
                        </div>
                    </div>

                    <!-- Total Scanned -->
                    <div class="card">
                        <div class="p-4 pb-2">
                            <h3 class="text-sm font-medium">Total Scanned</h3>
                        </div>
                        <div class="p-4 pt-0">
                            <div class="text-2xl font-bold flex items-center">
                                {{ $stats['total_scanned'] ?? 0 }}
                                <span class="badge ml-2 bg-blue-500 text-white">Total</span>
                            </div>
                            <p class="text-xs text-muted-foreground mt-1">All scanned documents in system</p>
                        </div>
                    </div>
                </div>

                <!-- Main Content Area -->
                <div id="indexed-upload-content">
                    <!-- Tabs -->
                    <div class="tabs">
                        <div class="tabs-list grid w-full md:w-auto grid-cols-2">
                            <button class="tab active" role="tab" aria-selected="true" data-tab="upload">
                                @if($showScanMore)
                                    Scan more File
                                @else
                                    Upload Indexed Scanned File
                                @endif
                            </button>
                            <button class="tab" role="tab" aria-selected="false" data-tab="scanned-files">Scanned Files</button>
                        </div>

                        <!-- Upload Tab -->
                        <div class="tab-content mt-6 active" role="tabpanel" aria-hidden="false" data-tab-content="upload">
                            <div class="card">
                                <div class="p-6 border-b">
                                    <div class="flex flex-col md:flex-row md:items-center justify-between">
                                        <div>
                                            <h2 class="text-lg font-semibold">
                                                @if($showScanMore)
                                                    Scan more File
                                                @else
                                                    Upload Indexed Scanned File
                                                @endif
                                            </h2>
                                            <p class="text-sm text-muted-foreground">
                                                @if($showScanMore)
                                                    Upload additional scanned documents to existing digital folders
                                                @else
                                                    Upload scanned documents to their digital folders
                                                @endif
                                            </p>
                                        </div>
                                        <div class="mt-2 md:mt-0 selected-file-badge {{ $selectedFileIndexing ? '' : 'hidden' }}">
                                            <span class="badge bg-blue-500 text-white px-3 py-1 flex items-center">
                                                <i data-lucide="folder-open" class="h-4 w-4 mr-2"></i>
                                                <span id="selected-file-number">{{ $selectedFileIndexing->file_number ?? 'No file selected' }}</span>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="p-6">
                                    <div class="space-y-6">
                                        <div class="flex justify-between items-center">
                                            <label class="text-sm font-medium">
                                                @if($showScanMore)
                                                    Selected Indexed File
                                                @else
                                                    Select Indexed File
                                                @endif
                                            </label>
                                            @if(!$showScanMore)
                                            <button class="btn btn-outline btn-sm gap-1" id="select-file-btn">
                                                <i data-lucide="folder" class="h-4 w-4"></i>
                                                <span id="change-file-text">{{ $selectedFileIndexing ? 'Change File' : 'Select File' }}</span>
                                            </button>
                                            @endif
                                        </div>

                                        <!-- Upload area -->
                                        <div class="border rounded-md p-4">
                                            <h3 class="text-sm font-medium mb-4">Upload Scanned Documents</h3>

                                            <!-- Idle state -->
                                            <div id="upload-idle" class="rounded-md border-2 border-dashed p-8 text-center">
                                                <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-muted">
                                                    <i data-lucide="file-up" class="h-6 w-6"></i>
                                                </div>
                                                <h3 class="mb-2 text-lg font-medium">Drag and drop scanned documents here</h3>
                                                <p class="mb-4 text-sm text-muted-foreground">or click to browse files on your computer</p>
                                                <input type="file" multiple class="hidden" id="file-upload" accept=".pdf,.jpg,.jpeg,.png,.tiff">
                                                <button class="btn btn-primary gap-2" id="browse-files-btn" {{ $selectedFileIndexing ? '' : 'disabled' }}>
                                                    <i data-lucide="upload" class="h-4 w-4"></i>
                                                    Browse Files
                                                </button>
                                                @if(!$selectedFileIndexing)
                                                    <p class="mt-2 text-sm text-red-500" id="select-file-warning">Please select an indexed file first</p>
                                                @endif
                                            </div>

                                            <!-- Selected files list -->
                                            <div id="selected-files-container" class="rounded-md border divide-y mt-4 hidden">
                                                <div class="p-3 bg-muted/50 flex justify-between items-center">
                                                    <span class="font-medium"><span id="selected-files-count">0</span> files selected</span>
                                                    <button class="btn btn-ghost btn-sm" id="clear-all-btn">Clear All</button>
                                                </div>
                                                <div id="selected-files-list">
                                                    <!-- Files will be added here dynamically -->
                                                </div>
                                            </div>

                                            <!-- Uploading state -->
                                            <div id="upload-progress" class="space-y-2 mt-4 hidden">
                                                <div class="flex justify-between text-sm">
                                                    <span>Uploading <span id="uploading-count">0</span> files...</span>
                                                    <span id="upload-percentage">0%</span>
                                                </div>
                                                <div class="progress">
                                                    <div class="progress-bar" id="progress-bar" style="width: 0%"></div>
                                                </div>
                                            </div>

                                            <!-- Complete state -->
                                            <div id="upload-complete" class="mt-4 p-4 bg-green-50 border border-green-100 rounded-md hidden">
                                                <div class="flex items-center gap-2 text-green-700">
                                                    <i data-lucide="check-circle" class="h-5 w-5"></i>
                                                    <span class="font-medium">Upload Complete!</span>
                                                </div>
                                                <p class="text-sm text-green-700 mt-1">
                                                    Files have been successfully uploaded and organized by paper size.
                                                </p>
                                            </div>
                                        </div>

                                        <!-- Action buttons -->
                                        <div class="flex flex-col md:flex-row gap-4 justify-center">
                                            <!-- Start upload button (idle state) -->
                                            <button class="btn btn-primary gap-2 hidden" id="start-upload-btn">
                                                <i data-lucide="upload" class="h-4 w-4"></i>
                                                Start Upload
                                            </button>

                                            <!-- Cancel button (uploading state) -->
                                            <button class="btn btn-destructive gap-2 hidden" id="cancel-upload-btn">
                                                <i data-lucide="alert-circle" class="h-4 w-4"></i>
                                                Cancel
                                            </button>

                                            <!-- Complete state buttons -->
                                            @if($showScanMore)
                                            <button class="btn btn-outline gap-2 hidden" id="upload-more-btn">
                                                <i data-lucide="refresh-cw" class="h-4 w-4"></i>
                                                Upload More
                                            </button>
                                            @endif
                                            <button class="btn btn-primary gap-2 hidden" id="view-uploaded-btn">
                                                <i data-lucide="check-circle" class="h-4 w-4"></i>
                                                View Uploaded Files
                                            </button>
                                            <a href="{{ route('pagetyping.index', ['file_indexing_id' => $selectedFileIndexing->id ?? '']) }}" 
                                               class="btn btn-primary gap-2 hidden" id="proceed-page-typing-btn">
                                                <i data-lucide="type" class="h-4 w-4"></i>
                                                Proceed to Page Typing
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Scanned Files Tab -->
                        <div class="tab-content mt-6 hidden" role="tabpanel" aria-hidden="true" data-tab-content="scanned-files">
                            <div class="card">
                                <div class="p-6 border-b">
                                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                                        <div>
                                            <h2 class="text-lg font-semibold">Scanned Files</h2>
                                            <p class="text-sm text-muted-foreground">View and manage uploaded documents</p>
                                        </div>
                                        <div class="relative w-full md:w-64">
                                            <i data-lucide="search" class="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground"></i>
                                            <input type="search" placeholder="Search files..." class="input w-full pl-8" id="search-scanned-files">
                                        </div>
                                    </div>
                                </div>
                                <div class="p-6">
                                    @if($recentScans && $recentScans->count() > 0)
                                        <div class="overflow-x-auto table-container">
                                            <table class="min-w-full divide-y divide-gray-200">
                                                <thead class="bg-gray-50">
                                                    <tr>
                                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">File No</th>
                                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Scan Date</th>
                                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pages</th>
                                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Scanned By</th>
                                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="bg-white divide-y divide-gray-200">
                                                    @foreach($recentScans as $scan)
                                                        <tr class="hover:bg-gray-50">
                                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                                {{ $scan->file_number ?? 'Unknown' }}
                                                            </td>
                                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                                {{ $scan->file_title ?? 'Document' }}
                                                            </td>
                                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                                {{ $scan->latest_scan_date ? $scan->latest_scan_date->format('M d, Y') : 'N/A' }}
                                                            </td>
                                                            <td class="px-6 py-4 whitespace-nowrap">
                                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                                                    {{ $scan->status === 'typed' ? 'bg-green-100 text-green-800' : 
                                                                       ($scan->status === 'scanned' ? 'bg-blue-100 text-blue-800' : 'bg-yellow-100 text-yellow-800') }}">
                                                                    {{ ucfirst($scan->status) }}
                                                                </span>
                                                            </td>
                                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                                {{ $scan->scan_count }} {{ $scan->scan_count == 1 ? 'scan' : 'scans' }}
                                                            </td>
                                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                                {{ $scan->uploader->name ?? 'Unknown' }}
                                                            </td>
                                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium relative">
                                                                <div class="relative inline-block">
                                                                    <button class="text-gray-400 hover:text-gray-600 p-1 rounded-full focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" onclick="toggleDropdown('dropdown-{{ $scan->id }}')">
                                                                        <i data-lucide="more-vertical" class="h-5 w-5"></i>
                                                                    </button>
                                                                    <div id="dropdown-{{ $scan->id }}" class="dropdown-menu absolute right-0 top-full mt-1 w-48 bg-white rounded-md shadow-lg z-[9999] hidden border border-gray-200">
                                                                        <div class="py-1">
                                                                            <button class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition-colors duration-150" onclick="viewFileScans({{ $scan->id }})">
                                                                                <i data-lucide="eye" class="h-4 w-4 mr-2 inline"></i>
                                                                                View
                                                                            </button>
                                                                            @if($showScanMore)
                                                                            <button class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition-colors duration-150" onclick="handleUploadMore({{ $scan->id }})">
                                                                                <i data-lucide="plus-circle" class="h-4 w-4 mr-2 inline"></i>
                                                                                Upload More
                                                                            </button>
                                                                            @endif
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    @else
                                        <div class="text-center py-8">
                                            <i data-lucide="inbox" class="h-12 w-12 mx-auto text-gray-300 mb-4"></i>
                                            <p class="text-gray-500">No scanned files found</p>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Unindexed Upload Content (Hidden by default) -->
               

                <!-- File Selector Dialog -->
                <div id="file-selector-dialog" class="dialog-backdrop hidden" aria-hidden="true">
                    <div class="dialog-content animate-fade-in">
                        <div class="p-4 border-b">
                            <h2 class="text-lg font-semibold">Select Indexed File for Document Upload</h2>
                        </div>
                        <div class="py-4 px-6">
                            <div class="relative mb-4">
                                <i data-lucide="search" class="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground"></i>
                                <input type="search" placeholder="Search indexed files..." class="input w-full pl-8" id="search-indexed-files">
                            </div>
                            <div class="rounded-md border divide-y max-h-[400px] overflow-y-auto" id="indexed-files-list">
                                <!-- Indexed files will be loaded here dynamically -->
                            </div>
                        </div>
                        <div class="flex justify-end gap-2 p-4 border-t">
                            <button class="btn btn-outline" id="cancel-file-select-btn">Cancel</button>
                            <button class="btn btn-primary" id="confirm-file-select-btn" disabled>Select File</button>
                        </div>
                    </div>
                </div>

                <!-- Document Details Dialog -->
                <div id="document-details-dialog" class="dialog-backdrop hidden" aria-hidden="true">
                    <div class="dialog-content animate-fade-in">
                        <div class="p-4 border-b">
                            <h2 class="text-lg font-semibold">Document Details</h2>
                        </div>
                        <div class="py-4 px-6 space-y-4">
                            <div>
                                <label for="document-name" class="block mb-2 text-sm font-medium">File Name</label>
                                <p class="text-sm font-medium" id="document-name"></p>
                            </div>

                            <div>
                                <label for="paper-size" class="block mb-2 text-sm font-medium">Paper Size</label>
                                <div class="radio-group">
                                    <div class="radio-item">
                                        <input type="radio" name="paper-size" id="A4" value="A4">
                                        <label for="A4" class="text-sm">A4</label>
                                    </div>
                                    <div class="radio-item">
                                        <input type="radio" name="paper-size" id="A5" value="A5">
                                        <label for="A5" class="text-sm">A5</label>
                                    </div>
                                    <div class="radio-item">
                                        <input type="radio" name="paper-size" id="A3" value="A3">
                                        <label for="A3" class="text-sm">A3</label>
                                    </div>
                                    <div class="radio-item">
                                        <input type="radio" name="paper-size" id="Letter" value="Letter">
                                        <label for="Letter" class="text-sm">Letter</label>
                                    </div>
                                    <div class="radio-item">
                                        <input type="radio" name="paper-size" id="Legal" value="Legal">
                                        <label for="Legal" class="text-sm">Legal</label>
                                    </div>
                                    <div class="radio-item">
                                        <input type="radio" name="paper-size" id="Custom" value="Custom">
                                        <label for="Custom" class="text-sm">Custom</label>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <label for="document-type" class="block mb-2 text-sm font-medium">Document Type</label>
                                <select id="document-type" class="input">
                                    <option value="Certificate">Certificate</option>
                                    <option value="Deed">Deed</option>
                                    <option value="Letter">Letter</option>
                                    <option value="Application Form">Application Form</option>
                                    <option value="Map">Map</option>
                                    <option value="Survey Plan">Survey Plan</option>
                                    <option value="Receipt">Receipt</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>

                            <div>
                                <label for="document-notes" class="block mb-2 text-sm font-medium">Notes (Optional)</label>
                                <textarea id="document-notes" class="input" rows="3"></textarea>
                            </div>
                        </div>
                        <div class="flex justify-end gap-2 p-4 border-t">
                            <button class="btn btn-outline" id="cancel-details-btn">Cancel</button>
                            <button class="btn btn-primary" id="save-details-btn">Save Details</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
     
        <!-- Footer -->
        @include('admin.footer')
        
        <!-- Enhanced JavaScript -->
        <script>
            // Initialize Lucide icons
            lucide.createIcons();

            // Function to toggle dropdown visibility
            function toggleDropdown(dropdownId) {
                const dropdown = document.getElementById(dropdownId);
                if (dropdown) {
                    // Close any other open dropdowns first
                    document.querySelectorAll('.dropdown-menu').forEach(menu => {
                        if (menu.id !== dropdownId) {
                            menu.classList.add('hidden');
                            menu.classList.remove('show');
                        }
                    });
                    
                    if (dropdown.classList.contains('hidden')) {
                        // Position the dropdown using fixed positioning
                        const button = dropdown.previousElementSibling;
                        const rect = button.getBoundingClientRect();
                        const viewportWidth = window.innerWidth;
                        const viewportHeight = window.innerHeight;
                        const dropdownWidth = 192; // w-48 = 12rem = 192px
                        
                        dropdown.style.position = 'fixed';
                        dropdown.style.zIndex = '10000';
                        
                        // Calculate horizontal position
                        let leftPos = rect.right - dropdownWidth;
                        if (leftPos < 10) {
                            leftPos = rect.left;
                        }
                        if (leftPos + dropdownWidth > viewportWidth - 10) {
                            leftPos = viewportWidth - dropdownWidth - 10;
                        }
                        
                        // Calculate vertical position
                        let topPos = rect.bottom + 5;
                        if (topPos + 100 > viewportHeight) { // Estimate dropdown height
                            topPos = rect.top - 100 - 5;
                        }
                        
                        dropdown.style.top = topPos + 'px';
                        dropdown.style.left = leftPos + 'px';
                        
                        dropdown.classList.remove('hidden');
                        dropdown.classList.add('show');
                    } else {
                        dropdown.classList.add('hidden');
                        dropdown.classList.remove('show');
                    }
                }
            }
            
            // Close dropdowns when clicking outside
            document.addEventListener('click', function(event) {
                const isDropdownButton = event.target.closest('button[onclick*="toggleDropdown"]');
                const isDropdownMenu = event.target.closest('.dropdown-menu');
                
                if (!isDropdownButton && !isDropdownMenu) {
                    document.querySelectorAll('.dropdown-menu').forEach(menu => {
                        menu.classList.add('hidden');
                        menu.classList.remove('show');
                    });
                }
            });
            
            // Close dropdowns on scroll or resize
            window.addEventListener('scroll', function() {
                document.querySelectorAll('.dropdown-menu.show').forEach(menu => {
                    menu.classList.add('hidden');
                    menu.classList.remove('show');
                });
            });
            
            window.addEventListener('resize', function() {
                document.querySelectorAll('.dropdown-menu.show').forEach(menu => {
                    menu.classList.add('hidden');
                    menu.classList.remove('show');
                });
            });

            // Application state
            let uploadState = {
                isUnindexedMode: false,
                selectedFileId: null,
                selectedFiles: [],
                uploadProgress: 0
            };

            // Switch between Indexed and Unindexed upload modes
            document.getElementById('upload-type-switch').addEventListener('change', function() {
                uploadState.isUnindexedMode = this.checked;
                toggleUploadMode();
            });

            function toggleUploadMode() {
                const pageTitle = document.getElementById('page-title');
                const pageDescription = document.getElementById('page-description');
                const selectedFileInfo = document.getElementById('selected-file-info');
                const indexedContent = document.getElementById('indexed-upload-content');
                const unindexedContent = document.getElementById('unindexed-upload-content');

                if (uploadState.isUnindexedMode) {
                    // Switch to Unindexed mode
                    @if($showScanMore)
                        pageTitle.textContent = 'Scan more File (Unindexed)';
                        pageDescription.textContent = 'Upload additional scanned documents without existing indexing records';
                    @else
                        pageTitle.textContent = 'Upload Unindexed Scanned File';
                        pageDescription.textContent = 'Upload scanned documents without existing indexing records';
                    @endif
                    if (selectedFileInfo) selectedFileInfo.style.display = 'none';
                    indexedContent.classList.add('hidden');
                    unindexedContent.classList.remove('hidden');
                } else {
                    // Switch to Indexed mode
                    @if($showScanMore)
                        pageTitle.textContent = 'Scan more File';
                        pageDescription.textContent = 'Upload additional scanned documents to existing digital folders';
                    @else
                        pageTitle.textContent = 'Upload Indexed Scanned File';
                        pageDescription.textContent = 'Upload scanned documents to their digital folders';
                    @endif
                    if (selectedFileInfo) selectedFileInfo.style.display = 'block';
                    indexedContent.classList.remove('hidden');
                    unindexedContent.classList.add('hidden');
                }
            }

            // Upload More action handler - now using direct onclick handlers
            @if($showScanMore)
            function handleUploadMore(fileId) {
                // Close the dropdown first
                document.querySelectorAll('.dropdown-menu').forEach(menu => {
                    menu.classList.add('hidden');
                    menu.classList.remove('show');
                });
                
                // Get CSRF token from meta tag
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                
                if (!csrfToken) {
                    showNotification('CSRF token not found. Please refresh the page and try again.', 'error');
                    return;
                }
                
                // Show loading notification
                showNotification('Processing request...', 'info');
                
                // Set is_updated = 1 for the file
                fetch(`/scanning/upload-more/${fileId}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({})
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        // Show success message
                        showNotification('File marked for additional uploads successfully!', 'success');
                        
                        // Redirect to upload interface for this file after a short delay
                        setTimeout(() => {
                            window.location.href = `/scanning?file_indexing_id=${fileId}`;
                        }, 1500);
                    } else {
                        showNotification('Error: ' + (data.message || 'Unknown error occurred'), 'error');
                    }
                })
                .catch(error => {
                    console.error('Error in handleUploadMore:', error);
                    showNotification('An error occurred while processing the request. Please try again.', 'error');
                });
            }
            @else
            function handleUploadMore(fileId) {
                showNotification('Upload More feature is not available in this mode.', 'error');
            }
            @endif

            // Unindexed file upload handlers
            document.getElementById('browse-unindexed-files-btn').addEventListener('click', function() {
                document.getElementById('unindexed-file-upload').click();
            });

            document.getElementById('unindexed-file-upload').addEventListener('change', function(e) {
                handleUnindexedFileSelection(e.target.files);
            });

            function handleUnindexedFileSelection(files) {
                uploadState.selectedFiles = Array.from(files);
                displayUnindexedSelectedFiles();
            }

            function displayUnindexedSelectedFiles() {
                const container = document.getElementById('unindexed-selected-files-container');
                const list = document.getElementById('unindexed-selected-files-list');
                const count = document.getElementById('unindexed-selected-files-count');
                const startBtn = document.getElementById('start-unindexed-processing-btn');

                if (uploadState.selectedFiles.length === 0) {
                    container.classList.add('hidden');
                    startBtn.classList.add('hidden');
                    return;
                }

                container.classList.remove('hidden');
                startBtn.classList.remove('hidden');
                count.textContent = uploadState.selectedFiles.length;

                list.innerHTML = '';
                uploadState.selectedFiles.forEach((file, index) => {
                    const fileItem = document.createElement('div');
                    fileItem.className = 'p-3 flex items-center justify-between';
                    fileItem.innerHTML = `
                        <div class="flex items-center gap-3">
                            <i data-lucide="file-text" class="h-5 w-5 text-gray-400"></i>
                            <div>
                                <div class="font-medium text-sm">${file.name}</div>
                                <div class="text-xs text-gray-500">${formatFileSize(file.size)}</div>
                            </div>
                        </div>
                        <button type="button" class="text-red-500 hover:text-red-700 p-1" onclick="removeUnindexedFile(${index})">
                            <i data-lucide="x" class="h-4 w-4"></i>
                        </button>
                    `;
                    list.appendChild(fileItem);
                });

                lucide.createIcons();
            }

            function removeUnindexedFile(index) {
                uploadState.selectedFiles.splice(index, 1);
                displayUnindexedSelectedFiles();
            }

            // Start unindexed processing
            document.getElementById('start-unindexed-processing-btn').addEventListener('click', function() {
                startUnindexedProcessing();
            });

            function startUnindexedProcessing() {
                if (uploadState.selectedFiles.length === 0) return;

                // Show processing state
                document.getElementById('unindexed-selected-files-container').classList.add('hidden');
                document.getElementById('start-unindexed-processing-btn').classList.add('hidden');
                document.getElementById('unindexed-processing').classList.remove('hidden');
                document.getElementById('cancel-unindexed-processing-btn').classList.remove('hidden');

                // Simulate processing
                let progress = 0;
                const interval = setInterval(() => {
                    progress += 10;
                    document.getElementById('unindexed-processing-percentage').textContent = progress + '%';
                    document.getElementById('unindexed-progress-bar').style.width = progress + '%';
                    document.getElementById('unindexed-processing-count').textContent = Math.ceil((progress / 100) * uploadState.selectedFiles.length);

                    if (progress >= 100) {
                        clearInterval(interval);
                        completeUnindexedProcessing();
                    }
                }, 500);
            }

            function completeUnindexedProcessing() {
                // Hide processing state
                document.getElementById('unindexed-processing').classList.add('hidden');
                document.getElementById('cancel-unindexed-processing-btn').classList.add('hidden');

                // Show complete state
                document.getElementById('unindexed-complete').classList.remove('hidden');
                document.getElementById('process-more-unindexed-btn').classList.remove('hidden');
                document.getElementById('view-processed-files-btn').classList.remove('hidden');

                showNotification('Unindexed files processed successfully! Indexing records have been created.', 'success');
            }

            // Utility functions
            function formatFileSize(bytes) {
                if (bytes === 0) return '0 Bytes';
                const k = 1024;
                const sizes = ['Bytes', 'KB', 'MB', 'GB'];
                const i = Math.floor(Math.log(bytes) / Math.log(k));
                return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
            }

            function showNotification(message, type = 'info') {
                // Create notification element
                const notification = document.createElement('div');
                notification.className = `fixed top-4 right-4 z-50 p-4 rounded-md shadow-lg max-w-sm ${
                    type === 'success' ? 'bg-green-50 border border-green-200 text-green-800' :
                    type === 'error' ? 'bg-red-50 border border-red-200 text-red-800' :
                    'bg-blue-50 border border-blue-200 text-blue-800'
                }`;
                
                notification.innerHTML = `
                    <div class="flex items-center gap-2">
                        <i data-lucide="${type === 'success' ? 'check-circle' : type === 'error' ? 'alert-circle' : 'info'}" class="h-5 w-5"></i>
                        <span class="text-sm font-medium">${message}</span>
                        <button onclick="this.parentElement.parentElement.remove()" class="ml-auto">
                            <i data-lucide="x" class="h-4 w-4"></i>
                        </button>
                    </div>
                `;
                
                document.body.appendChild(notification);
                lucide.createIcons();
                
                // Auto remove after 5 seconds
                setTimeout(() => {
                    if (notification.parentElement) {
                        notification.remove();
                    }
                }, 5000);
            }

            // Clear buttons
            document.getElementById('clear-unindexed-all-btn').addEventListener('click', function() {
                uploadState.selectedFiles = [];
                displayUnindexedSelectedFiles();
            });

            // Process more button
            document.getElementById('process-more-unindexed-btn').addEventListener('click', function() {
                // Reset to initial state
                document.getElementById('unindexed-complete').classList.add('hidden');
                document.getElementById('process-more-unindexed-btn').classList.add('hidden');
                document.getElementById('view-processed-files-btn').classList.add('hidden');
                uploadState.selectedFiles = [];
                displayUnindexedSelectedFiles();
            });
        </script>
        
        <script>
            // Wire up View Processed Files button to reveal analysis results section and scroll
            (function(){
                const viewBtn = document.getElementById('view-processed-files-btn');
                if (viewBtn) {
                    viewBtn.addEventListener('click', function() {
                        const aiDiv = document.getElementById('ai-processing');
                        const results = document.getElementById('analysis-results');
                        if (aiDiv) aiDiv.classList.remove('hidden');
                        if (results) {
                            results.classList.remove('hidden');
                            results.scrollIntoView({ behavior: 'smooth' });
                        }
                    });
                }

                // Override unindexed processing to display OCR modal + analysis results
                const originalStart = window.startUnindexedProcessing;
                window.startUnindexedProcessing = function() {
                    try {
                        // Call original if exists to retain progress UI
                        if (typeof originalStart === 'function') {
                            // Show OCR/AI first
                            const ocrModal = document.getElementById('ocr-modal');
                            const aiDiv = document.getElementById('ai-processing');
                            if (aiDiv) aiDiv.classList.remove('hidden');
                            if (ocrModal) ocrModal.classList.remove('hidden');
                        }
                    } catch (e) {}

                    // Simulate OCR + analysis population until backend wiring is complete
                    let files = (window.uploadState && Array.isArray(uploadState.selectedFiles)) ? uploadState.selectedFiles : [];
                    const total = Math.max(1, files.length);
                    let step = 0;
                    const perStep = Math.max(1, Math.floor(100 / total));

                    const upd = (pct) => {
                        const ocrP = document.getElementById('ocr-progress-percent');
                        const ocrB = document.getElementById('ocr-progress-bar');
                        const aiP = document.getElementById('ai-progress-percent');
                        const aiB = document.getElementById('ai-progress-bar');
                        if (ocrP) ocrP.textContent = pct + '%';
                        if (ocrB) ocrB.style.width = pct + '%';
                        if (aiP) aiP.textContent = pct + '%';
                        if (aiB) aiB.style.width = pct + '%';
                    };

                    const interval = setInterval(() => {
                        step++;
                        const pct = Math.min(100, step * perStep);
                        upd(pct);
                        if (pct >= 100) {
                            clearInterval(interval);
                            // Hide OCR modal
                            const ocrModal = document.getElementById('ocr-modal');
                            if (ocrModal) ocrModal.classList.add('hidden');
                            // Populate simple analysis cards from filenames
                            const resultsContainer = document.getElementById('analysis-results');
                            const metadataResults = document.getElementById('metadata-results');
                            const filesProcessed = document.getElementById('files-processed');
                            if (resultsContainer && metadataResults && filesProcessed) {
                                const items = files.map((file, idx) => ({
                                    originalFileName: file.name,
                                    confidence: 60,
                                    fileNumberFound: false,
                                    extractedFileNumber: '',
                                    ownerFound: false,
                                    detectedOwner: '',
                                    plotNumberFound: false,
                                    plotNumber: '',
                                    landUseFound: false,
                                    landUseType: '',
                                    districtFound: false,
                                    district: ''
                                }));
                                filesProcessed.textContent = `${items.length} files processed`;
                                metadataResults.innerHTML = items.map(x => `
                                    <div class="border rounded-lg overflow-hidden">
                                        <div class="bg-gradient-to-r from-blue-50 to-indigo-50 px-4 py-3 border-b">
                                            <div class="flex justify-between items-center">
                                                <div>
                                                    <h5 class="font-semibold text-gray-900">${x.originalFileName}</h5>
                                                    <p class="text-sm text-gray-600 mt-1">Document processed</p>
                                                </div>
                                                <span class="px-3 py-1 text-sm font-medium rounded-full bg-amber-100 text-amber-800 border-amber-200">${x.confidence}% confidence</span>
                                            </div>
                                        </div>
                                        <div class="p-4">
                                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                                                <div class="space-y-4">
                                                    <h6 class="font-medium text-gray-900 border-b pb-2">File Numbers</h6>
                                                    <div class="bg-gray-50 rounded-lg p-3">
                                                        <div class="flex items-center justify-between mb-2">
                                                            <span class="text-sm font-medium text-gray-700">New File Number (KANGIS)</span>
                                                            <span class="px-2 py-1 text-xs font-medium rounded-full bg-red-50 text-red-700 border-red-200">⚠ Not Found</span>
                                                        </div>
                                                        <div class="text-lg font-mono bg-white p-2 rounded border">No file number detected</div>
                                                    </div>
                                                    <div class="bg-gray-50 rounded-lg p-3">
                                                        <div class="flex items-center justify-between mb-2">
                                                            <span class="text-sm font-medium text-gray-700">Property Owner</span>
                                                            <span class="px-2 py-1 text-xs font-medium rounded-full bg-red-50 text-red-700 border-red-200">⚠ Not Found</span>
                                                        </div>
                                                        <div class="text-lg font-semibold bg-white p-2 rounded border">No owner detected</div>
                                                    </div>
                                                </div>
                                                <div class="space-y-4">
                                                    <h6 class="font-medium text-gray-900 border-b pb-2">Property Information</h6>
                                                    <div class="bg-gray-50 rounded-lg p-3">
                                                        <div class="flex items-center justify-between mb-2">
                                                            <span class="text-sm font-medium text-gray-700">Plot No:</span>
                                                            <span class="px-2 py-1 text-xs font-medium rounded-full bg-red-50 text-red-700 border-red-200">⚠ Not Found</span>
                                                        </div>
                                                        <div class="text-lg bg-white p-2 rounded border">No plot number detected</div>
                                                    </div>
                                                    <div class="bg-gray-50 rounded-lg p-3">
                                                        <div class="flex items-center justify-between mb-2">
                                                            <span class="text-sm font-medium text-gray-700">Land Use Type</span>
                                                            <span class="px-2 py-1 text-xs font-medium rounded-full bg-red-50 text-red-700 border-red-200">⚠ Not Found</span>
                                                        </div>
                                                        <div class="text-lg bg-white p-2 rounded border">No land use detected</div>
                                                    </div>
                                                    <div class="bg-gray-50 rounded-lg p-3">
                                                        <div class="flex items-center justify-between mb-2">
                                                            <span class="text-sm font-medium text-gray-700">District/Location</span>
                                                            <span class="px-2 py-1 text-xs font-medium rounded-full bg-red-50 text-red-700 border-red-200">⚠ Not Found</span>
                                                        </div>
                                                        <div class="text-lg bg-white p-2 rounded border">No district detected</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                `).join('');
                                resultsContainer.classList.remove('hidden');
                            }

                            // Show final complete state controls already in UI
                            const complete = document.getElementById('unindexed-complete');
                            const processMore = document.getElementById('process-more-unindexed-btn');
                            const viewProcessed = document.getElementById('view-processed-files-btn');
                            if (complete) complete.classList.remove('hidden');
                            if (processMore) processMore.classList.remove('hidden');
                            if (viewProcessed) viewProcessed.classList.remove('hidden');
                        }
                    }, 800);
                };
            })();
        </script>

        <script>
            // View file scans function
            function viewFileScans(fileIndexingId) {
                // Redirect to the view page for this FileNo
                window.location.href = `/scanning/${fileIndexingId}`;
            }
        </script>
        
        @include('scanning.assets.js_dynamic')
    </div>
@endsection

