<div class="card">
  <div class="p-6 border-b">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
      <div>
        <h2 class="text-lg font-semibold">Completed Files</h2>
        <p class="text-sm text-muted-foreground">Files that have been successfully typed and processed</p>
      </div>
      <div class="relative w-full md:w-64">
        <i data-lucide="search" class="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground"></i>
        <input type="search" placeholder="Search completed files..." class="input w-full pl-8" id="completed-search">
      </div>
    </div>
  </div>
  <div class="p-6">
    <div class="overflow-x-auto">
      <table class="w-full">
        <thead>
          <tr class="border-b">
            <th class="text-left py-3 px-4 font-medium text-sm text-muted-foreground">File Number</th>
            <th class="text-left py-3 px-4 font-medium text-sm text-muted-foreground">File Name</th>
            <th class="text-left py-3 px-4 font-medium text-sm text-muted-foreground">Date Typed</th>
            <th class="text-left py-3 px-4 font-medium text-sm text-muted-foreground">Typed By</th>
            <th class="text-left py-3 px-4 font-medium text-sm text-muted-foreground">Status</th>
            <th class="text-left py-3 px-4 font-medium text-sm text-muted-foreground">Pages</th>
            <th class="text-left py-3 px-4 font-medium text-sm text-muted-foreground">Actions</th>
          </tr>
        </thead>
        <tbody id="completed-files-table-body">
          <!-- Completed files will be populated here by JavaScript -->
        </tbody>
      </table>
    </div>
    
    <!-- Empty state (hidden by default, shown when no files) -->
    <div id="completed-files-empty" class="hidden text-center py-12">
      <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-muted">
        <i data-lucide="file-text" class="h-6 w-6"></i>
      </div>
      <h3 class="mb-2 text-lg font-medium">No completed files</h3>
      <p class="mb-4 text-sm text-muted-foreground">Complete typing files to see them here</p>
    </div>
  </div>
</div>

<!-- Modal for showing processed pages -->
<div id="processed-pages-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center p-4">
  <div class="bg-white rounded-lg max-w-6xl w-full max-h-[90vh] overflow-hidden">
    <div class="p-6 border-b flex justify-between items-center">
      <div>
        <h3 class="text-lg font-semibold" id="modal-file-title">Processed Pages</h3>
        <p class="text-sm text-muted-foreground" id="modal-file-subtitle">View all processed pages for this file</p>
      </div>
      <button class="btn btn-ghost btn-icon" id="close-modal">
        <i data-lucide="x" class="h-5 w-5"></i>
      </button>
    </div>
    <div class="p-6 overflow-y-auto max-h-[calc(90vh-120px)]">
      <div id="modal-processed-pages-content">
        <!-- Processed pages content will be populated here -->
      </div>
    </div>
  </div>
</div>