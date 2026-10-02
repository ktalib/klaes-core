@extends('layouts.app')
@section('page-title')
    {{ $PageTitle }}
@endsection
@section('content')
    <!-- Main Content -->
    <div class="flex-1 overflow-auto">
        <!-- Header -->
        @include('admin.header')
        <!-- Dashboard Content -->
        <div class="p-6">
            @include('scanning.assets.style')

            <!-- Preview Libraries -->
            <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
            <script src="https://cdnjs.cloudflare.com/ajax/libs/mammoth/1.6.0/mammoth.browser.min.js"></script>
            <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
            <script src="https://unpkg.com/pdfobject@2.2.8/pdfobject.min.js"></script>
            <script>
                // Configure PDF.js worker
                pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
            </script>

            <style>
                /* Additional styles for view page */
                .scan-card {
                    transition: all 0.2s ease;
                }

                .scan-card:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
                }

                .tab.active {
                    border-bottom: 2px solid #3b82f6;
                    color: #3b82f6;
                }

                .tab-content.active {
                    display: block;
                }

                .scans-grid {
                    display: grid;
                    gap: 1.5rem;
                }

                @media (max-width: 768px) {
                    .scans-grid {
                        grid-template-columns: 1fr;
                    }
                }

                @media (min-width: 768px) {
                    .scans-grid {
                        grid-template-columns: repeat(2, 1fr);
                    }
                }

                @media (min-width: 1024px) {
                    .scans-grid {
                        grid-template-columns: repeat(3, 1fr);
                    }
                }

                /* Enhanced preview styles */
                .scan-card .aspect-\[3\/4\] {
                    cursor: pointer;
                }

                .scan-card .aspect-\[3\/4\]:hover {
                    opacity: 0.9;
                }

                /* PDF viewer styles */
                .pdf-viewer-container {
                    max-width: 100%;
                }

                .pdf-canvas-container {
                    background: #f8f9fa;
                    padding: 1rem;
                    border-radius: 0.5rem;
                }

                #pdf-viewer-canvas {
                    max-width: 100%;
                    height: auto;
                }

                /* Button styles */
                .btn {
                    @apply inline-flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors;
                }

                .btn-primary {
                    @apply bg-blue-600 text-white hover:bg-blue-700;
                }

                .btn-outline {
                    @apply border border-gray-300 bg-white text-gray-700 hover:bg-gray-50;
                }

                .btn-sm {
                    @apply px-2 py-1 text-xs;
                }

                /* Loading animation */
                @keyframes spin {
                    to {
                        transform: rotate(360deg);
                    }
                }

                .animate-spin {
                    animation: spin 1s linear infinite;
                }

                /* Enhanced file type icons */
                .file-type-icon {
                    transition: transform 0.2s ease;
                }

                .file-type-icon:hover {
                    transform: scale(1.1);
                }
            </style>

            <div class="container mx-auto py-6 space-y-6">
                <!-- Page Header -->
                <div class="flex flex-col space-y-4">
                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                        <div>
                            <h1 class="text-2xl font-bold tracking-tight">{{ $PageTitle }}</h1>
                            <p class="text-muted-foreground">{{ $PageDescription }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <a href="{{ route('scanning.index') }}" class="btn btn-outline gap-2">
                                <i data-lucide="arrow-left" class="h-4 w-4"></i>
                                Back to Scanning
                            </a>
                            <!-- <button class="btn btn-primary gap-2" onclick="uploadMoreScans()">
                                    <i data-lucide="plus-circle" class="h-4 w-4"></i>
                                    Upload More
                                </button> -->
                        </div>
                    </div>

                    <!-- File Information Card -->
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <i data-lucide="folder-open" class="h-5 w-5 text-blue-600 mr-3"></i>
                                <div>
                                    <p class="font-medium text-blue-900">{{ $fileIndexing->file_number }}</p>
                                    <p class="text-sm text-blue-700">{{ $fileIndexing->file_title }}</p>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="text-sm text-blue-700">{{ $allScans->count() }}
                                    {{ $allScans->count() == 1 ? 'scan' : 'scans' }}</p>
                                <p class="text-xs text-blue-600">Storage: {{ $folderPath }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tabs -->
                <div class="tabs">
                    <div class="tabs-list grid w-full md:w-auto grid-cols-2">
                        <button class="tab active" role="tab" aria-selected="true" data-tab="scans-list">Scanned
                            Documents</button>
                        <button class="tab" role="tab" aria-selected="false" data-tab="file-manager">File Manager</button>
                    </div>

                    <!-- Scanned Documents Tab -->
                    <div class="tab-content mt-6 active" role="tabpanel" aria-hidden="false" data-tab-content="scans-list">
                        <div class="card">
                            <div class="p-6 border-b">
                                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                                    <div>
                                        <h2 class="text-lg font-semibold">Scanned Documents</h2>
                                        <p class="text-sm text-muted-foreground">All scanned files for
                                            {{ $fileIndexing->file_number }}</p>
                                    </div>
                                    <div class="relative w-full md:w-64">
                                        <i data-lucide="search"
                                            class="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground"></i>
                                        <input type="search" placeholder="Search scans..." class="input w-full pl-8"
                                            id="search-scans">
                                    </div>
                                </div>
                            </div>
                            <div class="p-6">
                                @if($allScans && $allScans->count() > 0)
                                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6" id="scans-grid">
                                        @foreach($allScans as $scan)
                                                                <div class="scan-card border rounded-lg overflow-hidden hover:shadow-md transition-shadow"
                                                                    data-scan-id="{{ $scan->id }}">
                                                                    <!-- Document Preview -->
                                                                    <div class="aspect-[3/4] bg-gray-100 relative cursor-pointer"
                                                                        onclick="viewDocument('/storage/app/public/{{ $scan->document_path }}', '{{ $scan->original_filename }}', '{{ pathinfo($scan->document_path, PATHINFO_EXTENSION) }}')">
                                                                        @if(in_array(strtolower(pathinfo($scan->document_path, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif']))
                                                                            <img src="/storage/app/public/{{ $scan->document_path }}"
                                                                                alt="{{ $scan->original_filename }}" class="w-full h-full object-cover">
                                                                            <div
                                                                                class="absolute inset-0 bg-black bg-opacity-0 hover:bg-opacity-10 transition-all duration-200 flex items-center justify-center">
                                                                                <div class="opacity-0 hover:opacity-100 transition-opacity">
                                                                                    <i data-lucide="zoom-in" class="h-8 w-8 text-white"></i>
                                                                                </div>
                                                                            </div>
                                                                        @elseif(strtolower(pathinfo($scan->document_path, PATHINFO_EXTENSION)) === 'pdf')
                                                                            <div class="w-full h-full bg-red-50 relative" id="pdf-preview-{{ $scan->id }}">
                                                                                <canvas class="w-full h-full object-cover"
                                                                                    id="pdf-canvas-{{ $scan->id }}"></canvas>
                                                                                <div
                                                                                    class="absolute inset-0 bg-black bg-opacity-0 hover:bg-opacity-10 transition-all duration-200 flex items-center justify-center">
                                                                                    <div class="opacity-0 hover:opacity-100 transition-opacity">
                                                                                        <i data-lucide="zoom-in" class="h-8 w-8 text-white"></i>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="absolute inset-0 flex items-center justify-center"
                                                                                    id="pdf-loading-{{ $scan->id }}">
                                                                                    <div class="text-center">
                                                                                        <i data-lucide="file-text"
                                                                                            class="h-12 w-12 text-red-500 mx-auto mb-2"></i>
                                                                                        <p class="text-sm text-red-700 font-medium">Loading PDF...</p>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                            <script>
                                                                                // Load PDF thumbnail for {{ $scan->id }}
                                                                                (function () {
                                                                                    const url = '/storage/app/public/{{ $scan->document_path }}';
                                                                                    const canvas = document.getElementById('pdf-canvas-{{ $scan->id }}');
                                                                                    const loading = document.getElementById('pdf-loading-{{ $scan->id }}');
                                                                                    const context = canvas.getContext('2d');

                                                                                    pdfjsLib.getDocument(url).promise.then(function (pdf) {
                                                                                        pdf.getPage(1).then(function (page) {
                                                                                            const viewport = page.getViewport({ scale: 1 });
                                                                                            const scale = Math.min(300 / viewport.width, 400 / viewport.height);
                                                                                            const scaledViewport = page.getViewport({ scale: scale });

                                                                                            canvas.width = scaledViewport.width;
                                                                                            canvas.height = scaledViewport.height;

                                                                                            page.render({
                                                                                                canvasContext: context,
                                                                                                viewport: scaledViewport
                                                                                            }).promise.then(function () {
                                                                                                loading.style.display = 'none';
                                                                                            });
                                                                                        });
                                                                                    }).catch(function (error) {
                                                                                        console.error('Error loading PDF:', error);
                                                                                        loading.innerHTML = `
                                                                                                            <div class="text-center">
                                                                                                                <i data-lucide="file-text" class="h-12 w-12 text-red-500 mx-auto mb-2"></i>
                                                                                                                <p class="text-sm text-red-700 font-medium">PDF Document</p>
                                                                                                            </div>
                                                                                                        `;
                                                                                    });
                                                                                })();
                                                                            </script>
                                                                        @else
                                                                            @php
                                                                                $extension = strtolower(pathinfo($scan->document_path, PATHINFO_EXTENSION));
                                                                                $isOfficeDoc = in_array($extension, ['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx']);
                                                                                $isTextFile = in_array($extension, ['txt', 'csv', 'xml', 'json']);
                                                                            @endphp

                                                                            <div class="w-full h-full flex items-center justify-center 
                                                                                                {{ $isOfficeDoc ? 'bg-blue-50' : ($isTextFile ? 'bg-green-50' : 'bg-gray-50') }}"
                                                                                id="file-preview-{{ $scan->id }}">
                                                                                <div
                                                                                    class="absolute inset-0 bg-black bg-opacity-0 hover:bg-opacity-10 transition-all duration-200 flex items-center justify-center">
                                                                                    <div class="opacity-0 hover:opacity-100 transition-opacity">
                                                                                        <i data-lucide="zoom-in" class="h-8 w-8 text-white"></i>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="text-center relative z-10">
                                                                                    @if($isOfficeDoc)
                                                                                        @if(in_array($extension, ['doc', 'docx']))
                                                                                            <i data-lucide="file-text"
                                                                                                class="h-12 w-12 text-blue-500 mx-auto mb-2 file-type-icon"></i>
                                                                                            <p class="text-sm text-blue-700 font-medium">Word Document</p>
                                                                                        @elseif(in_array($extension, ['xls', 'xlsx']))
                                                                                            <i data-lucide="sheet"
                                                                                                class="h-12 w-12 text-green-500 mx-auto mb-2 file-type-icon"></i>
                                                                                            <p class="text-sm text-green-700 font-medium">Excel Spreadsheet</p>
                                                                                        @elseif(in_array($extension, ['ppt', 'pptx']))
                                                                                            <i data-lucide="presentation"
                                                                                                class="h-12 w-12 text-orange-500 mx-auto mb-2 file-type-icon"></i>
                                                                                            <p class="text-sm text-orange-700 font-medium">PowerPoint</p>
                                                                                        @endif
                                                                                    @elseif($isTextFile)
                                                                                        <i data-lucide="file-code"
                                                                                            class="h-12 w-12 text-green-500 mx-auto mb-2 file-type-icon"></i>
                                                                                        <p class="text-sm text-green-700 font-medium">
                                                                                            {{ strtoupper($extension) }} File</p>
                                                                                    @else
                                                                                        <i data-lucide="file"
                                                                                            class="h-12 w-12 text-gray-400 mx-auto mb-2 file-type-icon"></i>
                                                                                        <p class="text-sm text-gray-600">{{ strtoupper($extension) }}</p>
                                                                                    @endif
                                                                                    <p class="text-xs text-gray-500 mt-1">Click to preview</p>
                                                                                </div>
                                                                            </div>
                                                                        @endif

                                                                        <!-- Status Badge -->
                                                                        <div class="absolute top-2 right-2">
                                                                            <span
                                                                                class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium 
                                                                                            {{ $scan->status === 'typed' ? 'bg-green-100 text-green-800' :
                                            ($scan->status === 'scanned' ? 'bg-blue-100 text-blue-800' : 'bg-yellow-100 text-yellow-800') }}">
                                                                                {{ ucfirst($scan->status) }}
                                                                            </span>
                                                                        </div>
                                                                    </div>

                                                                    <!-- Document Info -->
                                                                    <div class="p-4">
                                                                        <h3 class="font-medium text-sm mb-2 truncate"
                                                                            title="{{ $scan->original_filename }}">
                                                                            {{ $scan->original_filename }}
                                                                        </h3>
                                                                        <div class="space-y-1 text-xs text-gray-500">
                                                                            <div class="flex justify-between">
                                                                                <span>Type:</span>
                                                                                <span>{{ $scan->document_type }}</span>
                                                                            </div>
                                                                            <div class="flex justify-between">
                                                                                <span>Paper Size:</span>
                                                                                <span>{{ $scan->paper_size }}</span>
                                                                            </div>
                                                                            <div class="flex justify-between">
                                                                                <span>Uploaded:</span>
                                                                                <span>{{ $scan->created_at->format('M d, Y') }}</span>
                                                                            </div>
                                                                            <div class="flex justify-between">
                                                                                <span>By:</span>
                                                                                <span>{{ $scan->uploader->name ?? 'Unknown' }}</span>
                                                                            </div>
                                                                        </div>

                                                                        @if($scan->notes)
                                                                            <div class="mt-2 p-2 bg-gray-50 rounded text-xs">
                                                                                <strong>Notes:</strong> {{ $scan->notes }}
                                                                            </div>
                                                                        @endif

                                                                        <!-- Actions -->
                                                                        <div class="mt-3 flex items-center justify-center">
                                                                            <button
                                                                                class="text-indigo-600 hover:text-indigo-900 text-sm font-medium w-full py-2 px-4 bg-indigo-50 hover:bg-indigo-100 rounded-lg transition-colors"
                                                                                onclick="viewDocument('/storage/app/public/{{ $scan->document_path }}', '{{ $scan->original_filename }}', '{{ pathinfo($scan->document_path, PATHINFO_EXTENSION) }}')">
                                                                                <i data-lucide="eye" class="h-4 w-4 inline mr-1"></i>
                                                                                View Document
                                                                            </button>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                        @endforeach
                                    </div>
                                @else
                                    <div class="text-center py-8">
                                        <i data-lucide="inbox" class="h-12 w-12 mx-auto text-gray-300 mb-4"></i>
                                        <p class="text-gray-500">No scanned documents found for this file</p>
                                        <button class="btn btn-primary mt-4 gap-2" onclick="uploadMoreScans()">
                                            <i data-lucide="plus-circle" class="h-4 w-4"></i>
                                            Upload First Scan
                                        </button>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- File Manager Tab -->
                    <div class="tab-content mt-6 hidden" role="tabpanel" aria-hidden="true" data-tab-content="file-manager">
                        <div class="card">
                            <div class="p-6 border-b">
                                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                                    <div>
                                        <h2 class="text-lg font-semibold">File Manager</h2>
                                        <p class="text-sm text-muted-foreground">Browse and manage files in
                                            {{ $folderPath }}</p>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <button class="btn btn-outline btn-sm gap-2" onclick="refreshFileManager()">
                                            <i data-lucide="refresh-cw" class="h-4 w-4"></i>
                                            Refresh
                                        </button>
                                        <!-- <button class="btn btn-primary btn-sm gap-2" onclick="uploadMoreScans()">
                                                <i data-lucide="upload" class="h-4 w-4"></i>
                                                Upload
                                            </button> -->
                                    </div>
                                </div>
                            </div>
                            <div class="p-6">
                                @if(count($folderFiles) > 0)
                                    <div class="overflow-x-auto">
                                        <table class="min-w-full divide-y divide-gray-200">
                                            <thead class="bg-gray-50">
                                                <tr>
                                                    <th
                                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                        Name</th>
                                                    <th
                                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                        Size</th>
                                                    <th
                                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                        Modified</th>
                                                    <th
                                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                        Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody class="bg-white divide-y divide-gray-200">
                                                @foreach($folderFiles as $file)
                                                    <tr class="hover:bg-gray-50 group">
                                                        <td class="px-6 py-4 whitespace-nowrap">
                                                            <div class="flex items-center">
                                                                @php
                                                                    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                                                                    $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                                                                @endphp

                                                                @if($isImage)
                                                                    <div class="h-10 w-10 flex-shrink-0 mr-3 border rounded overflow-hidden bg-gray-100 cursor-pointer"
                                                                        onclick="viewDocument('{{ $file['url'] }}', '{{ $file['name'] }}')">
                                                                        <img src="{{ $file['url'] }}" alt="thumb"
                                                                            class="h-full w-full object-cover">
                                                                    </div>
                                                                @elseif($ext === 'pdf')
                                                                    <div
                                                                        class="h-10 w-10 flex-shrink-0 mr-3 flex items-center justify-center bg-red-50 rounded text-red-500">
                                                                        <i data-lucide="file-text" class="h-6 w-6"></i>
                                                                    </div>
                                                                @else
                                                                    <div
                                                                        class="h-10 w-10 flex-shrink-0 mr-3 flex items-center justify-center bg-gray-50 rounded text-gray-400">
                                                                        <i data-lucide="file" class="h-6 w-6"></i>
                                                                    </div>
                                                                @endif

                                                                <div>
                                                                    <span
                                                                        class="text-sm font-medium text-gray-900 block">{{ $file['name'] }}</span>
                                                                    @if(strpos($file['name'], '/') !== false)
                                                                        <span
                                                                            class="text-xs text-gray-500 bg-gray-100 px-1.5 py-0.5 rounded-full">
                                                                            {{ dirname($file['name']) }}
                                                                        </span>
                                                                    @endif
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                            {{ formatBytes($file['size']) }}
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                            {{ date('M d, Y H:i', $file['modified']) }}
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                            <div class="flex items-center space-x-2">
                                                                <button
                                                                    class="text-indigo-600 hover:text-indigo-900 inline-flex items-center gap-1 px-3 py-1 bg-indigo-50 hover:bg-indigo-100 rounded transition-colors"
                                                                    onclick="viewDocument('{{ $file['url'] }}', '{{ $file['name'] }}')">
                                                                    <i data-lucide="eye" class="h-4 w-4"></i>
                                                                    <span>View</span>
                                                                </button>
                                                                <a href="{{ $file['url'] }}" download
                                                                    class="text-green-600 hover:text-green-900 inline-flex items-center gap-1 px-3 py-1 bg-green-50 hover:bg-green-100 rounded transition-colors">
                                                                    <i data-lucide="download" class="h-4 w-4"></i>
                                                                    <span>Download</span>
                                                                </a>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @else
                                    <div class="text-center py-8">
                                        <i data-lucide="folder-x" class="h-12 w-12 mx-auto text-gray-300 mb-4"></i>
                                        <p class="text-gray-500">No files found in this folder</p>
                                        <p class="text-sm text-gray-400 mt-1">{{ $folderPath }}</p>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Document Viewer Modal -->
        <div id="document-viewer-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
            <div class="flex items-center justify-center min-h-screen p-4">
                <div class="bg-white rounded-lg max-w-4xl w-full max-h-[90vh] overflow-hidden">
                    <div class="flex items-center justify-between p-4 border-b">
                        <h3 class="text-lg font-semibold" id="document-title">Document Viewer</h3>
                        <button onclick="closeDocumentViewer()" class="text-gray-400 hover:text-gray-600">
                            <i data-lucide="x" class="h-6 w-6"></i>
                        </button>
                    </div>
                    <div class="p-4 max-h-[80vh] overflow-auto">
                        <div id="document-content" class="text-center">
                            <!-- Document content will be loaded here -->
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        @include('admin.footer')

        <script>
            // Initialize Lucide icons
            lucide.createIcons();

            // Tab functionality
            document.querySelectorAll('.tab').forEach(tab => {
                tab.addEventListener('click', function () {
                    const tabName = this.getAttribute('data-tab');

                    // Update tab states
                    document.querySelectorAll('.tab').forEach(t => {
                        t.classList.remove('active');
                        t.setAttribute('aria-selected', 'false');
                    });
                    this.classList.add('active');
                    this.setAttribute('aria-selected', 'true');

                    // Update content states
                    document.querySelectorAll('.tab-content').forEach(content => {
                        content.classList.add('hidden');
                        content.setAttribute('aria-hidden', 'true');
                    });
                    const targetContent = document.querySelector(`[data-tab-content="${tabName}"]`);
                    if (targetContent) {
                        targetContent.classList.remove('hidden');
                        targetContent.setAttribute('aria-hidden', 'false');
                    }
                });
            });

            // Search functionality
            document.getElementById('search-scans').addEventListener('input', function () {
                const searchTerm = this.value.toLowerCase();
                const scanCards = document.querySelectorAll('.scan-card');

                scanCards.forEach(card => {
                    const filename = card.querySelector('h3').textContent.toLowerCase();
                    const documentType = card.querySelector('.space-y-1 span:nth-child(2)').textContent.toLowerCase();

                    if (filename.includes(searchTerm) || documentType.includes(searchTerm)) {
                        card.style.display = 'block';
                    } else {
                        card.style.display = 'none';
                    }
                });
            });

            // Document viewer functions
            function viewDocument(url, filename, extension = null) {
                const modal = document.getElementById('document-viewer-modal');
                const title = document.getElementById('document-title');
                const content = document.getElementById('document-content');

                title.textContent = filename;

                const ext = extension || filename.split('.').pop().toLowerCase();

                // Show loading state
                content.innerHTML = `
                        <div class="text-center py-8">
                            <div class="animate-spin h-8 w-8 border-4 border-blue-500 border-t-transparent rounded-full mx-auto mb-4"></div>
                            <p class="text-gray-600">Loading preview...</p>
                        </div>
                    `;

                modal.classList.remove('hidden');

                if (['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg'].includes(ext)) {
                    // Image files
                    content.innerHTML = `
                            <div class="text-center">
                                <img src="${url}" alt="${filename}" class="max-w-full h-auto rounded-lg shadow-lg" 
                                     onload="this.classList.add('opacity-100')" 
                                     class="opacity-0 transition-opacity duration-300">
                            </div>
                        `;
                } else if (ext === 'pdf') {
                    // PDF files with enhanced viewer
                    content.innerHTML = `
                            <div class="pdf-viewer-container">
                                <div class="flex justify-between items-center mb-4 p-2 bg-gray-50 rounded">
                                    <div class="flex items-center gap-2">
                                        <button onclick="prevPage()" class="btn btn-sm btn-outline" id="prev-btn">
                                            <i data-lucide="chevron-left" class="h-4 w-4"></i>
                                        </button>
                                        <span id="page-info">Page 1 of 1</span>
                                        <button onclick="nextPage()" class="btn btn-sm btn-outline" id="next-btn">
                                            <i data-lucide="chevron-right" class="h-4 w-4"></i>
                                        </button>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <button onclick="zoomOut()" class="btn btn-sm btn-outline">
                                            <i data-lucide="zoom-out" class="h-4 w-4"></i>
                                        </button>
                                        <span id="zoom-info">100%</span>
                                        <button onclick="zoomIn()" class="btn btn-sm btn-outline">
                                            <i data-lucide="zoom-in" class="h-4 w-4"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="pdf-canvas-container text-center" style="max-height: 500px; overflow: auto;">
                                    <canvas id="pdf-viewer-canvas" class="border rounded shadow-sm"></canvas>
                                </div>
                            </div>
                        `;
                    loadPDFViewer(url);
                } else if (['doc', 'docx'].includes(ext)) {
                    // Word documents
                    content.innerHTML = `
                            <div class="text-center py-8">
                                <i data-lucide="file-text" class="h-16 w-16 mx-auto text-blue-500 mb-4"></i>
                                <p class="text-gray-600 mb-4">Microsoft Word Document</p>
                                <p class="text-sm text-gray-500 mb-4">Preview not available for this file type</p>
                                <a href="${url}" download class="btn btn-primary gap-2">
                                    <i data-lucide="download" class="h-4 w-4"></i>
                                    Download File
                                </a>
                            </div>
                        `;
                } else if (['xls', 'xlsx'].includes(ext)) {
                    // Excel files
                    content.innerHTML = `
                            <div class="text-center py-8">
                                <i data-lucide="sheet" class="h-16 w-16 mx-auto text-green-500 mb-4"></i>
                                <p class="text-gray-600 mb-4">Excel Spreadsheet</p>
                                <p class="text-sm text-gray-500 mb-4">Preview not available for this file type</p>
                                <a href="${url}" download class="btn btn-primary gap-2">
                                    <i data-lucide="download" class="h-4 w-4"></i>
                                    Download File
                                </a>
                            </div>
                        `;
                } else if (['ppt', 'pptx'].includes(ext)) {
                    // PowerPoint files
                    content.innerHTML = `
                            <div class="text-center py-8">
                                <i data-lucide="presentation" class="h-16 w-16 mx-auto text-orange-500 mb-4"></i>
                                <p class="text-gray-600 mb-4">PowerPoint Presentation</p>
                                <p class="text-sm text-gray-500 mb-4">Preview not available for this file type</p>
                                <a href="${url}" download class="btn btn-primary gap-2">
                                    <i data-lucide="download" class="h-4 w-4"></i>
                                    Download File
                                </a>
                            </div>
                        `;
                } else if (['txt', 'csv', 'xml', 'json', 'html', 'css', 'js'].includes(ext)) {
                    // Text-based files
                    fetch(url)
                        .then(response => response.text())
                        .then(text => {
                            content.innerHTML = `
                                    <div class="text-left">
                                        <div class="bg-gray-50 p-4 rounded-lg mb-4">
                                            <h4 class="font-semibold mb-2">File Content Preview</h4>
                                            <p class="text-sm text-gray-600">File type: ${ext.toUpperCase()}</p>
                                        </div>
                                        <pre class="bg-gray-900 text-green-400 p-4 rounded-lg overflow-auto max-h-96 text-sm font-mono">${text.substring(0, 5000)}${text.length > 5000 ? '\n\n... (Content truncated for preview)' : ''}</pre>
                                    </div>
                                `;
                        })
                        .catch(error => {
                            content.innerHTML = `
                                    <div class="text-center py-8">
                                        <i data-lucide="file-text" class="h-16 w-16 mx-auto text-gray-400 mb-4"></i>
                                        <p class="text-gray-600 mb-4">Could not load text content</p>
                                        <a href="${url}" download class="btn btn-primary gap-2">
                                            <i data-lucide="download" class="h-4 w-4"></i>
                                            Download File
                                        </a>
                                    </div>
                                `;
                        });
                } else {
                    // Unsupported file types
                    content.innerHTML = `
                            <div class="text-center py-8">
                                <i data-lucide="file" class="h-16 w-16 mx-auto text-gray-400 mb-4"></i>
                                <p class="text-gray-600 mb-4">Cannot preview this file type (${ext.toUpperCase()})</p>
                                <p class="text-sm text-gray-500 mb-4">Download the file to view its contents</p>
                                <a href="${url}" download class="btn btn-primary gap-2">
                                    <i data-lucide="download" class="h-4 w-4"></i>
                                    Download File
                                </a>
                            </div>
                        `;
                }

                lucide.createIcons();
            }

            function closeDocumentViewer() {
                document.getElementById('document-viewer-modal').classList.add('hidden');
            }

            // PDF Viewer functionality
            let pdfDoc = null;
            let pageNum = 1;
            let pageRendering = false;
            let pageNumPending = null;
            let scale = 1.0;
            let canvas = null;
            let ctx = null;

            function loadPDFViewer(url) {
                canvas = document.getElementById('pdf-viewer-canvas');
                ctx = canvas.getContext('2d');

                pdfjsLib.getDocument(url).promise.then(function (pdfDoc_) {
                    pdfDoc = pdfDoc_;
                    document.getElementById('page-info').textContent = `Page ${pageNum} of ${pdfDoc.numPages}`;

                    // Initial page render
                    renderPage(pageNum);
                }).catch(function (error) {
                    console.error('Error loading PDF:', error);
                    document.getElementById('pdf-viewer-canvas').parentElement.innerHTML = `
                            <div class="text-center py-8">
                                <i data-lucide="alert-circle" class="h-16 w-16 mx-auto text-red-500 mb-4"></i>
                                <p class="text-red-600 mb-4">Error loading PDF</p>
                                <a href="${url}" download class="btn btn-primary gap-2">
                                    <i data-lucide="download" class="h-4 w-4"></i>
                                    Download File
                                </a>
                            </div>
                        `;
                });
            }

            function renderPage(num) {
                pageRendering = true;
                pdfDoc.getPage(num).then(function (page) {
                    const viewport = page.getViewport({ scale: scale });
                    canvas.height = viewport.height;
                    canvas.width = viewport.width;

                    const renderContext = {
                        canvasContext: ctx,
                        viewport: viewport
                    };

                    const renderTask = page.render(renderContext);
                    renderTask.promise.then(function () {
                        pageRendering = false;
                        if (pageNumPending !== null) {
                            renderPage(pageNumPending);
                            pageNumPending = null;
                        }
                    });
                });

                document.getElementById('page-info').textContent = `Page ${num} of ${pdfDoc.numPages}`;
            }

            function queueRenderPage(num) {
                if (pageRendering) {
                    pageNumPending = num;
                } else {
                    renderPage(num);
                }
            }

            function prevPage() {
                if (pageNum <= 1) {
                    return;
                }
                pageNum--;
                queueRenderPage(pageNum);
            }

            function nextPage() {
                if (pageNum >= pdfDoc.numPages) {
                    return;
                }
                pageNum++;
                queueRenderPage(pageNum);
            }

            function zoomIn() {
                scale += 0.25;
                document.getElementById('zoom-info').textContent = Math.round(scale * 100) + '%';
                queueRenderPage(pageNum);
            }

            function zoomOut() {
                if (scale <= 0.25) {
                    return;
                }
                scale -= 0.25;
                document.getElementById('zoom-info').textContent = Math.round(scale * 100) + '%';
                queueRenderPage(pageNum);
            }

            // Upload more scans
            function uploadMoreScans() {
                window.location.href = `/scanning?file_indexing_id={{ $fileIndexing->id }}`;
            }

            // Refresh file manager
            function refreshFileManager() {
                window.location.reload();
            }

            // Close modal on escape key
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') {
                    closeDocumentViewer();
                }
            });

            // Close modal on backdrop click
            document.getElementById('document-viewer-modal').addEventListener('click', function (e) {
                if (e.target === this) {
                    closeDocumentViewer();
                }
            });
        </script>
    </div>
@endsection

@php
    function formatBytes($bytes, $precision = 2)
    {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');

        for ($i = 0; $bytes > 1024; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }
@endphp