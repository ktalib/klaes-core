@extends('layouts.app')
@section('page-title')
    {{ __('Document Upload') }}
@endsection
@section('content')
    <!-- Main Content -->
    <div class="flex-1 overflow-auto">
        <!-- Header --> 
        @include('admin.header') 
        <!-- Dashboard Content -->
        <div class="p-6">
            @include('scanning.assets.style')
            <div class="container mx-auto py-6 space-y-6">
                <!-- Page Header -->
                <div class="flex flex-col space-y-2">
                    <h1 class="text-2xl font-bold tracking-tight">Upload Indexed Scanned File</h1>
                    <p class="text-muted-foreground">Upload scanned documents to their digital folders</p>
                    
                    @if($selectedFileIndexing)
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mt-4">
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

                <!-- Tabs -->
                <div class="tabs">
                    <div class="tabs-list grid w-full md:w-auto grid-cols-2">
                        <button class="tab active" role="tab" aria-selected="true" data-tab="upload">Upload Indexed Scanned File</button>
                        <button class="tab" role="tab" aria-selected="false" data-tab="scanned-files">Scanned Files</button>
                    </div>

                    <!-- Upload Tab -->
                    <div class="tab-content mt-6 active" role="tabpanel" aria-hidden="false" data-tab-content="upload">
                        <div class="card">
                            <div class="p-6 border-b">
                                <div class="flex flex-col md:flex-row md:items-center justify-between">
                                    <div>
                                        <h2 class="text-lg font-semibold">Upload Indexed Scanned File</h2>
                                        <p class="text-sm text-muted-foreground">Upload scanned documents to their digital folders</p>
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
                                        <label class="text-sm font-medium">Select Indexed File</label>
                                        <button class="btn btn-outline btn-sm gap-1" id="select-file-btn">
                                            <i data-lucide="folder" class="h-4 w-4"></i>
                                            <span id="change-file-text">{{ $selectedFileIndexing ? 'Change File' : 'Select File' }}</span>
                                        </button>
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
                                        <button class="btn btn-outline gap-2 hidden" id="upload-more-btn">
                                            <i data-lucide="refresh-cw" class="h-4 w-4"></i>
                                            Upload More
                                        </button>
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
                                    <div class="overflow-x-auto">
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
                                            <tbody id="scanned-files-list" class="bg-white divide-y divide-gray-200">
                                                @foreach($recentScans as $scan)
                                                    <tr class="hover:bg-gray-50">
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                            {{ $scan->fileIndexing->file_number ?? 'Unknown' }}
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                            {{ $scan->original_filename ?? 'Document' }}
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                            {{ $scan->created_at->format('M d, Y') }}
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap">
                                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                                                {{ $scan->status === 'typed' ? 'bg-green-100 text-green-800' : 
                                                                   ($scan->status === 'scanned' ? 'bg-blue-100 text-blue-800' : 'bg-yellow-100 text-yellow-800') }}">
                                                                {{ ucfirst($scan->status) }}
                                                            </span>
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                            1 page
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                            {{ $scan->uploader->name ?? 'Unknown' }}
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                            <div class="flex items-center space-x-2">
                                                                <button class="text-indigo-600 hover:text-indigo-900" onclick="viewDocument({{ $scan->id }})">
                                                                    <i data-lucide="eye" class="h-4 w-4 mr-1"></i>
                                                                    View
                                                                </button>
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
        @include('scanning.assets.js_dynamic')
    </div>
@endsection