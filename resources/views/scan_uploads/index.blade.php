@extends('layouts.app')

@section('page-title')
  {{ __($PageTitle ?? 'Scan Uploads') }}
@endsection
 
@section('content')
  @include('scan_uploads.assets.style')
  <div class="flex-1 overflow-auto" data-scan-upload-root data-log-endpoint="{{ route('scan-uploads.log') }}"
    data-upload-endpoint="{{ route('scan-uploads.upload') }}"
    data-reorder-endpoint="{{ route('scan-uploads.reorder') }}"
    data-delete-endpoint="{{ route('scan-uploads.destroy', ['scan' => 'ID']) }}"
    data-download-endpoint="{{ route('scan-uploads.download', ['scan' => 'ID']) }}"
    data-debug-endpoint="{{ route('scan-uploads.debug') }}"
    data-apply-endpoint="{{ route('scan-uploads.apply-edits', ['scan' => 'ID']) }}"
    data-blind-discover-endpoint="{{ route('scan-uploads.blind-scan.discover') }}"
    data-blind-transfer-endpoint="{{ route('scan-uploads.blind-scan.transfer') }}"
    data-scan-uploads='@json($payload["uploads"] ?? [])'
    data-current-page="{{ $payload['pagination']['current_page'] ?? 1 }}"
    data-next-page="{{ $payload['pagination']['next_page'] ?? '' }}"
    data-prev-page="{{ $payload['pagination']['prev_page'] ?? '' }}"
    data-page-query="{{ $payload['pagination']['page_query'] ?? 'pag_next' }}">
    {{-- --}}
    @include('admin.header')
    
    @include('scan_uploads.assets.templates')

    <div class="p-6 bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen">
      <div class="container mx-auto space-y-8">

        <div class="flex flex-col space-y-2">
          <div class="flex items-center gap-3">
            <div class="p-3 bg-gradient-to-br from-blue-500 to-blue-600 rounded-lg">
              <i data-lucide="upload-cloud" class="h-6 w-6 text-white"></i>
            </div>
            <div>
              <h1 class="text-3xl font-bold tracking-tight text-gray-900">Document Upload</h1>
              <p class="text-gray-600 mt-1">Manage scanned documents and organize files</p>
            </div>
          </div>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
          <!-- Today's Uploads -->
          <div class="card bg-white">
            <div class="p-6">
              <div class="flex items-start justify-between">
                <div>
                  <p class="text-sm font-semibold text-gray-600 uppercase tracking-wide">Today's Uploads</p>
                  <p class="text-3xl font-bold text-gray-900 mt-2">
                      @php
    $totalToday = DB::connection('sqlsrv')
        ->table('scannings')
        ->whereDate('created_at', now()->today())
        ->distinct()
        ->count('file_indexing_id');
    
    echo $totalToday;
@endphp

                  </p>

                   <p class="text-3xl font-bold text-gray-900 mt-2 hidden" id="uploads-count">
                    {{ $payload['stats']['today_uploads'] ?? 0 }}
                  </p>


                  <p class="text-xs text-gray-500 mt-3">Files uploaded today</p>
                </div>
                <div class="p-3 bg-blue-100 rounded-lg">
                  <i data-lucide="upload" class="h-6 w-6 text-blue-600"></i>
                </div>
              </div>
            </div>
          </div>

          <!-- Pending Page Typing -->
          <div class="card bg-white">
            <div class="p-6">
              <div class="flex items-start justify-between">
                <div>
                  <p class="text-sm font-semibold text-gray-600 uppercase tracking-wide">Pending Page Typing</p>
                  <p class="text-3xl font-bold text-gray-900 mt-2" id="pending-count">
                    {{ $payload['stats']['pending_page_typing'] ?? 0 }}
                  </p>
                  <p class="text-xs text-gray-500 mt-3">Documents waiting for processing</p>
                </div>
                <div class="p-3 bg-amber-100 rounded-lg">
                  <i data-lucide="clock" class="h-6 w-6 text-amber-600"></i>
                </div>
              </div>
            </div>
          </div>

          <!-- Total Scanned -->
          <div class="card bg-white">
            <div class="p-6">
              <div class="flex items-start justify-between">
                <div>
                  <p class="text-sm font-semibold text-gray-600 uppercase tracking-wide">Total Scanned</p>
                  <div class="flex items-center gap-3 mt-2">
                    <p class="text-3xl font-bold text-gray-900">{{ $payload['stats']['total_scanned'] ?? 0 }}</p>
                    <span class="badge badge-success">All Time</span>
                  </div>
                  <p class="text-xs text-gray-500 mt-3">Total documents in system</p>
                </div>
                <div class="p-3 bg-green-100 rounded-lg">
                  <i data-lucide="file-check" class="h-6 w-6 text-green-600"></i>
                </div>
              </div>
            </div>
          </div>

          <!-- All Time Documents -->
          <div class="card bg-white">
            <div class="p-6">
              <div class="flex items-start justify-between">
                <div>
                  <p class="text-sm font-semibold text-gray-600 uppercase tracking-wide">All Time Documents</p>
                  <p class="text-3xl font-bold text-gray-900 mt-2" id="all-time-count">
                    {{ $payload['stats']['all_time_documents'] ?? 0 }}
                  </p>
                  <p class="text-xs text-gray-500 mt-3">Lifetime uploads captured in the system</p>
                </div>
                <div class="p-3 bg-slate-100 rounded-lg">
                  <i data-lucide="infinity" class="h-6 w-6 text-slate-600"></i>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Tabs -->
        <div class="tabs">
          <div class="tabs-list grid w-full md:w-auto grid-cols-2">
            <button class="tab" role="tab" aria-selected="true" data-tab="upload">
              <i data-lucide="cloud-upload" class="h-4 w-4 inline mr-2"></i>
              Upload Documents
            </button>
            <button class="tab" role="tab" aria-selected="false" data-tab="uploaded-files">
              <i data-lucide="file-stack" class="h-4 w-4 inline mr-2"></i>
              Uploaded Documents
            </button>
          </div>

          <!-- Upload Tab -->
          <div class="tab-content mt-6" role="tabpanel" aria-hidden="false" data-tab-content="upload">
            <div class="card bg-white">
              <div class="p-6 border-b border-gray-200">
                <div class="flex flex-col md:flex-row md:items-center justify-between">
                  <div>
                    <h2 class="text-xl font-bold text-gray-900">Upload New Documents</h2>
                    <p class="text-sm text-gray-600 mt-1">Select a file and upload your scanned documents</p>
                  </div>
                  <div class="mt-4 md:mt-0 selected-file-badge hidden">
                    <span class="badge badge-primary px-4 py-2 text-base flex items-center gap-2">
                      <i data-lucide="check-circle-2" class="h-5 w-5"></i>
                      <span id="selected-file-number">No file selected</span>
                    </span>
                  </div>
                </div>
              </div>
              <div class="p-6">
                <div class="space-y-6">
                  <div class="flex justify-between items-center bg-gray-50 p-4 rounded-lg">
                    <label class="text-sm font-semibold text-gray-700">Select Indexed File</label>
                    <button class="btn btn-outline btn-sm gap-2" id="select-file-btn">
                      <i data-lucide="folder-open" class="h-4 w-4"></i>
                      <span id="change-file-text">Select File</span> 
                    </button>
                  </div>

                  <!-- Upload area -->
                  <div
                    class="border-2 border-dashed border-gray-300 rounded-lg p-6 bg-white hover:bg-gray-50 transition-colors drop-zone">
                    <h3 class="text-sm font-semibold text-gray-900 mb-4 flex items-center gap-2">
                      <i data-lucide="cloud-upload" class="h-5 w-5 text-blue-600 upload-icon"></i>
                      Upload Scanned Documents
                    </h3>

                    <!-- PDF Conversion Option -->
                    <div class="mb-4 p-4 bg-blue-50 border-l-4 border-blue-500 rounded-md">
                      <div class="flex items-center space-x-3 mb-2">
                        <input type="checkbox" id="convertPdfs" class="rounded border-gray-300" checked>
                        <label for="convertPdfs" class="text-sm font-semibold text-gray-900">
                          Convert PDF files to images (client-side)
                          <span id="autoConvertBadge" class="pdf-conversion-badge">Auto-activated</span>
                        </label>
                      </div>
                      <p class="text-sm text-gray-700 ml-8">
                        PDF files will be automatically converted to JPG images in your browser before upload.
                      </p>
                      <div class="flex flex-wrap items-center gap-3 text-xs text-blue-700 mt-2 ml-8"
                        id="pdfConversionInfo">
                        <span id="pdfConversionText">PDF conversion ready</span>
                        <button id="convert-pdfs-btn" type="button"
                          class="hidden inline-flex items-center gap-1 rounded-md bg-blue-600 px-3 py-1 text-white font-semibold tracking-wide uppercase text-[10px] shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:ring-offset-1">
                          <i data-lucide="refresh-cw" class="h-3 w-3"></i>
                          Convert PDFs Now
                        </button>
                      </div>
                    </div>

                    <!-- Idle state -->
                    <div id="upload-idle"
                      class="rounded-lg border-2 border-dashed border-gray-300 p-8 text-center bg-gradient-to-br from-gray-50 to-white">
                      <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-blue-100">
                        <i data-lucide="file-up" class="h-7 w-7 text-blue-600"></i>
                      </div>
                      <h3 class="mb-2 text-lg font-semibold text-gray-900">Drag and drop scanned documents here</h3>
                      <p class="mb-4 text-sm text-gray-600">or click to browse files on your computer</p>
                      <input type="file" multiple accept=".pdf,.jpg,.jpeg,.png,.gif,.bmp,.tiff,.webp" class="hidden"
                        id="file-upload">
                      <button class="btn btn-primary gap-2" id="browse-files-btn" disabled>
                        <i data-lucide="upload" class="h-4 w-4"></i>
                        Browse Files
                      </button>
                      <p class="mt-3 text-sm text-red-600 font-semibold" id="select-file-warning">Please select an indexed
                        file first</p>
                      <p class="text-xs text-gray-500 mt-3">
                        Supported: PDF, JPG, PNG, GIF, BMP, TIFF, WebP
                      </p>
                    </div>

                    <!-- Uploading state -->
                    <div id="upload-progress" class="space-y-3 mt-4 hidden">
                      <div class="flex justify-between text-sm font-semibold">
                        <span class="text-gray-700">Uploading <span id="uploading-count">0</span> files...</span>
                        <span class="text-blue-600" id="upload-percentage">0%</span>
                      </div>
                      <div class="progress">
                        <div class="progress-bar" id="progress-bar" style="width: 0%"></div>
                      </div>
                    </div>

                    <!-- Complete state -->
                    <div id="upload-complete" class="mt-4 p-4 bg-green-50 border-l-4 border-green-500 rounded-md hidden">
                      <div class="flex items-center gap-2 text-green-800 font-semibold">
                        <i data-lucide="check-circle" class="h-6 w-6"></i>
                        <span>Upload Complete!</span>
                      </div>
                      <p class="text-sm text-green-700 mt-2 ml-8">
                        Files have been successfully uploaded and organized by paper size.
                      </p>
                    </div>

                    <!-- Error state -->
                    <div id="upload-error" class="mt-4 p-4 bg-red-50 border-l-4 border-red-500 rounded-md hidden">
                      <div class="flex items-center gap-2 text-red-800 font-semibold">
                        <i data-lucide="alert-circle" class="h-6 w-6"></i>
                        <span>Upload Failed!</span>
                      </div>
                      <p class="text-sm text-red-700 mt-2 ml-8" id="upload-error-message">
                        Some files failed to upload. Check console for details.
                      </p>
                    </div>

                    <!-- Selected files list (Grid View) -->
                    <div id="selected-files-container" class="rounded-lg border border-gray-200 mt-4 hidden bg-white">
                      <div class="p-4 bg-gray-50 flex justify-between items-center rounded-t-lg border-b">
                        <span class="font-semibold text-gray-900"><i data-lucide="check-circle-2"
                            class="h-5 w-5 inline text-green-600 mr-2"></i><span id="selected-files-count">0</span> files
                          selected</span>
                        <div class="flex items-center gap-2">
                          <button type="button" class="btn btn-outline btn-sm gap-1" id="preview-selected-files-btn">
                            <i data-lucide="eye" class="h-4 w-4"></i>
                            Preview
                          </button>
                          <button class="btn btn-outline btn-sm text-red-600 hover:bg-red-50" id="clear-all-btn">Clear
                            All</button>
                        </div>
                      </div>

                      <!-- Grid container for file cards -->
                      <div id="selected-files-list" class="p-6 grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                        <!-- Files will be added here dynamically as cards -->
                      </div>
                    </div>
                  </div>

                  <!-- Action buttons -->
                  <div class="flex flex-col md:flex-row gap-3 justify-center pt-6">
                    <!-- Start upload button (idle state) -->
                    <button class="btn btn-primary gap-2 hidden" id="start-upload-btn">
                      <i data-lucide="upload" class="h-4 w-4"></i>
                      Start Upload
                    </button>

                    <!-- Cancel button (uploading state) -->
                    <button class="btn btn-destructive gap-2 hidden" id="cancel-upload-btn">
                      <i data-lucide="x-circle" class="h-4 w-4"></i>
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
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Uploaded Files Tab -->
          <div class="tab-content mt-6" role="tabpanel" aria-hidden="true" data-tab-content="uploaded-files">
            <div class="card bg-white">
              <div class="p-6 border-b border-gray-200">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                  <div>
                    <h2 class="text-xl font-bold text-gray-900">Uploaded Documents</h2>
                    <p class="text-sm text-gray-600 mt-1">Documents ready for page typing and processing</p>
                  </div>
                  <div class="flex flex-col md:flex-row items-end md:items-center gap-3">
                    <div class="flex items-center gap-2 bg-gray-50 px-4 py-2 rounded-lg">
                      <label for="paper-size-filter"
                        class="text-sm font-semibold text-gray-700 whitespace-nowrap">Filter:</label>
                      <select id="paper-size-filter" class="input w-[130px] bg-white text-sm">
                        <option value="All">All Sizes</option>
                        <option value="A4">A4</option>
                        <option value="A5">A5</option>
                        <option value="A3">A3</option>
                        <option value="Letter">Letter</option>
                        <option value="Legal">Legal</option>
                        <option value="Custom">Custom</option>
                      </select>
                    </div>
                    <div class="relative w-full md:w-72">
                      <i data-lucide="search" class="absolute left-3 top-3 h-4 w-4 text-gray-400"></i>
                      <input type="search" placeholder="Search files..." class="input w-full pl-10 text-sm"
                        id="file-search">
                    </div>
                    <button class="btn btn-outline btn-sm whitespace-nowrap gap-2" id="toggle-view-btn">
                      <i data-lucide="layout-grid" class="h-4 w-4"></i>
                      Toggle View
                    </button>
                  </div>
                </div>
              </div>
              <div class="p-6">
                <!-- Empty state -->
                <div id="no-documents"
                  class="rounded-lg border-2 border-dashed border-gray-300 p-12 text-center bg-gradient-to-br from-gray-50 to-white">
                  <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-gray-100">
                    <i data-lucide="file-text" class="h-7 w-7 text-gray-500"></i>
                  </div>
                  <h3 class="mb-2 text-lg font-semibold text-gray-900">No uploaded documents yet</h3>
                  <p class="mb-6 text-sm text-gray-600">Upload documents to see them listed here</p>
                  <button class="btn btn-primary gap-2" id="go-to-upload-btn">
                    <i data-lucide="upload" class="h-4 w-4"></i>
                    Go to Upload Tab
                  </button>
                </div>

                <!-- List view -->
                <div id="list-view" class="rounded-lg border border-gray-200 hidden overflow-visible">
                  <!-- Batches will be added here dynamically -->
                </div>

                <!-- Folder view -->
                <div id="folder-view" class="space-y-6 hidden">
                  <!-- Folders will be added here dynamically -->
                </div>

                <div id="folder-pagination" class="hidden mt-4">
                  <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <span id="folder-pagination-summary" class="text-sm text-gray-600"></span>
                    <div class="flex items-center gap-2">
                      <button type="button" class="btn btn-outline btn-sm gap-2" data-folder-page="prev">
                        <i data-lucide="chevron-left" class="h-4 w-4"></i>
                        Previous
                      </button>
                      <button type="button" class="btn btn-primary btn-sm gap-2" data-folder-page="next">
                        Next
                        <i data-lucide="chevron-right" class="h-4 w-4"></i>
                      </button>
                    </div>
                  </div>
                </div>

                @isset($uploadsPaginator)
                  @php
                    $currentPage = $uploadsPaginator->currentPage();
                    $nextPageNumber = $uploadsPaginator->hasMorePages() ? $currentPage + 1 : null;
                    $prevPageNumber = $currentPage > 1 ? $currentPage - 1 : null;
                    $activeSearch = request()->query('search', '');
                  @endphp

                  @if($activeSearch)
                    <div class="mt-4 mb-2 flex items-center gap-2 px-3 py-2 bg-blue-50 border border-blue-200 rounded-lg">
                      <i data-lucide="search" class="h-4 w-4 text-blue-600"></i>
                      <span class="text-sm text-blue-800">
                        Showing results for <strong>"{{ e($activeSearch) }}"</strong>
                        &mdash; {{ $uploadsPaginator->total() }} {{ Str::plural('result', $uploadsPaginator->total()) }} found
                      </span>
                      <a href="{{ url()->current() }}?tab=uploaded-files&view={{ request()->query('view', 'list') }}"
                         class="ml-auto btn btn-outline btn-sm gap-1 text-blue-700 border-blue-300 hover:bg-blue-100">
                        <i data-lucide="x" class="h-3 w-3"></i>
                        Clear Search
                      </a>
                    </div>
                  @endif

                  <div id="uploaded-documents-pagination"
                    class="mt-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div class="text-sm text-gray-600">
                      @if($uploadsPaginator->total() > 0)
                        Showing {{ $uploadsPaginator->firstItem() ?? 0 }}-{{ $uploadsPaginator->lastItem() ?? 0 }} of
                        {{ $uploadsPaginator->total() }} files (20 per page)
                      @else
                        No uploaded documents found.
                      @endif
                    </div>
                    <div class="flex items-center gap-2">
                      <button type="button" class="btn btn-outline btn-sm gap-2" data-pagination-btn="prev"
                        data-page-number="{{ $prevPageNumber ?? '' }}" {{ $prevPageNumber ? '' : 'disabled' }}>
                        <i data-lucide="chevron-left" class="h-4 w-4"></i>
                        Previous
                      </button>
                      <button type="button" class="btn btn-primary btn-sm gap-2" data-pagination-btn="next"
                        data-page-number="{{ $nextPageNumber ?? '' }}" {{ $nextPageNumber ? '' : 'disabled' }}>
                        Next
                        <i data-lucide="chevron-right" class="h-4 w-4"></i>
                      </button>
                    </div>
                  </div>
                @endisset
              </div>
              <!-- Batch Actions -->
              <div id="batch-actions" class="justify-end border-t border-gray-200 pt-4 p-6 hidden bg-gray-50">
                <button class="btn btn-primary gap-2" id="proceed-to-typing-btn">
                  <i data-lucide="arrow-right" class="h-4 w-4"></i>
                  Proceed to Page Typing
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- File Selector Dialog -->
        <div id="file-selector-dialog" class="dialog-backdrop hidden" aria-hidden="true">
          <div class="dialog-content dialog-file-selector animate-fade-in">
            <div class="p-6 border-b border-gray-200">
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                  <div class="p-2 bg-blue-100 rounded-lg">
                    <i data-lucide="folder-search" class="h-5 w-5 text-blue-600"></i>
                  </div>
                  <div>
                    <h2 class="text-lg font-bold text-gray-900">Select Indexed File</h2>
                    <p class="text-xs text-gray-500 mt-1">Click on a file to select it</p>
                  </div>
                </div>
                <!-- Selected file indicator -->
                <div id="selected-file-badge" class="hidden px-4 py-2 bg-green-50 border border-green-300 rounded-lg">
                  <div class="flex items-center gap-2">
                    <i data-lucide="check-circle-2" class="h-5 w-5 text-green-600"></i>
                    <div>
                      <p class="text-xs font-semibold text-green-900">Selected</p>
                      <p class="text-xs text-green-700" id="selected-file-name">-</p>
                      <p class="text-xs text-green-700 hidden" id="selected-upload-method">-</p>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div class="py-4 px-6 space-y-4">
              {{-- Filters on the left, the list they drive on the right; stacks
                   back to one column below lg. --}}
              <div class="grid gap-4 lg:grid-cols-2 lg:items-start">
              <div class="space-y-4">
              <!-- Registry Selection (Always visible) -->
              <div id="registry-selection-container">
                <label for="blind-scan-registry" class="block text-sm font-semibold text-gray-700 mb-2">Select Registry
                  <span class="text-red-500">*</span></label>
                <select id="blind-scan-registry" class="input w-full text-sm" required>
                  <option value="" selected>Select Registry</option>
                  <option value="Lands Registry">Lands Registry</option>
                  <option value="Cadastral Registry">Cadastral Registry</option>
                  <option value="DCIV Registry">DCIV Registry</option>
                  <option value="Secret Registry">Secret Registry</option>
                  <option value="KANGIS Registry">KANGIS Registry</option>
                  <option value="SLTR Registry">SLTR Registry</option>
                  <option value="ST Registry">ST Registry</option>
                  <option value="Deeds Registry">Deeds Registry</option>
                </select>
                <p class="text-xs text-gray-500 mt-1">Select the registry to proceed.</p>
              </div>

              <div class="border border-gray-200 rounded-lg p-4 bg-blue-50/50 mb-4">
                <label for="global-selected-fileno-select" class="block text-sm font-semibold text-blue-900 mb-2">Search via smart file number Selector</label>
                <select id="global-selected-fileno-select" class="input w-full text-sm bg-blue-50 border-blue-200 text-blue-900 font-mono font-bold" disabled>
                  <option value="">Select registry first...</option>
                </select>
              </div>

              </div>

              <div class="rounded-lg border border-gray-200 divide-y max-h-[400px] lg:max-h-[420px] overflow-y-auto"
                id="indexed-files-list">
                <div class="p-4 text-sm text-gray-500">
                  <div class="flex items-center gap-2">
                    <div class="loading-spinner"></div>
                    Loading indexed files...
                  </div>
                </div>
              </div>
              </div>
              <div class="grid gap-4 lg:grid-cols-2 lg:items-start">
              <div class="border border-gray-200 rounded-lg p-4 space-y-3">
                <div>
                  <p class="text-sm font-semibold text-gray-800">Upload Method</p>
                  <p class="text-xs text-gray-500">Choose how these documents will be added to the file.</p>
                </div>
                <div class="grid gap-3 sm:grid-cols-2" id="upload-method-options">
                   <label for="upload-method-blind"
                    class="flex items-start gap-3 rounded-lg border border-gray-200 p-3 hover:border-blue-400 cursor-pointer transition-colors">
                    <input type="radio" name="upload-method" id="upload-method-blind" value="blind"
                      class="mt-1 h-4 w-4 text-blue-600 focus:ring-blue-500">
                    <div>
                      <p class="scan-source-title text-sm font-semibold flex items-center gap-2"><i data-lucide="scan-line" class="h-4 w-4 shrink-0" aria-hidden="true"></i>Blind scan</p>
                      <p class="text-xs text-gray-500">Browse documents from the normal scanning folder.</p>
                    </div>
                  </label>
                  
                  <label for="upload-method-direct"
                    class="flex items-start gap-3 rounded-lg border border-gray-200 p-3 hover:border-blue-400 cursor-pointer transition-colors">
                    <input type="radio" name="upload-method" id="upload-method-direct" value="direct"
                      class="mt-1 h-4 w-4 text-blue-600 focus:ring-blue-500" >
                    <div>
                      <p class="scan-source-title text-sm font-semibold flex items-center gap-2"><i data-lucide="upload" class="h-4 w-4 shrink-0" aria-hidden="true"></i>Direct upload</p>
                      <p class="text-xs text-gray-500">Documents are added from this workstation.</p>
                    </div>
                  </label>
                 
                </div>
                <div class="border-t border-gray-200 pt-3 space-y-2">
                  <button type="button" class="btn btn-outline btn-sm w-full gap-2" id="browse-large-format-scans" disabled><i data-lucide="image" class="h-4 w-4 shrink-0" aria-hidden="true"></i>Browse Large-Format Scans</button>
                  <p class="text-xs text-gray-500" style="overflow-wrap:anywhere">Master LFS Folder: {{ config('large_format_scans.libraries.lfs.display', config('large_format_scans.folder')) }}</p>
                  <p class="text-xs text-gray-500" id="lf-scan-status" aria-live="polite">Select an indexed file first. LF images are added to its upload pages.</p>
                </div>
              </div>

              <div class="space-y-4">
              <div class="rounded-lg border border-gray-200 p-4">
                <div class="flex items-center justify-between gap-3">
                  <div>
                    <p class="text-sm font-semibold text-gray-800">Preview</p>
                    <p class="text-xs text-gray-500">The file-type instruction is usually written on the file's cover.</p>
                  </div>
                  <button type="button" id="scan-upload-preview-cover"
                          class="btn btn-outline btn-sm gap-2"
                          disabled>
                    <i data-lucide="eye" class="h-4 w-4"></i>
                    Preview file
                  </button>
                </div>
                <div id="scan-upload-cover-preview"
                     class="hidden mt-3 rounded-lg border border-gray-200 bg-gray-50 p-2">
                  <div id="scan-upload-cover-body" class="flex items-center justify-center min-h-[180px]"></div>
                  <div id="scan-upload-cover-navigation" class="hidden items-center justify-center gap-3 border-t border-gray-200 pt-2 mt-2">
                    <button type="button" id="scan-upload-cover-previous" class="btn btn-outline btn-sm btn-icon" title="Previous page">
                      <i data-lucide="chevron-left" class="h-4 w-4"></i>
                    </button>
                    <span id="scan-upload-cover-position" class="min-w-24 text-center text-xs font-semibold text-gray-700">Page 1 of 1</span>
                    <button type="button" id="scan-upload-cover-next" class="btn btn-outline btn-sm btn-icon" title="Next page">
                      <i data-lucide="chevron-right" class="h-4 w-4"></i>
                    </button>
                  </div>
                </div>
              </div>

              {{-- File Type (master folder) picker removed from the upload card:
                   uploads now keep the file's existing classification, which the
                   server applies when no edms_file_type is posted. --}}
              </div>
              </div>
            </div>
            <div class="flex justify-end gap-3 p-6 border-t border-gray-200 bg-gray-50">
              <button class="btn btn-outline" id="cancel-file-select-btn">Cancel</button>
              <button class="btn btn-primary" id="confirm-file-select-btn">
                <i data-lucide="check" class="h-4 w-4"></i>
                Load Documents
              </button>
            </div>
          </div>
        </div>

        <!-- Document Details Dialog -->
        <div id="document-details-dialog" class="dialog-backdrop hidden" aria-hidden="true">
          <div class="dialog-content animate-fade-in">
            <div class="p-6 border-b border-gray-200">
              <div class="flex items-center gap-3">
                <div class="p-2 bg-indigo-100 rounded-lg">
                  <i data-lucide="file-text" class="h-5 w-5 text-indigo-600"></i>
                </div>
                <h2 class="text-lg font-bold text-gray-900">Document Details</h2>
              </div>
            </div>
            <div class="py-4 px-6 space-y-5">
              <div>
                <label for="document-name" class="block mb-2 text-sm font-semibold text-gray-700">File Name</label>
                <p class="text-sm font-medium text-gray-900" id="document-name"></p>
              </div>

              <div>
                <label class="block mb-3 text-sm font-semibold text-gray-700">Paper Size</label>
                <div class="radio-group">
                  <div class="radio-item">
                    <input type="radio" name="paper-size" id="A4" value="A4">
                    <label for="A4" class="text-sm font-medium">A4</label>
                  </div>
                  <div class="radio-item">
                    <input type="radio" name="paper-size" id="A5" value="A5">
                    <label for="A5" class="text-sm font-medium">A5</label>
                  </div>
                  <div class="radio-item">
                    <input type="radio" name="paper-size" id="A3" value="A3">
                    <label for="A3" class="text-sm font-medium">A3</label>
                  </div>
                  <div class="radio-item">
                    <input type="radio" name="paper-size" id="Letter" value="Letter">
                    <label for="Letter" class="text-sm font-medium">Letter</label>
                  </div>
                  <div class="radio-item">
                    <input type="radio" name="paper-size" id="Legal" value="Legal">
                    <label for="Legal" class="text-sm font-medium">Legal</label>
                  </div>
                  <div class="radio-item">
                    <input type="radio" name="paper-size" id="Custom" value="Custom">
                    <label for="Custom" class="text-sm font-medium">Custom</label>
                  </div>
                </div>
              </div>

              <div>
                <label for="document-type" class="block mb-2 text-sm font-semibold text-gray-700">Document Type</label>
                <select id="document-type" class="input text-sm">
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
                <label for="document-notes" class="block mb-2 text-sm font-semibold text-gray-700">Notes
                  (Optional)</label>
                <textarea id="document-notes" class="input text-sm" rows="3"
                  placeholder="Add any notes about this document..."></textarea>
              </div>
            </div>
            <div class="flex justify-end gap-3 p-6 border-t border-gray-200 bg-gray-50">
              <button class="btn btn-outline" id="cancel-details-btn">Cancel</button>
              <button class="btn btn-primary" id="save-details-btn">Save Details</button>
            </div>
          </div>
        </div>

        @include('scan_uploads.partials.blind_scan_modal')
        @include('scan_uploads.partials.reassign_modal')
        @include('components.edms.registry-transfer-modal')
        @include('components.edms.file-type-transfer-modal')
        {{-- The file picker both EDMS cards (and the reassignment dialog) open. Its
             plugin script is already loaded further down; without this markup
             GlobalFileNoModal.open() finds no #global-fileno-modal and bails. --}}
        @include('components.global-fileno-modal')

        <!-- Upload Progress Modal -->
        <div id="upload-progress-modal" class="dialog-backdrop hidden" aria-hidden="true">
          <div class="dialog-content max-w-md animate-fade-in">
            <div class="p-6 border-b border-gray-200 flex items-center gap-3">
              <div class="p-2 bg-blue-100 rounded-lg">
                <i data-lucide="loader-2" class="h-5 w-5 text-blue-600 animate-spin"></i>
              </div>
              <div>
                <h2 class="text-lg font-bold text-gray-900">Processing Upload</h2>
                <p class="text-sm text-gray-600" id="upload-progress-modal-message">Uploading documents...</p>
              </div>
            </div>
            <div class="p-6 space-y-4">
              <div class="flex items-center justify-between text-sm font-semibold text-gray-700">
                <span>Status</span>
                <span id="upload-progress-modal-percent">0%</span>
              </div>
              <div class="progress">
                <div class="progress-bar" id="upload-progress-modal-bar" style="width: 0%"></div>
              </div>
              <p class="text-xs text-gray-500">
               Processing....
              </p>
            </div>
            <div class="p-4 border-t border-gray-200 bg-gray-50 text-right">
              <button class="btn btn-outline btn-sm hidden" id="upload-progress-modal-close">Close</button>
            </div>
          </div>
        </div>

        <!-- PDF Conversion Progress Modal -->
        <div id="pdf-conversion-modal" class="dialog-backdrop hidden" aria-hidden="true">
          <div class="dialog-content animate-fade-in">
            <div class="p-6 border-b border-gray-200">
              <h2 class="text-lg font-bold text-gray-900">PDF Conversion Progress</h2>
            </div>
            <div class="py-6 px-6 space-y-6">
              <div class="flex items-center gap-4">
                <div class="p-3 bg-blue-100 rounded-lg">
                  <i data-lucide="file-up" class="h-6 w-6 text-blue-600 animate-pulse"></i>
                </div>
                <div>
                  <h4 class="font-semibold text-gray-900">Converting PDF to Images</h4>
                  <p class="text-sm text-gray-600 mt-1" id="pdf-conversion-current-file">Processing PDF files...</p>
                </div>
              </div>
              <div class="space-y-3">
                <div class="flex justify-between text-sm font-semibold">
                  <span class="text-gray-700">Conversion Progress</span>
                  <span class="text-blue-600" id="pdf-conversion-progress-percent">0%</span>
                </div>
                <div class="progress">
                  <div class="progress-bar" id="pdf-conversion-progress-bar" style="width: 0%"></div>
                </div>
              </div>
              <div class="p-4 bg-gray-50 rounded-lg text-sm">
                <p class="font-semibold text-gray-900 mb-2">Processing:</p>
                <ul class="space-y-2 text-gray-600">
                  <li class="flex items-center gap-2"><i data-lucide="check-circle-2"
                      class="h-4 w-4 text-blue-600"></i>Loading PDF files</li>
                  <li class="flex items-center gap-2"><i data-lucide="circle" class="h-4 w-4 text-gray-400"></i>Converting
                    pages to JPEG images</li>
                  <li class="flex items-center gap-2"><i data-lucide="circle" class="h-4 w-4 text-gray-400"></i>Generating
                    image files</li>
                  <li class="flex items-center gap-2"><i data-lucide="circle" class="h-4 w-4 text-gray-400"></i>Preparing
                    for upload</li>
                </ul>
              </div>
            </div>
          </div>
        </div>

        <!-- Document Preview Dialog -->
        <div id="preview-dialog" class="dialog-backdrop hidden" aria-hidden="true">
          <div class="dialog-content dialog-preview-lg animate-fade-in" data-preview-shell>
            <div class="preview-header">
              <div class="preview-header-info">
                <p class="preview-title" id="preview-title">Document Preview</p>
                <p class="preview-filename" id="preview-filename"></p>
              </div>
              <div class="preview-header-actions">
                <button type="button" class="preview-header-btn" id="preview-sidebar-toggle" title="Toggle gallery"
                  aria-label="Toggle gallery">
                  <i data-lucide="panel-left" class="h-4 w-4"></i>
                </button>
                <button type="button" class="preview-header-btn" id="toggle-fullscreen-btn" title="Toggle fullscreen"
                  aria-label="Toggle fullscreen">
                  <i data-lucide="maximize-2" class="h-4 w-4"></i>
                </button>
                <button type="button" class="preview-header-btn is-danger" id="close-preview-btn" title="Close preview"
                  aria-label="Close preview">
                  <i data-lucide="x" class="h-4 w-4"></i>
                </button>
              </div>
            </div>

            <div class="preview-shell">
              <aside class="preview-sidebar">
                <div class="preview-sidebar-header">
                  <span>Pages</span>
                  <span id="preview-sidebar-count">0</span>
                </div>
                <div class="preview-thumbnails" id="preview-thumbnails"></div>
              </aside>

              <section class="preview-stage">
                <div class="preview-toolbar">
                  <div class="preview-toolbar-left">
                    <button type="button" class="btn btn-outline btn-sm" id="preview-replace-lf">Replace with LF Scan</button>
                    <button class="toolbar-btn" id="zoom-out-btn" title="Zoom Out">
                      <i data-lucide="zoom-out" class="h-4 w-4"></i>
                    </button>
                    <span class="toolbar-badge" id="zoom-level">100%</span>
                    <button class="toolbar-btn" id="zoom-in-btn" title="Zoom In">
                      <i data-lucide="zoom-in" class="h-4 w-4"></i>
                    </button>
                    <button class="toolbar-btn" id="rotate-left-btn" title="Rotate 90 deg counterclockwise">
                      <i data-lucide="rotate-ccw" class="h-4 w-4"></i>
                    </button>
                    <button class="toolbar-btn" id="rotate-btn" title="Rotate 90 deg clockwise">
                      <i data-lucide="rotate-cw" class="h-4 w-4"></i>
                    </button>
                    <button class="toolbar-btn" id="crop-toggle-btn" title="Crop">
                      <i data-lucide="crop" class="h-4 w-4"></i>
                    </button>
                    <button class="toolbar-btn" id="reset-view-btn" title="Reset view">
                      <i data-lucide="refresh-ccw" class="h-4 w-4"></i>
                    </button>
                  </div>
                  <div class="preview-toolbar-right">
                    <button class="toolbar-btn" id="preview-upload-btn" title="Upload replacement">
                      <i data-lucide="upload-cloud" class="h-4 w-4"></i>
                    </button>
                    <input type="file" accept="image/*" id="preview-upload-input" class="hidden">
                    <button class="toolbar-btn toolbar-btn-edit" id="preview-edit-btn" title="Edit details">
                      <i data-lucide="edit-3" class="h-4 w-4"></i>
                    </button>
                    <button class="toolbar-btn" id="preview-reassign-btn" title="Reassign document">
                      <i data-lucide="git-branch" class="h-4 w-4"></i>
                    </button>
                    <button class="toolbar-btn toolbar-btn-delete" id="preview-delete-btn" title="Remove document">
                      <i data-lucide="trash-2" class="h-4 w-4"></i>
                    </button>
                    <button class="toolbar-btn" id="undo-btn" title="Undo">
                      <i data-lucide="undo" class="h-4 w-4"></i>
                    </button>
                    <button class="toolbar-btn" id="redo-btn" title="Redo">
                      <i data-lucide="redo" class="h-4 w-4"></i>
                    </button>
                    <div class="preview-nav">
                      <button class="toolbar-btn" id="prev-page-btn" title="Previous">
                        <i data-lucide="chevron-left" class="h-4 w-4"></i>
                      </button>
                      <button class="toolbar-btn" id="next-page-btn" title="Next">
                        <i data-lucide="chevron-right" class="h-4 w-4"></i>
                      </button>
                    </div>
                  </div>
                </div>

                <div class="preview-canvas" id="preview-canvas">
                  <div class="preview-image-wrapper" id="preview-image-wrapper">
                    <img id="preview-image" src="/placeholder.svg" alt="Document preview" draggable="false">
                    <div id="crop-overlay" class="crop-overlay hidden">
                      <div class="crop-selection" id="crop-selection"></div>
                      <div class="crop-actions hidden" id="crop-action-buttons">
                        <button class="btn btn-primary btn-xs" id="crop-apply-btn">Apply Crop</button>
                        <button class="btn btn-outline btn-xs" id="crop-cancel-btn">Cancel</button>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="preview-meta">
                  <div id="document-info" class="preview-info-badges"></div>
                  <button class="btn btn-primary btn-sm gap-2" id="apply-preview-btn">
                    <i data-lucide="save" class="h-4 w-4"></i>
                    Apply Changes
                  </button>
                </div>
              </section>
            </div>
          </div>
        </div>
      </div>
    </div>
    @include('admin.footer')
  </div>

  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
  <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js" crossorigin="anonymous"
    referrerpolicy="no-referrer"></script>
  <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
  <script src="{{ asset('js/global-fileno-modal.js') }}"></script>
  <script src="{{ asset('js/scan-reassignment.js') }}"></script>
  @include('scan_uploads.partials.large_format_browser')
  @include('scan_uploads.assets.scripts')
@endsection
