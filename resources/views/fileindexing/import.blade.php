@extends('layouts.app')

@section('page-title')
  {{ __('Duplicate Files Detection & Import') }}
@endsection

@section('content')
  <div class="flex-1 overflow-auto">
    @include('admin.header')

    <div id="toast-container" class="pointer-events-none fixed top-4 right-4 z-[60] w-full max-w-sm space-y-3"></div>

    <div class="p-6 space-y-8">
      <nav class="flex flex-wrap items-center gap-2 text-sm text-slate-500" aria-label="Breadcrumb">
        <a href="{{ route('dashboard') }}" class="font-medium text-slate-600 hover:text-slate-900">Dashboard</a>
        <span aria-hidden="true">/</span>
        <a href="{{ route('fileindexing.index') }}" class="font-medium text-slate-600 hover:text-slate-900">File Indexing</a>
        <span aria-hidden="true">/</span>
        <span class="font-semibold text-slate-900">Duplicate Files Detection</span>
      </nav>

      @if(session('error'))
        <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert">
          <div class="flex items-start gap-3">
            <i data-lucide="alert-triangle" class="mt-0.5 h-5 w-5"></i>
            <div>{{ session('error') }}</div>
          </div>
        </div>
      @endif

      @if(session('success'))
        <div class="rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-800" role="alert">
          <div class="flex items-start gap-3">
            <i data-lucide="check-circle" class="mt-0.5 h-5 w-5"></i>
            <div>{{ session('success') }}</div>
          </div>
        </div>
      @endif

      @php
        $importSummary = session('import_summary');
        $lastImportDuplicateRecords = session('duplicate_records', []);
        $lastImportHasDuplicates = is_array($lastImportDuplicateRecords) && count($lastImportDuplicateRecords) > 0;
        $lastImportShouldCollapse = $lastImportHasDuplicates && count($lastImportDuplicateRecords) > 10;
      @endphp

      <div class="grid gap-6 2xl:grid-cols-2">
        <section id="csv-duplicates-card" class="card flex flex-col gap-6 p-6">
          <header class="flex flex-wrap items-center justify-between gap-4">
            <div>
              <p class="text-xs font-semibold uppercase tracking-wide text-blue-600">Card 1</p>
              <h2 class="text-2xl font-semibold text-slate-900">CSV Internal Duplicates</h2>
              <p class="mt-1 text-sm text-slate-600">Upload a CSV, analyze duplicates within the file, and download a cleaned version before importing.</p>
              <p class="mt-1 text-xs text-blue-600 bg-blue-50 border border-blue-200 rounded-lg px-3 py-2">
                <strong>Note:</strong> Batch numbers and shelf locations will be automatically assigned using the system's batch management service during import, ensuring proper shelf location calculation (e.g., Batch 35 → A37).
              </p>
            </div>
          </header>

          <form method="POST" action="{{ route('fileindexing.import') }}" enctype="multipart/form-data" class="space-y-5" id="import-form"
            data-preview-url="{{ route('fileindexing.import.preview') }}"
            data-database-url="{{ route('fileindexing.duplicates.database') }}"
            data-database-detail-url="{{ route('fileindexing.duplicates.database.details') }}"
            data-database-export-url="{{ route('fileindexing.duplicates.database.export') }}"
            data-download-template="{{ route('fileindexing.duplicates.csv.download', ['token' => '__TOKEN__']) }}">
            @csrf

            <div id="csv-dropzone" class="relative flex flex-col items-center justify-center rounded-2xl border-2 border-dashed border-slate-200 bg-slate-50/60 px-6 py-10 text-center transition focus-within:border-blue-400 focus-within:bg-blue-50/40 hover:border-blue-400 hover:bg-blue-50/30" tabindex="0" role="button" aria-label="Upload CSV file">
              <i data-lucide="file-up" class="mb-4 h-10 w-10 text-blue-500"></i>
              <p class="text-base font-semibold text-slate-800">Drag & drop your CSV here</p>
              <p class="mt-2 text-sm text-slate-500">or click to browse. Maximum file size: 10&nbsp;MB (CSV format only).</p>
              <input type="file" name="csv" id="csv" accept=".csv,text/csv" class="absolute inset-0 cursor-pointer opacity-0" required aria-required="true">
              <div id="file-info" class="pointer-events-none mt-4 hidden rounded-lg border border-slate-200 bg-white px-4 py-3 text-left text-xs text-slate-600"></div>
            </div>

            <div class="flex flex-wrap items-center gap-3">
              <button type="button" id="analyze-btn" class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-blue-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-500">
                <i data-lucide="scan-line" class="h-4 w-4"></i>
                Analyze CSV for Duplicates
              </button>
              <button type="button" id="download-clean-btn-main" class="inline-flex items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm font-medium text-emerald-700 shadow-sm transition hover:bg-emerald-100 hover:border-emerald-300 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-500 disabled:cursor-not-allowed disabled:opacity-50" disabled data-download-template="{{ route('fileindexing.duplicates.csv.download', ['token' => '__TOKEN__']) }}">
                <i data-lucide="file-down" class="h-4 w-4"></i>
                Download Clean CSV
              </button>
              <button type="submit" id="submit-btn" class="inline-flex items-center gap-2 rounded-lg bg-gradient-to-r from-blue-600 to-blue-700 px-5 py-2.5 text-sm font-semibold text-white shadow-md transition hover:from-blue-700 hover:to-blue-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-500">
                <i data-lucide="upload-cloud" class="h-4 w-4"></i>
                <span id="submit-text" data-default-text="Upload &amp; Import">Upload &amp; Import</span>
              </button>
              <button type="button" id="clear-file-btn" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-600 shadow-sm transition hover:border-blue-400 hover:text-blue-600 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-500">
                <i data-lucide="x-circle" class="h-4 w-4"></i>
                Clear Selection
              </button>
            </div>

            <div id="upload-progress" class="hidden rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-700" role="status" aria-live="polite">
              <div class="flex items-center gap-2">
                <i data-lucide="loader" class="h-4 w-4 animate-spin"></i>
                <span>Import in progress. Please keep this page open while the CSV is processed.</span>
              </div>
            </div>
          </form>

          <div id="csv-preview-loading" class="hidden rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-700" role="status" aria-live="polite">
            <div class="flex items-center gap-2">
              <i data-lucide="loader-2" class="h-4 w-4 animate-spin"></i>
              <span>Analyzing CSV for duplicates…</span>
            </div>
          </div>

          <div id="csv-preview-error" class="hidden rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700" role="alert">
            <div class="flex items-start gap-2">
              <i data-lucide="alert-octagon" class="mt-0.5 h-4 w-4"></i>
              <span id="csv-preview-error-message"></span>
            </div>
          </div>

          <div id="csv-preview-panel" class="hidden space-y-5" aria-live="polite">
            <div class="grid gap-4 sm:grid-cols-2">
              <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Rows detected</p>
                <p id="csv-summary-total-rows" class="mt-2 text-3xl font-semibold text-slate-900">0</p>
              </div>
              <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-emerald-600">Ready to import</p>
                <p id="csv-summary-importable" class="mt-2 text-3xl font-semibold text-emerald-700">0</p>
              </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
              <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-amber-600">Duplicates in database</p>
                <p id="csv-summary-system-duplicates" class="mt-2 text-2xl font-semibold text-amber-700">0</p>
              </div>
              <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-amber-600">Duplicates in CSV</p>
                <p id="csv-summary-file-duplicates" class="mt-2 text-2xl font-semibold text-amber-700">0</p>
              </div>
              <div class="rounded-xl border border-red-200 bg-red-50 p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-red-600">Rows with issues</p>
                <p id="csv-summary-errors" class="mt-2 text-2xl font-semibold text-red-700">0</p>
              </div>
            </div>

            <p id="csv-summary-message" class="text-sm text-slate-600"></p>

            <div id="csv-overflow-alert" class="hidden rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
              <strong class="font-semibold">Batch capacity reached:</strong>
              <ul id="csv-overflow-list" class="mt-2 list-disc space-y-1 pl-4"></ul>
            </div>

          <div id="csv-preview-loading" class="hidden rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-700" role="status" aria-live="polite">
            <div class="flex items-center gap-2">
              <i data-lucide="loader-2" class="h-4 w-4 animate-spin"></i>
              <span>Analyzing CSV for duplicates…</span>
            </div>
          </div>

          <div id="csv-preview-error" class="hidden rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700" role="alert">
            <div class="flex items-start gap-2">
              <i data-lucide="alert-octagon" class="mt-0.5 h-4 w-4"></i>
              <span id="csv-preview-error-message"></span>
            </div>
          </div>

          <div id="csv-preview-panel" class="hidden space-y-5" aria-live="polite">
            <div class="grid gap-4 sm:grid-cols-2">
              <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Rows detected</p>
                <p id="csv-summary-total-rows" class="mt-2 text-3xl font-semibold text-slate-900">0</p>
              </div>
              <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-emerald-600">Ready to import</p>
                <p id="csv-summary-importable" class="mt-2 text-3xl font-semibold text-emerald-700">0</p>
              </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
              <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-amber-600">Duplicates in database</p>
                <p id="csv-summary-system-duplicates" class="mt-2 text-2xl font-semibold text-amber-700">0</p>
              </div>
              <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-amber-600">Duplicates in CSV</p>
                <p id="csv-summary-file-duplicates" class="mt-2 text-2xl font-semibold text-amber-700">0</p>
              </div>
              <div class="rounded-xl border border-red-200 bg-red-50 p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-red-600">Rows with issues</p>
                <p id="csv-summary-errors" class="mt-2 text-2xl font-semibold text-red-700">0</p>
              </div>
            </div>

            <p id="csv-summary-message" class="text-sm text-slate-600"></p>

            <div id="csv-overflow-alert" class="hidden rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
              <strong class="font-semibold">Batch capacity reached:</strong>
              <ul id="csv-overflow-list" class="mt-2 list-disc space-y-1 pl-4"></ul>
            </div>

            <!-- Download Clean CSV Button - Now appears after analysis -->
            <div class="flex justify-center">
              <button type="button" id="download-clean-btn" class="inline-flex items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm font-medium text-emerald-700 shadow-sm transition hover:bg-emerald-100 hover:border-emerald-300 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-500 disabled:cursor-not-allowed disabled:opacity-50" disabled data-download-template="{{ route('fileindexing.duplicates.csv.download', ['token' => '__TOKEN__']) }}">
                <i data-lucide="file-down" class="h-4 w-4"></i>
                Download Clean CSV
              </button>
            </div>

            <div class="grid gap-5 xl:grid-cols-2">
              <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                  <div>
                    <h3 class="text-base font-semibold text-slate-900">Duplicates already in database</h3>
                    <p class="text-xs text-slate-500">Files that exist in the system will be skipped during import.</p>
                  </div>
                  <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-700" id="csv-system-duplicate-count">0</span>
                </div>
                <div class="max-h-72 overflow-auto">
                  <table class="min-w-full divide-y divide-slate-200 text-sm text-slate-700">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                      <tr>
                        <th scope="col" class="px-4 py-2 text-left">File Number</th>
                        <th scope="col" class="px-4 py-2 text-left">In CSV</th>
                        <th scope="col" class="px-4 py-2 text-left">In Database</th>
                        <th scope="col" class="px-4 py-2 text-left">Actions</th>
                      </tr>
                    </thead>
                    <tbody id="csv-system-duplicates-body" class="divide-y divide-slate-100">
                      <tr id="csv-system-empty">
                        <td colspan="4" class="px-4 py-4 text-center text-sm text-slate-500">No duplicates detected in the database.</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>

              <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                  <div>
                    <h3 class="text-base font-semibold text-slate-900">Duplicates within uploaded CSV</h3>
                    <p class="text-xs text-slate-500">Review differences before choosing which row to keep.</p>
                  </div>
                  <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-700" id="csv-file-duplicate-count">0</span>
                </div>
                <div class="max-h-72 overflow-auto">
                  <table class="min-w-full divide-y divide-slate-200 text-sm text-slate-700">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                      <tr>
                        <th scope="col" class="px-4 py-2 text-left">File Number</th>
                        <th scope="col" class="px-4 py-2 text-left">Duplicate Rows</th>
                        <th scope="col" class="px-4 py-2 text-left">Actions</th>
                      </tr>
                    </thead>
                    <tbody id="csv-file-duplicates-body" class="divide-y divide-slate-100">
                      <tr id="csv-file-empty">
                        <td colspan="3" class="px-4 py-4 text-center text-sm text-slate-500">No duplicate rows detected inside the uploaded CSV.</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </section>

        <section id="database-duplicates-card" class="card space-y-5 p-6">
          <header class="flex flex-wrap items-center justify-between gap-4">
            <div>
              <p class="text-xs font-semibold uppercase tracking-wide text-blue-600">Card 2</p>
              <h2 class="text-2xl font-semibold text-slate-900">CSV vs Database Duplicates</h2>
              <p class="mt-1 text-sm text-slate-600">Shows file numbers from your uploaded CSV that already exist in the database and will be skipped during import.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
              <div class="text-xs text-slate-500 bg-slate-50 px-3 py-2 rounded-lg">
                <i data-lucide="info" class="h-3 w-3 inline mr-1"></i>
                Upload a CSV first to see duplicates
              </div>
            </div>
          </header>

          <div class="grid gap-3 md:grid-cols-1">
            <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
              <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">CSV vs Database Duplicates</p>
              <p id="database-total-count" class="mt-1 text-2xl font-semibold text-slate-900">—</p>
              <p class="text-xs text-slate-500">Files that will be skipped during import</p>
            </div>
          </div>

          <div id="csv-vs-database-duplicates" class="hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
              <table class="min-w-full divide-y divide-slate-200 text-sm text-slate-700">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                  <tr>
                    <th scope="col" class="px-4 py-2 text-left">File Number</th>
                    <th scope="col" class="px-4 py-2 text-left">CSV Title</th>
                    <th scope="col" class="px-4 py-2 text-left">CSV Registry</th>
                    <th scope="col" class="px-4 py-2 text-left">CSV Batch</th>
                    <th scope="col" class="px-4 py-2 text-left">Database Occurrences</th>
                    <th scope="col" class="px-4 py-2 text-left">Actions</th>
                  </tr>
                </thead>
                <tbody id="csv-vs-database-body" class="divide-y divide-slate-100">
                </tbody>
              </table>
            </div>
          </div>

          <div id="csv-vs-database-empty" class="rounded-xl border border-slate-200 bg-slate-50 p-6 text-center text-sm text-slate-500">
            <i data-lucide="upload" class="mx-auto h-8 w-8 mb-2 text-slate-400"></i>
            <p>Upload a CSV file to check for duplicates against the database.</p>
          </div>
        </section>
      </div>

      @if($importSummary)
        <section class="card space-y-5 p-6">
          <header class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
              <i data-lucide="activity" class="h-6 w-6 text-blue-600"></i>
              <div>
                <h3 class="text-xl font-semibold text-slate-900">Last Import Summary</h3>
                <p class="text-sm text-slate-500">Processed {{ $importSummary['total_rows'] ?? 0 }} rows in {{ $importSummary['processing_time'] ?? 0 }} seconds.</p>
              </div>
            </div>
          </header>

          <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 shadow-sm">
              <p class="text-xs font-semibold uppercase tracking-wide text-emerald-600">Imported</p>
              <p class="mt-2 text-3xl font-semibold text-emerald-700">{{ $importSummary['imported'] ?? 0 }}</p>
            </div>
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 shadow-sm">
              <p class="text-xs font-semibold uppercase tracking-wide text-amber-600">Duplicates skipped</p>
              <p class="mt-2 text-3xl font-semibold text-amber-700">{{ $importSummary['duplicates'] ?? 0 }}</p>
            </div>
            <div class="rounded-xl border border-red-200 bg-red-50 p-4 shadow-sm">
              <p class="text-xs font-semibold uppercase tracking-wide text-red-600">Errors</p>
              <p class="mt-2 text-3xl font-semibold text-red-700">{{ $importSummary['errors'] ?? 0 }}</p>
            </div>
            <div class="rounded-xl border border-blue-200 bg-blue-50 p-4 shadow-sm">
              <p class="text-xs font-semibold uppercase tracking-wide text-blue-600">Processing time</p>
              <p class="mt-2 text-3xl font-semibold text-blue-700">{{ $importSummary['processing_time'] ?? 0 }}s</p>
            </div>
          </div>

          @if(!empty($importSummary['overflow']))
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-700">
              <strong class="font-semibold">Overflow limited to 100 per batch:</strong>
              <ul class="mt-2 list-disc space-y-1 pl-5">
                @foreach($importSummary['overflow'] as $overflowKey => $overflowCount)
                  @php [$reg, $batch] = explode('|', $overflowKey); @endphp
                  <li>{{ $reg }} &mdash; Batch {{ $batch }} ({{ $overflowCount }} skipped)</li>
                @endforeach
              </ul>
            </div>
          @endif

          @if(!empty($importSummary['duplicates_truncated']))
            <div class="rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">
              Showing first {{ count($lastImportDuplicateRecords) }} duplicates. Additional duplicates were summarized only.
            </div>
          @endif
        </section>
      @endif

      @if($lastImportHasDuplicates)
        <section class="card space-y-4 p-6">
          <header class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2">
              <i data-lucide="clipboard-list" class="h-5 w-5 text-amber-600"></i>
              <h3 class="text-lg font-semibold text-slate-900">Duplicate files skipped during last import</h3>
            </div>
            <button type="button" id="last-import-toggle-duplicates" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-600 transition hover:border-blue-400 hover:text-blue-600 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-500">
              <i data-lucide="chevron-down" class="h-4 w-4 transition-transform {{ $lastImportShouldCollapse ? '' : 'rotate-180' }}"></i>
              <span>{{ $lastImportShouldCollapse ? 'Show duplicate rows' : 'Hide duplicate rows' }}</span>
            </button>
          </header>

          <div id="last-import-duplicate-records" class="rounded-xl border border-slate-200 bg-white shadow-sm {{ $lastImportShouldCollapse ? 'hidden' : '' }}">
            <div class="overflow-x-auto">
              <table class="min-w-full divide-y divide-slate-200 text-sm text-slate-700">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                  <tr>
                    <th scope="col" class="px-4 py-2 text-left">Row</th>
                    <th scope="col" class="px-4 py-2 text-left">File Number</th>
                    <th scope="col" class="px-4 py-2 text-left">Registry</th>
                    <th scope="col" class="px-4 py-2 text-left">Batch</th>
                    <th scope="col" class="px-4 py-2 text-left">Title</th>
                    <th scope="col" class="px-4 py-2 text-left">Reason</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                  @foreach($lastImportDuplicateRecords as $duplicate)
                    <tr class="hover:bg-slate-50">
                      <td class="px-4 py-2 text-sm text-slate-600">{{ $duplicate['row_number'] ?? '—' }}</td>
                      <td class="px-4 py-2 text-sm font-medium text-slate-900">{{ $duplicate['file_number'] ?? 'N/A' }}</td>
                      <td class="px-4 py-2 text-sm text-slate-600">{{ $duplicate['registry'] ?? '—' }}</td>
                      <td class="px-4 py-2 text-sm text-slate-600">{{ $duplicate['batch_no'] ?? '—' }}</td>
                      <td class="px-4 py-2 text-sm text-slate-600">{{ $duplicate['file_title'] ?? '—' }}</td>
                      <td class="px-4 py-2 text-sm text-slate-600">{{ $duplicate['reason'] ?? '' }}</td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
          </div>
        </section>
      @endif
    </div>
  </div>

  <div id="database-detail-modal" class="fixed inset-0 z-[70] hidden items-center justify-center bg-slate-900/60 p-4 backdrop-blur-sm transition" aria-hidden="true" data-modal>
    <div data-modal-backdrop class="absolute inset-0"></div>
    <div class="relative max-h-[80vh] w-full max-w-2xl overflow-hidden rounded-2xl bg-white shadow-2xl focus:outline-none" role="dialog" aria-modal="true" aria-labelledby="database-detail-title">
      <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
        <div>
          <h3 id="database-detail-title" class="text-lg font-semibold text-slate-900">Duplicate details</h3>
          <p class="text-xs text-slate-500">Showing the most recent matching records from the database.</p>
        </div>
        <button type="button" class="rounded-full border border-slate-200 bg-white p-2 text-slate-500 transition hover:border-blue-400 hover:text-blue-600 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-500" data-modal-close>
          <i data-lucide="x" class="h-4 w-4"></i>
          <span class="sr-only">Close</span>
        </button>
      </div>
      <div id="database-detail-content" class="max-h-[60vh] overflow-auto px-6 py-4 text-sm text-slate-700">
        <div class="flex items-center gap-2 rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-sm text-blue-700">
          <i data-lucide="loader" class="h-4 w-4 animate-spin"></i>
          <span>Loading duplicate records…</span>
        </div>
      </div>
    </div>
  </div>

  <div id="csv-compare-modal" class="fixed inset-0 z-[70] hidden items-center justify-center bg-slate-900/60 p-4 backdrop-blur-sm transition" aria-hidden="true" data-modal>
    <div data-modal-backdrop class="absolute inset-0"></div>
    <div class="relative max-h-[85vh] w-full max-w-3xl overflow-hidden rounded-2xl bg-white shadow-2xl focus:outline-none" role="dialog" aria-modal="true" aria-labelledby="csv-compare-title">
      <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
        <div>
          <h3 id="csv-compare-title" class="text-lg font-semibold text-slate-900">Duplicate comparison</h3>
          <p class="text-xs text-slate-500">Compare the first occurrence against duplicate rows in the uploaded CSV.</p>
        </div>
        <button type="button" class="rounded-full border border-slate-200 bg-white p-2 text-slate-500 transition hover:border-blue-400 hover:text-blue-600 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-500" data-modal-close>
          <i data-lucide="x" class="h-4 w-4"></i>
          <span class="sr-only">Close</span>
        </button>
      </div>
      <div id="csv-compare-content" class="max-h-[65vh] overflow-auto px-6 py-4 text-sm text-slate-700">
        <div class="flex items-center gap-2 rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-sm text-blue-700">
          <i data-lucide="scan" class="h-4 w-4 animate-pulse"></i>
          <span>Choose a duplicate to review detailed comparisons.</span>
        </div>
      </div>
    </div>
  </div>

  <script>
    console.log('Script loading...');
    document.addEventListener('DOMContentLoaded', () => {
      console.log('DOM loaded, initializing CSV analyzer');
      
      // Test if basic JS is working
      console.log('JavaScript is working correctly');
      
      const MAX_FILE_SIZE = 10 * 1024 * 1024;

      const toastContainer = document.getElementById('toast-container');
      const form = document.getElementById('import-form');
      const fileInput = document.getElementById('csv');
      const dropZone = document.getElementById('csv-dropzone');
      const fileInfo = document.getElementById('file-info');
      const analyzeButton = document.getElementById('analyze-btn');
      
      console.log('Key elements found:', {
        form: !!form,
        fileInput: !!fileInput,
        dropZone: !!dropZone,
        fileInfo: !!fileInfo,
        analyzeButton: !!analyzeButton
      });
      const downloadCleanButton = document.getElementById('download-clean-btn');
      const downloadCleanButtonMain = document.getElementById('download-clean-btn-main');
      const clearFileButton = document.getElementById('clear-file-btn');
      const submitButton = document.getElementById('submit-btn');
      const submitText = document.getElementById('submit-text');
      const uploadProgress = document.getElementById('upload-progress');
      const csvPreviewPanel = document.getElementById('csv-preview-panel');
      const csvPreviewLoading = document.getElementById('csv-preview-loading');
      const csvPreviewError = document.getElementById('csv-preview-error');
      const csvPreviewErrorMessage = document.getElementById('csv-preview-error-message');
      const csvSummaryTotalRows = document.getElementById('csv-summary-total-rows');
      const csvSummaryImportable = document.getElementById('csv-summary-importable');
      const csvSummarySystemDuplicates = document.getElementById('csv-summary-system-duplicates');
      const csvSummaryFileDuplicates = document.getElementById('csv-summary-file-duplicates');
      const csvSummaryErrors = document.getElementById('csv-summary-errors');
      const csvSummaryMessage = document.getElementById('csv-summary-message');
      const csvOverflowAlert = document.getElementById('csv-overflow-alert');
      const csvOverflowList = document.getElementById('csv-overflow-list');
      const csvSystemTbody = document.getElementById('csv-system-duplicates-body');
      const csvSystemEmpty = document.getElementById('csv-system-empty');
      const csvSystemCount = document.getElementById('csv-system-duplicate-count');
      const csvFileTbody = document.getElementById('csv-file-duplicates-body');
      const csvFileEmpty = document.getElementById('csv-file-empty');
      const csvFileCount = document.getElementById('csv-file-duplicate-count');

      const csvVsDatabaseSection = document.getElementById('csv-vs-database-duplicates');
      const csvVsDatabaseBody = document.getElementById('csv-vs-database-body');
      const csvVsDatabaseEmpty = document.getElementById('csv-vs-database-empty');
      const databaseTotalCount = document.getElementById('database-total-count');

      const lastImportToggle = document.getElementById('last-import-toggle-duplicates');
      const lastImportRecords = document.getElementById('last-import-duplicate-records');

      const databaseDetailModal = document.getElementById('database-detail-modal');
      const databaseDetailContent = document.getElementById('database-detail-content');
      const csvCompareModal = document.getElementById('csv-compare-modal');
      const csvCompareContent = document.getElementById('csv-compare-content');

      const defaultSubmitText = submitText ? (submitText.dataset.defaultText || submitText.textContent) : 'Upload & Import';

      const routes = form ? {
        preview: form.dataset.previewUrl,
        database: form.dataset.databaseUrl,
        databaseDetails: form.dataset.databaseDetailUrl,
        databaseExport: form.dataset.databaseExportUrl,
        downloadTemplate: form.dataset.downloadTemplate,
      } : {};

      const csrfTokenInput = form ? form.querySelector('input[name="_token"]') : null;
      const metaCsrfToken = document.querySelector('meta[name="csrf-token"]');
      const csrfToken = csrfTokenInput ? csrfTokenInput.value : (metaCsrfToken ? metaCsrfToken.getAttribute('content') : null);

      const dbState = {
        page: 1,
        perPage: 10,
        minOccurrences: 2,
        sortBy: 'occurrences',
        sortDir: 'desc',
        search: '',
        total: 0,
        lastPage: 1,
      };

      const csvPreviewState = {
        headers: [],
        systemDuplicates: [],
        csvDuplicates: [],
        cleanCsv: null,
        csvDuplicateIndex: new Map(),
      };

      let analyzeInFlight = false;

      function showToast(message, variant = 'info') {
        if (!toastContainer) return;

        const variantClasses = {
          success: 'bg-emerald-600 text-white',
          error: 'bg-red-600 text-white',
          warning: 'bg-amber-500 text-slate-900',
          info: 'bg-slate-900 text-white',
        };
        const icons = {
          success: 'check-circle',
          error: 'alert-triangle',
          warning: 'alert-triangle',
          info: 'info',
        };

        const toast = document.createElement('div');
        toast.className = `pointer-events-auto rounded-xl px-4 py-3 text-sm font-medium shadow-lg ring-1 ring-black/10 transition duration-200 ease-out ${variantClasses[variant] || variantClasses.info}`;
        toast.setAttribute('role', 'status');

        toast.innerHTML = `
          <div class="flex items-start gap-3">
            <i data-lucide="${icons[variant] || icons.info}" class="mt-0.5 h-4 w-4 flex-none"></i>
            <span class="flex-1">${message}</span>
          </div>
        `;

        toast.style.opacity = '0';
        toast.style.transform = 'translateY(-8px)';

        toastContainer.appendChild(toast);
        requestAnimationFrame(() => {
          toast.style.opacity = '1';
          toast.style.transform = 'translateY(0)';
        });

        if (window.lucide) {
          window.lucide.createIcons({ root: toast });
        }

        setTimeout(() => {
          toast.style.opacity = '0';
          toast.style.transform = 'translateY(-8px)';
          setTimeout(() => toast.remove(), 250);
        }, 4500);
      }

      function debounce(fn, delay = 300) {
        let timer;
        return (...args) => {
          clearTimeout(timer);
          timer = setTimeout(() => fn(...args), delay);
        };
      }

      function formatNumber(value) {
        if (value === null || value === undefined) return '0';
        return Number(value).toLocaleString();
      }

      function formatDate(value) {
        if (!value) return '—';
        try {
          return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value));
        } catch (error) {
          return value;
        }
      }

      function formatBytes(bytes) {
        if (!bytes && bytes !== 0) return '0 B';
        const units = ['B', 'KB', 'MB', 'GB'];
        let size = bytes;
        let unitIndex = 0;
        while (size >= 1024 && unitIndex < units.length - 1) {
          size /= 1024;
          unitIndex += 1;
        }
        return `${size.toFixed(size < 10 && unitIndex > 0 ? 1 : 0)} ${units[unitIndex]}`;
      }



      async function openDatabaseDetail(fileNumber) {
        if (!databaseDetailModal || !databaseDetailContent) {
          return;
        }

        openModal(databaseDetailModal);
        databaseDetailContent.innerHTML = `
          <div class="flex items-center gap-2 rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-sm text-blue-700">
            <i data-lucide="loader" class="h-4 w-4 animate-spin"></i>
            <span>Fetching duplicate records for <strong>${fileNumber}</strong>…</span>
          </div>
        `;

        if (window.lucide) {
          window.lucide.createIcons({ root: databaseDetailContent });
        }

        if (!routes.databaseDetails) {
          databaseDetailContent.innerHTML = '<p class="text-sm text-red-600">Unable to load duplicate details: missing endpoint.</p>';
          return;
        }

        try {
          const params = new URLSearchParams({
            file_number: fileNumber,
            limit: '200',
          });
          const response = await fetch(`${routes.databaseDetails}?${params.toString()}`, {
            headers: {
              'Accept': 'application/json',
            },
          });

          if (!response.ok) {
            throw new Error(`Failed to load duplicate details (${response.status})`);
          }

          const payload = await response.json();
          if (!payload.success) {
            throw new Error(payload.message || 'Failed to load duplicate details');
          }

          const records = Array.isArray(payload.records) ? payload.records : [];

          if (!records.length) {
            databaseDetailContent.innerHTML = `
              <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">
                No additional records were found for <strong>${fileNumber}</strong>.
              </div>
            `;
            return;
          }

          const rows = records.map((record) => `
            <tr class="hover:bg-slate-50 transition">
              <td class="px-4 py-2">${record.id}</td>
              <td class="px-4 py-2">
                <div class="font-medium text-slate-900">${record.file_title || '—'}</div>
                <div class="text-xs text-slate-500">Tracking: ${record.tracking_id || 'N/A'}</div>
              </td>
              <td class="px-4 py-2">${record.registry || '—'}</td>
              <td class="px-4 py-2">${record.batch_no || '—'}</td>
              <td class="px-4 py-2">${record.updated_at || '—'}</td>
              <td class="px-4 py-2 text-xs text-slate-500">${record.created_at || '—'}</td>
            </tr>
          `).join('');

          databaseDetailContent.innerHTML = `
            <div class="mb-3 text-sm text-slate-600">
              Showing ${records.length} record(s) for <strong>${payload.file_number}</strong>
            </div>
            <div class="overflow-x-auto rounded-lg border border-slate-200">
              <table class="min-w-full divide-y divide-slate-200 text-sm text-slate-700">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                  <tr>
                    <th class="px-4 py-2 text-left">ID</th>
                    <th class="px-4 py-2 text-left">Title</th>
                    <th class="px-4 py-2 text-left">Registry</th>
                    <th class="px-4 py-2 text-left">Batch</th>
                    <th class="px-4 py-2 text-left">Updated</th>
                    <th class="px-4 py-2 text-left">Created</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                  ${rows}
                </tbody>
              </table>
            </div>
          `;
        } catch (error) {
          console.error(error);
          databaseDetailContent.innerHTML = `
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
              Unable to load duplicate details. ${error.message || 'Please try again later.'}
            </div>
          `;
        }
      }

      function renderCsvPreviewSummary(summary, overflow) {
        if (!summary) return;

        csvSummaryTotalRows.textContent = formatNumber(summary.total_rows || 0);
        csvSummaryImportable.textContent = formatNumber(summary.importable || 0);
        csvSummarySystemDuplicates.textContent = formatNumber(summary.system_duplicates || 0);
        csvSummaryFileDuplicates.textContent = formatNumber(summary.csv_duplicates || 0);
        csvSummaryErrors.textContent = formatNumber(summary.errors || 0);

        const duplicateTotal = (summary.system_duplicates || 0) + (summary.csv_duplicates || 0);

        if (duplicateTotal > 0) {
          csvSummaryMessage.textContent = `We detected ${formatNumber(duplicateTotal)} duplicate group(s). Review the details below before completing the import.`;
        } else {
          csvSummaryMessage.textContent = 'No duplicates detected! You can safely import the cleaned CSV or continue with the original file.';
        }

        if (Array.isArray(overflow) && overflow.length) {
          csvOverflowAlert.classList.remove('hidden');
          csvOverflowList.innerHTML = overflow.map((item) => `
            <li><strong>${item.registry || 'Registry'}</strong> — Batch ${item.batch_no} ( ${formatNumber(item.count)} skipped )</li>
          `).join('');
        } else {
          csvOverflowAlert.classList.add('hidden');
          csvOverflowList.innerHTML = '';
        }
      }

      function renderCsvVsDatabaseDuplicates(systemDuplicates) {
        if (!csvVsDatabaseBody) return;

        const items = Array.isArray(systemDuplicates) ? systemDuplicates : [];
        csvVsDatabaseBody.innerHTML = '';

        if (!items.length) {
          csvVsDatabaseSection.classList.add('hidden');
          csvVsDatabaseEmpty.classList.remove('hidden');
          databaseTotalCount.textContent = '0';
          return;
        }

        csvVsDatabaseSection.classList.remove('hidden');
        csvVsDatabaseEmpty.classList.add('hidden');
        databaseTotalCount.textContent = formatNumber(items.length);

        items.forEach((item) => {
          const firstRow = Array.isArray(item.rows) && item.rows.length ? item.rows[0] : null;
          const firstRowInfo = firstRow || {};

          const tr = document.createElement('tr');
          tr.className = 'hover:bg-slate-50 transition';

          tr.innerHTML = `
            <td class="px-4 py-3">
              <div class="font-semibold text-slate-900">${item.file_number}</div>
              <div class="text-xs text-slate-500">Row ${firstRowInfo.row_number || '—'}</div>
            </td>
            <td class="px-4 py-3 text-sm text-slate-600">${firstRowInfo.file_title || '—'}</td>
            <td class="px-4 py-3 text-sm text-slate-600">${firstRowInfo.registry || '—'}</td>
            <td class="px-4 py-3 text-sm text-slate-600">${firstRowInfo.batch_no || '—'}</td>
            <td class="px-4 py-3 text-sm text-slate-600">${formatNumber(item.system_occurrences)}</td>
            <td class="px-4 py-3">
              <button type="button" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-600 shadow-sm transition hover:border-blue-400 hover:text-blue-600 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-500" data-action="view-database-detail" data-file-number="${item.file_number}">
                <i data-lucide="list-tree" class="h-3.5 w-3.5"></i>
                View Details
              </button>
            </td>
          `;

          csvVsDatabaseBody.appendChild(tr);
        });

        if (window.lucide) {
          window.lucide.createIcons({ root: csvVsDatabaseBody });
        }
      }

      function renderSystemDuplicates(systemDuplicates) {
        if (!csvSystemTbody) return;

        const items = Array.isArray(systemDuplicates) ? systemDuplicates : [];
        csvSystemTbody.innerHTML = '';

        if (!items.length) {
          csvSystemTbody.appendChild(csvSystemEmpty);
          csvSystemEmpty.classList.remove('hidden');
          csvSystemCount.textContent = '0';
          return;
        }

        csvSystemEmpty.classList.add('hidden');
        csvSystemCount.textContent = formatNumber(items.length);

        items.forEach((item) => {
          const firstRow = Array.isArray(item.rows) && item.rows.length ? item.rows[0] : null;
          const firstRowInfo = firstRow ? `Row ${firstRow.row_number}` : '—';
          const recordPreview = Array.isArray(item.existing_records) ? item.existing_records : [];
          const extraCount = Math.max((item.existing_total_records || 0) - recordPreview.length, 0);

          const previewList = recordPreview.map((record) => `
            <li class="flex items-center gap-2 text-xs text-slate-500">
              <i data-lucide="circle" class="h-2 w-2"></i>
              <span>${record.registry || '—'} • Batch ${record.batch_no || '—'} • Updated ${record.updated_at || '—'}</span>
            </li>
          `).join('');

          const extraText = extraCount > 0 ? `<div class="mt-1 text-[10px] uppercase tracking-wide text-slate-400">${formatNumber(extraCount)} more in database…</div>` : '';

          const tr = document.createElement('tr');
          tr.className = 'hover:bg-slate-50 transition';

          tr.innerHTML = `
            <td class="px-4 py-3">
              <div class="font-semibold text-slate-900">${item.file_number}</div>
              <div class="text-xs text-slate-500">First in CSV: ${firstRowInfo}</div>
            </td>
            <td class="px-4 py-3 text-sm text-slate-600">${formatNumber(item.csv_occurrences)}</td>
            <td class="px-4 py-3 text-sm text-slate-600">${formatNumber(item.system_occurrences)}</td>
            <td class="px-4 py-3">
              <div class="space-y-2">
                <button type="button" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-600 shadow-sm transition hover:border-blue-400 hover:text-blue-600 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-500" data-action="view-database-detail" data-file-number="${item.file_number}">
                  <i data-lucide="list-tree" class="h-3.5 w-3.5"></i>
                  View records
                </button>
                ${recordPreview.length ? `<ul class="mt-2 space-y-1">${previewList}</ul>${extraText}` : ''}
              </div>
            </td>
          `;

          csvSystemTbody.appendChild(tr);
        });

        if (window.lucide) {
          window.lucide.createIcons({ root: csvSystemTbody });
        }
      }

      function renderCsvDuplicatesTable(csvDuplicates) {
        if (!csvFileTbody) return;

        const items = Array.isArray(csvDuplicates) ? csvDuplicates : [];
        csvPreviewState.csvDuplicateIndex = new Map(items.map((item) => [item.file_number, item]));

        csvFileTbody.innerHTML = '';

        if (!items.length) {
          csvFileTbody.appendChild(csvFileEmpty);
          csvFileEmpty.classList.remove('hidden');
          csvFileCount.textContent = '0';
          return;
        }

        csvFileEmpty.classList.add('hidden');
        csvFileCount.textContent = formatNumber(items.length);

        items.forEach((item) => {
          const duplicatesCount = Array.isArray(item.duplicates) ? item.duplicates.length : 0;
          const firstRow = item.first_occurrence || {};
          const tr = document.createElement('tr');
          tr.className = 'hover:bg-slate-50 transition';

          tr.innerHTML = `
            <td class="px-4 py-3">
              <div class="font-semibold text-slate-900">${item.file_number}</div>
              <div class="text-xs text-slate-500">First seen at row ${firstRow.row_number || '—'}</div>
            </td>
            <td class="px-4 py-3 text-sm text-slate-600">${formatNumber(duplicatesCount)}</td>
            <td class="px-4 py-3">
              <button type="button" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-600 shadow-sm transition hover:border-blue-400 hover:text-blue-600 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-500" data-action="compare-csv-duplicate" data-file-number="${item.file_number}">
                <i data-lucide="compare-arrows" class="h-3.5 w-3.5"></i>
                Compare rows
              </button>
            </td>
          `;

          csvFileTbody.appendChild(tr);
        });

        if (window.lucide) {
          window.lucide.createIcons({ root: csvFileTbody });
        }
      }

      function renderCsvPreview(payload) {
        if (!payload) return;

        csvPreviewState.headers = Array.isArray(payload.headers) ? payload.headers : [];
        csvPreviewState.systemDuplicates = Array.isArray(payload.system_duplicates) ? payload.system_duplicates : [];
        csvPreviewState.csvDuplicates = Array.isArray(payload.csv_duplicates) ? payload.csv_duplicates : [];
        csvPreviewState.cleanCsv = payload.clean_csv || null;

        renderCsvPreviewSummary(payload.summary || {}, payload.overflow || []);
        renderCsvVsDatabaseDuplicates(csvPreviewState.systemDuplicates);
        renderSystemDuplicates(csvPreviewState.systemDuplicates);
        renderCsvDuplicatesTable(csvPreviewState.csvDuplicates);

        csvPreviewPanel.classList.remove('hidden');

        const cleanCsvAvailable = csvPreviewState.cleanCsv && csvPreviewState.cleanCsv.token;
        
        // Update both download buttons
        if (downloadCleanButton) {
          downloadCleanButton.disabled = !cleanCsvAvailable;
          if (cleanCsvAvailable) {
            downloadCleanButton.setAttribute('data-token', csvPreviewState.cleanCsv.token);
          } else {
            downloadCleanButton.removeAttribute('data-token');
          }
        }
        
        if (downloadCleanButtonMain) {
          downloadCleanButtonMain.disabled = !cleanCsvAvailable;
          if (cleanCsvAvailable) {
            downloadCleanButtonMain.setAttribute('data-token', csvPreviewState.cleanCsv.token);
          } else {
            downloadCleanButtonMain.removeAttribute('data-token');
          }
        }

        if (window.lucide) {
          window.lucide.createIcons();
        }
      }

      function resetCsvPreview(clearMessages = true) {
        csvPreviewPanel.classList.add('hidden');
        csvPreviewLoading.classList.add('hidden');
        
        // Reset both download buttons
        if (downloadCleanButton) {
          downloadCleanButton.disabled = true;
          downloadCleanButton.removeAttribute('data-token');
        }
        if (downloadCleanButtonMain) {
          downloadCleanButtonMain.disabled = true;
          downloadCleanButtonMain.removeAttribute('data-token');
        }

        csvSummaryTotalRows.textContent = '0';
        csvSummaryImportable.textContent = '0';
        csvSummarySystemDuplicates.textContent = '0';
        csvSummaryFileDuplicates.textContent = '0';
        csvSummaryErrors.textContent = '0';
        csvSummaryMessage.textContent = 'Upload a CSV file to begin duplicate detection.';
        csvOverflowAlert.classList.add('hidden');
        csvOverflowList.innerHTML = '';

        csvSystemTbody.innerHTML = '';
        csvFileTbody.innerHTML = '';

        csvSystemTbody.appendChild(csvSystemEmpty);
        csvSystemEmpty.classList.remove('hidden');
        csvSystemCount.textContent = '0';

        csvFileTbody.appendChild(csvFileEmpty);
        csvFileEmpty.classList.remove('hidden');
        csvFileCount.textContent = '0';

        csvPreviewState.headers = [];
        csvPreviewState.systemDuplicates = [];
        csvPreviewState.csvDuplicates = [];
        csvPreviewState.cleanCsv = null;
        csvPreviewState.csvDuplicateIndex = new Map();

        // Reset Card 1 (CSV vs Database)
        csvVsDatabaseSection.classList.add('hidden');
        csvVsDatabaseEmpty.classList.remove('hidden');
        databaseTotalCount.textContent = '—';
        csvVsDatabaseBody.innerHTML = '';

        if (clearMessages) {
          csvPreviewError.classList.add('hidden');
          csvPreviewErrorMessage.textContent = '';
        }
      }

      function validateSelectedFile(file) {
        if (!file) {
          fileInfo?.classList.add('hidden');
          return true;
        }

        if (!/\.csv$/i.test(file.name)) {
          showToast('Only CSV files are supported.', 'error');
          if (fileInput) fileInput.value = '';
          if (fileInfo) fileInfo.classList.add('hidden');
          return false;
        }

        if (file.size > MAX_FILE_SIZE) {
          showToast('CSV file exceeds the 10MB limit.', 'error');
          if (fileInput) fileInput.value = '';
          if (fileInfo) fileInfo.classList.add('hidden');
          return false;
        }

        if (fileInfo) {
          fileInfo.innerHTML = `
            <div class="text-sm font-medium text-slate-700">${file.name}</div>
            <div class="mt-1 text-xs text-slate-500">Size: ${formatBytes(file.size)}</div>
            <div class="text-xs text-slate-500">Last modified: ${new Date(file.lastModified).toLocaleString()}</div>
          `;
          fileInfo.classList.remove('hidden');
        }

        return true;
      }

      async function analyzeCsv() {
        console.log('analyzeCsv called');
        console.log('form:', form);
        console.log('fileInput:', fileInput);
        console.log('analyzeInFlight:', analyzeInFlight);
        
        if (!form || !fileInput) {
          console.log('Missing form or fileInput');
          return;
        }
        if (analyzeInFlight) {
          console.log('Analysis already in flight');
          return;
        }

        const file = fileInput.files && fileInput.files[0] ? fileInput.files[0] : null;
        console.log('Selected file:', file);
        
        if (!validateSelectedFile(file)) {
          console.log('File validation failed');
          return;
        }

        if (!file) {
          console.log('No file selected');
          showToast('Please choose a CSV file before analyzing.', 'warning');
          return;
        }

        console.log('routes.preview:', routes.preview);
        if (!routes.preview) {
          console.log('Preview endpoint not available');
          showToast('Preview endpoint is not available.', 'error');
          return;
        }

        console.log('csrfToken:', csrfToken);
        if (!csrfToken) {
          console.log('Missing CSRF token');
          analyzeInFlight = false;
          csvPreviewLoading.classList.add('hidden');
          analyzeButton?.classList.remove('pointer-events-none', 'opacity-70');
          showToast('Missing security token. Refresh the page and try again.', 'error');
          return;
        }

        analyzeInFlight = true;
        csvPreviewLoading.classList.remove('hidden');
        csvPreviewError.classList.add('hidden');
        csvPreviewErrorMessage.textContent = '';
        
        // Disable both download buttons during analysis
        if (downloadCleanButton) downloadCleanButton.disabled = true;
        if (downloadCleanButtonMain) downloadCleanButtonMain.disabled = true;
        
        analyzeButton?.classList.add('pointer-events-none', 'opacity-70');

        const formData = new FormData();
        formData.append('csv', file);

        try {
          const headers = {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
          };

          if (csrfToken) {
            headers['X-CSRF-TOKEN'] = csrfToken;
          }

          const response = await fetch(routes.preview, {
            method: 'POST',
            headers,
            body: formData,
            credentials: 'same-origin',
          });

          if (!response.ok) {
            const contentType = response.headers.get('content-type') || '';
            let errorMessage = `Failed to analyze CSV (${response.status})`;

            if (contentType.includes('application/json')) {
              const errorPayload = await response.json();
              errorMessage = errorPayload.message || errorMessage;
            } else {
              const errorText = await response.text();
              if (errorText) {
                errorMessage = errorText;
              }
            }

            throw new Error(errorMessage);
          }

          const payload = await response.json();

          if (!payload.success) {
            throw new Error(payload.message || 'CSV analysis failed.');
          }

          renderCsvPreview(payload);
          showToast('CSV analysis complete.', 'success');
        } catch (error) {
          console.error(error);
          csvPreviewError.classList.remove('hidden');
          csvPreviewErrorMessage.textContent = error.message || 'Failed to analyze CSV file.';
          showToast(error.message || 'Failed to analyze CSV file.', 'error');
          resetCsvPreview(false);
        } finally {
          csvPreviewLoading.classList.add('hidden');
          analyzeButton?.classList.remove('pointer-events-none', 'opacity-70');
          analyzeInFlight = false;
          if (window.lucide) {
            window.lucide.createIcons();
          }
        }
      }

      function buildCsvCompareTable(entry) {
        if (!entry) {
          return '<p class="text-sm text-slate-600">No comparison data available.</p>';
        }

        const first = entry.first_occurrence || {};
        const duplicates = Array.isArray(entry.duplicates) ? entry.duplicates : [];

        const differenceRows = duplicates.map((dup, index) => {
          const differences = Array.isArray(dup.differences) && dup.differences.length
            ? dup.differences.map((diff) => `
                <tr class="${index % 2 === 0 ? 'bg-slate-50' : ''}">
                  <td class="px-4 py-2 text-xs font-semibold text-slate-600">${diff.column}</td>
                  <td class="px-4 py-2 text-xs text-slate-500">${diff.first_value || '—'}</td>
                  <td class="px-4 py-2 text-xs text-red-600">${diff.second_value || '—'}</td>
                </tr>
              `).join('')
            : `
              <tr>
                <td colspan="3" class="px-4 py-2 text-xs text-slate-500">No field differences detected.</td>
              </tr>
            `;

          return `
            <div class="rounded-lg border border-slate-200 bg-white shadow-sm">
              <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
                <div>
                  <p class="text-sm font-semibold text-slate-900">Duplicate row ${dup.row_number || '—'}</p>
                  <p class="text-xs text-slate-500">${dup.registry || '—'} • Batch ${dup.batch_no || '—'} • ${dup.file_title || '—'}</p>
                </div>
              </div>
              <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-xs text-slate-600">
                  <thead class="bg-slate-50 text-[11px] uppercase tracking-wide text-slate-500">
                    <tr>
                      <th class="px-4 py-2 text-left">Field</th>
                      <th class="px-4 py-2 text-left">First occurrence</th>
                      <th class="px-4 py-2 text-left">Duplicate row</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-slate-100">
                    ${differenceRows}
                  </tbody>
                </table>
              </div>
            </div>
          `;
        }).join('');

        return `
          <div class="space-y-4">
            <div class="rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
              <p class="font-semibold text-slate-900">First occurrence</p>
              <p class="text-xs text-slate-500">Row ${first.row_number || '—'} • ${first.registry || '—'} • Batch ${first.batch_no || '—'} • ${first.file_title || '—'}</p>
            </div>
            ${differenceRows || '<p class="text-sm text-slate-600">No duplicates to compare for this file number.</p>'}
          </div>
        `;
      }

      function openCsvCompare(fileNumber) {
        if (!csvCompareModal || !csvCompareContent) return;

        const entry = csvPreviewState.csvDuplicateIndex.get(fileNumber);
        if (!entry) {
          showToast('Unable to load comparison data for this file number.', 'error');
          return;
        }

        openModal(csvCompareModal);
        csvCompareContent.innerHTML = buildCsvCompareTable(entry);

        if (window.lucide) {
          window.lucide.createIcons({ root: csvCompareContent });
        }
      }

      function openModal(modal) {
        if (!modal) return;
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('overflow-hidden');
      }

      function closeModal(modal) {
        if (!modal) return;
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('overflow-hidden');
      }

      document.querySelectorAll('[data-modal-close]').forEach((button) => {
        button.addEventListener('click', () => closeModal(button.closest('[data-modal]')));
      });

      document.querySelectorAll('[data-modal-backdrop]').forEach((backdrop) => {
        backdrop.addEventListener('click', () => closeModal(backdrop.closest('[data-modal]')));
      });

      document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
          [databaseDetailModal, csvCompareModal].forEach((modal) => {
            if (modal && !modal.classList.contains('hidden')) {
              closeModal(modal);
            }
          });
        }
      });

      // Database table body removed - functionality moved to specific card event handlers

      if (csvVsDatabaseBody) {
        csvVsDatabaseBody.addEventListener('click', (event) => {
          const target = event.target.closest('[data-action]');
          if (!target) return;

          if (target.dataset.action === 'view-database-detail') {
            const fileNumber = target.dataset.fileNumber;
            if (fileNumber) {
              openDatabaseDetail(fileNumber);
            }
          }
        });
      }

      if (csvSystemTbody) {
        csvSystemTbody.addEventListener('click', (event) => {
          const target = event.target.closest('[data-action]');
          if (!target) return;

          if (target.dataset.action === 'view-database-detail') {
            const fileNumber = target.dataset.fileNumber;
            if (fileNumber) {
              openDatabaseDetail(fileNumber);
            }
          }
        });
      }

      if (csvFileTbody) {
        csvFileTbody.addEventListener('click', (event) => {
          const target = event.target.closest('[data-action]');
          if (!target) return;

          if (target.dataset.action === 'compare-csv-duplicate') {
            const fileNumber = target.dataset.fileNumber;
            if (fileNumber) {
              openCsvCompare(fileNumber);
            }
          }
        });
      }



      if (dropZone) {
        const highlight = () => dropZone.classList.add('border-blue-400', 'bg-blue-50/40');
        const unhighlight = () => dropZone.classList.remove('border-blue-400', 'bg-blue-50/40');

        ['dragenter', 'dragover'].forEach((eventName) => {
          dropZone.addEventListener(eventName, (event) => {
            event.preventDefault();
            highlight();
          });
        });

        ['dragleave', 'drop'].forEach((eventName) => {
          dropZone.addEventListener(eventName, (event) => {
            event.preventDefault();
            unhighlight();
          });
        });

        dropZone.addEventListener('drop', (event) => {
          const files = event.dataTransfer.files;
          if (!files || !files.length) return;

          const file = files[0];
          if (!validateSelectedFile(file)) {
            return;
          }

          const dataTransfer = new DataTransfer();
          dataTransfer.items.add(file);
          fileInput.files = dataTransfer.files;

          resetCsvPreview();
          showToast('File ready. Click “Analyze CSV for Duplicates” to proceed.', 'info');
        });

        dropZone.addEventListener('keydown', (event) => {
          if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            fileInput?.click();
          }
        });
      }

      if (fileInput) {
        console.log('File input found:', fileInput);
        fileInput.addEventListener('change', (event) => {
          const file = event.target.files && event.target.files[0] ? event.target.files[0] : null;
          console.log('File selected:', file);
          if (validateSelectedFile(file)) {
            resetCsvPreview();
          }
        });
      } else {
        console.log('ERROR: File input not found!');
      }

      if (clearFileButton) {
        clearFileButton.addEventListener('click', () => {
          if (fileInput) {
            fileInput.value = '';
          }
          if (fileInfo) {
            fileInfo.classList.add('hidden');
            fileInfo.innerHTML = '';
          }
          resetCsvPreview();
          showToast('File selection cleared.', 'info');
        });
      }

      if (analyzeButton) {
        console.log('Attaching click event to analyze button:', analyzeButton);
        console.log('Button element:', analyzeButton.outerHTML);
        
        analyzeButton.addEventListener('click', (e) => {
          console.log('=== ANALYZE BUTTON CLICKED ===');
          console.log('Event:', e);
          e.preventDefault();
          analyzeCsv();
        });
        
        // Test button click works
        console.log('Event listener attached successfully');
      } else {
        console.log('ERROR: Analyze button not found! Check the ID.');
      }

      // Handle download button clicks for both buttons
      function handleDownloadClick(button) {
        const token = button.getAttribute('data-token');
        const template = button.getAttribute('data-download-template') || routes.downloadTemplate;
        
        if (!token || !template) {
          showToast('Clean CSV is not available yet. Analyze the file first.', 'warning');
          return;
        }
        
        const url = template.replace('__TOKEN__', token);
        window.open(url, '_blank');
      }

      if (downloadCleanButton) {
        downloadCleanButton.addEventListener('click', () => handleDownloadClick(downloadCleanButton));
      }
      
      if (downloadCleanButtonMain) {
        downloadCleanButtonMain.addEventListener('click', () => handleDownloadClick(downloadCleanButtonMain));
      }

      if (form) {
        form.addEventListener('submit', (event) => {
          const file = fileInput?.files && fileInput.files[0] ? fileInput.files[0] : null;
          if (!file) {
            event.preventDefault();
            showToast('Please choose a CSV file before importing.', 'warning');
            return;
          }

          if (!validateSelectedFile(file)) {
            event.preventDefault();
            return;
          }

          submitButton?.setAttribute('disabled', 'disabled');
          analyzeButton?.setAttribute('disabled', 'disabled');
          downloadCleanButton?.setAttribute('disabled', 'disabled');
          downloadCleanButtonMain?.setAttribute('disabled', 'disabled');
          clearFileButton?.setAttribute('disabled', 'disabled');

          if (submitText) {
            submitText.textContent = 'Importing…';
          }

          uploadProgress?.classList.remove('hidden');
        });
      }

      if (lastImportToggle && lastImportRecords) {
        lastImportToggle.addEventListener('click', () => {
          lastImportRecords.classList.toggle('hidden');
          const icon = lastImportToggle.querySelector('i[data-lucide="chevron-down"]');
          const label = lastImportToggle.querySelector('span');

          if (icon) {
            const isHidden = lastImportRecords.classList.contains('hidden');
            icon.style.transform = isHidden ? 'rotate(0deg)' : 'rotate(180deg)';
            if (label) {
              label.textContent = isHidden ? 'Show duplicate rows' : 'Hide duplicate rows';
            }
          }
        });
      }

      resetCsvPreview();

      if (window.lucide) {
        window.lucide.createIcons();
      }
      
    });
  </script>
@endsection