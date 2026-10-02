@extends('layouts.app')

@section('page-title', 'CSV Importer Ã‚Â· File Indexing')

@section('content')
<div class="flex-1 overflow-auto bg-gray-50 min-h-screen">
    @include('admin.header')

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
            <p class="text-sm uppercase tracking-wide text-blue-600 font-semibold">System Admin Ã‚Â· CSV Importer</p>
            <h1 class="text-3xl font-bold text-gray-900 mt-1">File Indexing Workspace</h1>
            <p class="text-gray-600 mt-3">
                Migrate KLAES file indexing data using the Laravel importer. Upload the legacy CSV/Excel template to
                normalize records, run QC, detect duplicates, and stage import-ready batches. Grouping metadata, tracking
                IDs, and prop ID alignment are handled via the shared CSV importer services.
            </p>
            <ul class="mt-4 text-sm text-gray-600 list-disc pl-5 space-y-1">
                <li>Uploads accept CSV, XLS, or XLSX. Ensure <strong>file_number</strong> and <strong>file_title</strong> columns are present.</li>
                <li>Existing file numbers (in staging or File Indexing) are automatically suppressed.</li>
                <li>Grouping preview runs for uploads up to 1,000 rows. Larger jobs still run grouping at import.</li>
            </ul>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
            <form id="fileIndexingUploadForm" class="space-y-4">
                @csrf
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label for="fileIndexingCsv" class="block text-sm font-medium text-slate-700">Upload CSV / Excel</label>
                        <input type="file" id="fileIndexingCsv" name="file" accept=".csv,.xlsx,.xls"
                            class="mt-1 block w-full border border-slate-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 bg-white px-3 py-2 text-sm">
                        <p class="text-xs text-slate-500 mt-1">Map includes Registry, Batch, File Number, Title, Land Use, Plot, LPKN, TP No, CofO dates, etc.</p>
                    </div>
                    <div>
                        <label for="fileIndexingTestControl" class="block text-sm font-medium text-slate-700">Test Control</label>
                        <select id="fileIndexingTestControl" name="test_control"
                            class="mt-1 block w-full border border-slate-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 bg-white px-3 py-2 text-sm">
                            <option value="TEST">TEST</option>
                            <option value="PRODUCTION">PRODUCTION</option>
                        </select>
                        <p class="text-xs text-slate-500 mt-1">Use TEST to stage rehearsal imports before touching production rows.</p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <button type="submit"
                        class="inline-flex items-center gap-2 px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-60 disabled:cursor-not-allowed"
                        id="indexingUploadButton">
                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1M16 8l-4-4m0 0L8 8m4-4v12" />
                        </svg>
                        Upload &amp; Preview
                    </button>
                    <span class="text-sm text-slate-500" id="indexingUploadStatus">Waiting for upload.</span>
                </div>
            </form>
        </div>

        <div id="indexingUploadsPanel" class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
            <div class="flex items-center justify-between flex-wrap gap-3">
                <div>
                    <p class="text-xs uppercase tracking-wide text-slate-500 font-semibold">Recent Uploads</p>
                    <p class="text-sm text-slate-600">Monitor queued jobs, preview-ready files, and completed imports.</p>
                </div>
                <button id="indexingRefreshUploads"
                    class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-slate-600 border border-slate-300 rounded-lg hover:bg-slate-50">
                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9M20 20v-5h-.581m-15.357-2a8.003 8.003 0 0015.357 2" />
                    </svg>
                    Refresh
                </button>
            </div>
            <div class="overflow-x-auto mt-4">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-3 py-2 text-left">File</th>
                            <th class="px-3 py-2 text-left">Rows</th>
                            <th class="px-3 py-2 text-left">Test Control</th>
                            <th class="px-3 py-2 text-left">Status</th>
                            <th class="px-3 py-2 text-left">Import Summary</th>
                            <th class="px-3 py-2 text-left">Action</th>
                        </tr>
                    </thead>
                    <tbody id="indexingUploadsBody" class="divide-y divide-slate-100 bg-white">
                        <tr>
                            <td colspan="6" class="px-4 py-4 text-center text-slate-400 text-sm">No uploads yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div id="indexingPreviewPanel" class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 hidden">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-slate-500 font-semibold">Preview Ready</p>
                    <h2 class="text-2xl font-bold text-gray-900" id="indexingPreviewFilename"></h2>
                    <p class="text-sm text-slate-500">Session ID: <span id="indexingPreviewSession"></span></p>
                </div>
                <button id="indexingImportButton"
                    class="inline-flex items-center gap-2 px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-lg focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500 disabled:opacity-60 disabled:cursor-not-allowed"
                    disabled>
                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4v16m8-8H4" />
                    </svg>
                    Import Records
                </button>
            </div>

            <div class="grid md:grid-cols-3 lg:grid-cols-6 gap-4 mt-6" id="indexingSummaryGrid">
                @php
                    $cards = [
                        ['label' => 'Total Rows', 'id' => 'summary-total_rows'],
                        ['label' => 'Preview Rows', 'id' => 'summary-preview_rows'],
                        ['label' => 'Ready for Import', 'id' => 'summary-ready_for_import'],
                        ['label' => 'Suppressed Existing', 'id' => 'summary-suppressed_existing_count'],
                        ['label' => 'Duplicates', 'id' => 'summary-duplicate_rows'],
                        ['label' => 'Invalid Rows', 'id' => 'summary-invalid_rows'],
                    ];
                @endphp
                @foreach($cards as $card)
                <div class="bg-slate-50 border border-slate-200 rounded-xl p-4">
                    <p class="text-xs uppercase tracking-wide text-slate-500">{{ $card['label'] }}</p>
                    <p class="text-2xl font-bold text-slate-900 mt-1" id="{{ $card['id'] }}">0</p>
                </div>
                @endforeach
            </div>
        </div>

        <div id="indexingSuppressedPanel" class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 hidden">
            <h3 class="text-lg font-semibold text-slate-900 mb-2">Suppressed Existing Records</h3>
            <p class="text-sm text-slate-500 mb-4">Rows skipped because the file number already exists in File Indexing or File Number staging.</p>
            <div id="suppressedList" class="space-y-2 text-sm text-slate-600"></div>
        </div>

        <div id="indexingDuplicatesPanel" class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 hidden">
            <h3 class="text-lg font-semibold text-slate-900 mb-2">Duplicate File Numbers (Upload)</h3>
            <div id="duplicateList" class="space-y-1 text-sm text-slate-600"></div>
        </div>

        <div id="indexingGroupingPanel" class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 hidden">
            <div class="flex items-center justify-between flex-wrap gap-3">
                <div>
                    <p class="text-xs uppercase tracking-wide text-slate-500 font-semibold">Grouping Preview</p>
                    <p class="text-sm text-slate-600" id="groupingSummaryText"></p>
                </div>
            </div>
            <div id="groupingTableWrapper" class="mt-4 overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-3 py-2 text-left">File Number</th>
                            <th class="px-3 py-2 text-left">Status</th>
                            <th class="px-3 py-2 text-left">Tracking ID</th>
                            <th class="px-3 py-2 text-left">Registry</th>
                            <th class="px-3 py-2 text-left">Group</th>
                        </tr>
                    </thead>
                    <tbody id="groupingTableBody" class="divide-y divide-slate-100 bg-white">
                        <tr>
                            <td colspan="5" class="px-3 py-4 text-center text-slate-400">No grouping data yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div id="indexingQcPanel" class="bg-white rounded-2xl shadow-sm border border-amber-200 p-6 hidden">
            <div class="flex items-center gap-3">
                <div class="p-2 bg-amber-100 rounded-full text-amber-700">
                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v2m0 4h.01M10.29 3.86l-7.6 13.15A1.5 1.5 0 003.95 19.5h16.1a1.5 1.5 0 001.26-2.49L13.71 3.86a1.5 1.5 0 00-2.42 0z" />
                    </svg>
                </div>
                <div>
                    <p class="text-sm font-semibold text-amber-800">Quality Control Findings</p>
                    <p class="text-xs text-amber-600">Leading zeros, year normalization, and spacing issues flagged per file number.</p>
                </div>
            </div>
            <div id="indexingQcIssues" class="mt-5 grid gap-4 lg:grid-cols-2"></div>
        </div>

        <div id="indexingTableWrapper" class="bg-white rounded-2xl shadow-sm border border-slate-200 p-0 hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3 text-left">#</th>
                            <th class="px-4 py-3 text-left">File Number</th>
                            <th class="px-4 py-3 text-left">Status</th>
                            <th class="px-4 py-3 text-left">Title</th>
                            <th class="px-4 py-3 text-left">Land Use</th>
                            <th class="px-4 py-3 text-left">Location</th>
                            <th class="px-4 py-3 text-left">Registry</th>
                            <th class="px-4 py-3 text-left">Grouping</th>
                        </tr>
                    </thead>
                    <tbody id="indexingTableBody" class="divide-y divide-slate-100 bg-white">
                        <tr>
                            <td colspan="8" class="px-4 py-6 text-center text-slate-400 text-sm">Upload a dataset to preview records.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div id="indexingPaginationControls" class="flex items-center justify-between px-4 py-3 border-t border-slate-100 bg-slate-50 hidden">
                <button id="indexingPrevPage" class="px-3 py-1.5 text-sm rounded-lg border border-slate-300 text-slate-600 hover:bg-white disabled:opacity-40 disabled:cursor-not-allowed">
                    Ã¢â‚¬Â¹ Prev
                </button>
                <p class="text-sm text-slate-600" id="indexingPaginationInfo">Page 1 of 1 Ã¢â‚¬Â¢ 0 rows</p>
                <button id="indexingNextPage" class="px-3 py-1.5 text-sm rounded-lg border border-slate-300 text-slate-600 hover:bg-white disabled:opacity-40 disabled:cursor-not-allowed">
                    Next Ã¢â‚¬Âº
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const form = document.getElementById('fileIndexingUploadForm');
    const statusEl = document.getElementById('indexingUploadStatus');

    if (!form || !statusEl) {
        return;
    }

    const uploadButton = document.getElementById('indexingUploadButton');
    const refreshUploadsBtn = document.getElementById('indexingRefreshUploads');
    const uploadsBody = document.getElementById('indexingUploadsBody');
    const previewPanel = document.getElementById('indexingPreviewPanel');
    const suppressedPanel = document.getElementById('indexingSuppressedPanel');
    const duplicatesPanel = document.getElementById('indexingDuplicatesPanel');
    const groupingPanel = document.getElementById('indexingGroupingPanel');
    const qcPanel = document.getElementById('indexingQcPanel');
    const tableWrapper = document.getElementById('indexingTableWrapper');
    const tableBody = document.getElementById('indexingTableBody');
    const suppressedList = document.getElementById('suppressedList');
    const duplicateList = document.getElementById('duplicateList');
    const groupingTableBody = document.getElementById('groupingTableBody');
    const groupingSummaryText = document.getElementById('groupingSummaryText');
    const qcIssues = document.getElementById('indexingQcIssues');
    const paginationControls = document.getElementById('indexingPaginationControls');
    const paginationInfo = document.getElementById('indexingPaginationInfo');
    const prevPageBtn = document.getElementById('indexingPrevPage');
    const nextPageBtn = document.getElementById('indexingNextPage');
    const importButton = document.getElementById('indexingImportButton');
    const previewFilename = document.getElementById('indexingPreviewFilename');
    const previewSession = document.getElementById('indexingPreviewSession');

    const summaryFields = {
        total_rows: document.getElementById('summary-total_rows'),
        preview_rows: document.getElementById('summary-preview_rows'),
        ready_for_import: document.getElementById('summary-ready_for_import'),
        suppressed_existing_count: document.getElementById('summary-suppressed_existing_count'),
        duplicate_rows: document.getElementById('summary-duplicate_rows'),
        invalid_rows: document.getElementById('summary-invalid_rows'),
    };

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    const csrfHeaders = csrfToken ? { 'X-XSRF-TOKEN': csrfToken, 'X-CSRF-TOKEN': csrfToken } : {};
    const API_BASE = '/api/csv-import/file-indexing';

    const state = {
        sessionId: null,
        page: 1,
        perPage: 50,
        lastPage: 1,
        rowCount: 0,
        summary: null,
        suppressed: [],
        duplicates: {},
        grouping: {},
        qcFindings: {},
        statusInterval: null,
        uploadsInterval: null,
    };

    const statusPalette = {
        neutral: 'text-slate-500',
        success: 'text-emerald-600',
        danger: 'text-rose-600',
    };

    const statusBadge = (status) => {
        const map = {
            uploaded: 'bg-slate-100 text-slate-700',
            processing: 'bg-blue-100 text-blue-700',
            preview_ready: 'bg-emerald-100 text-emerald-700',
            import_queued: 'bg-amber-100 text-amber-700',
            import_processing: 'bg-sky-100 text-sky-700',
            import_completed: 'bg-emerald-100 text-emerald-700',
            failed: 'bg-rose-100 text-rose-700',
        };

        return map[status] || 'bg-slate-100 text-slate-600';
    };

    const setStatus = (message, tone = 'neutral') => {
        statusEl.textContent = message;
        statusEl.className = `text-sm ${statusPalette[tone] || statusPalette.neutral}`;
    };

    const toggleUpload = (loading) => {
        uploadButton.disabled = loading;
        uploadButton.classList.toggle('opacity-60', loading);
    };

    const resetPreviewPanels = () => {
        previewPanel.classList.add('hidden');
        suppressedPanel.classList.add('hidden');
        duplicatesPanel.classList.add('hidden');
        groupingPanel.classList.add('hidden');
        qcPanel.classList.add('hidden');
        tableWrapper.classList.add('hidden');
        paginationControls.classList.add('hidden');
        tableBody.innerHTML = '<tr><td colspan="8" class="px-4 py-6 text-center text-slate-400 text-sm">Upload a dataset to preview records.</td></tr>';
        suppressedList.innerHTML = '';
        duplicateList.innerHTML = '';
        groupingTableBody.innerHTML = '<tr><td colspan="5" class="px-3 py-4 text-center text-slate-400 text-sm">No grouping data yet.</td></tr>';
        groupingSummaryText.textContent = '';
        qcIssues.innerHTML = '';
        Object.values(summaryFields).forEach((field) => {
            field.textContent = '0';
        });
    };

    const apiFetch = async (path, options = {}) => {
        const response = await fetch(`${API_BASE}${path}`, {
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                ...csrfHeaders,
                ...(options.headers || {}),
            },
            ...options,
        });

        if (!response.ok) {
            let message = `Request failed with status ${response.status}`;
            try {
                const data = await response.json();
                if (typeof data === 'string') {
                    message = data;
                } else if (data?.message) {
                    message = data.message;
                } else if (data?.errors) {
                    message = Object.values(data.errors).flat().join(' ');
                }
            } catch (_) {
                // ignore JSON parse errors
            }
            throw new Error(message);
        }

        if (response.status === 204) {
            return null;
        }

        return response.json();
    };

    const renderSummaryCards = (summary = null) => {
        const payload = summary || state.summary;
        summaryFields.total_rows.textContent = payload?.total_rows ?? state.rowCount ?? 0;
        summaryFields.preview_rows.textContent = payload?.preview_rows ?? 0;
        summaryFields.ready_for_import.textContent = payload?.ready_for_import ?? 0;
        summaryFields.suppressed_existing_count.textContent = payload?.suppressed_existing_count ?? 0;
        summaryFields.duplicate_rows.textContent = payload?.duplicate_rows ?? 0;
        summaryFields.invalid_rows.textContent = payload?.invalid_rows ?? 0;
    };

    const renderSuppressed = (rows = []) => {
        if (!rows.length) {
            suppressedPanel.classList.add('hidden');
            suppressedList.innerHTML = '';
            return;
        }

        suppressedPanel.classList.remove('hidden');
        suppressedList.innerHTML = rows
            .slice(0, 250)
            .map((row) => {
                const sources = (row.sources || []).join(', ');
                return `<div class="p-3 rounded-lg bg-slate-50 border border-slate-200"><p class="font-mono text-xs text-slate-800">${row.file_number || 'Unknown'}</p><p class="text-xs text-slate-500">Row ${row.row_index ?? '?'} • Existing in ${sources || 'staging'}</p></div>`;
            })
            .join('');
    };

    const renderDuplicates = (duplicates = {}) => {
        const entries = Object.entries(duplicates || {});
        if (!entries.length) {
            duplicatesPanel.classList.add('hidden');
            duplicateList.innerHTML = '';
            return;
        }

        duplicatesPanel.classList.remove('hidden');
        duplicateList.innerHTML = entries
            .map(([fileNumber, count]) => `<div class="flex items-center justify-between border border-slate-200 rounded-lg px-3 py-2"><span class="font-mono text-xs text-slate-800">${fileNumber}</span><span class="text-xs text-slate-500">${count} occurrences</span></div>`)
            .join('');
    };

    const renderGrouping = (grouping = {}) => {
        const rows = grouping.rows || [];
        const summary = grouping.summary || {};
        const note = grouping.note || '';

        if (!rows.length && !note && !summary.matched && !summary.missing) {
            groupingPanel.classList.add('hidden');
            groupingTableBody.innerHTML = '<tr><td colspan="5" class="px-3 py-4 text-center text-slate-400 text-sm">No grouping data yet.</td></tr>';
            groupingSummaryText.textContent = '';
            return;
        }

        groupingPanel.classList.remove('hidden');
        const matched = summary.matched ?? 0;
        const missing = summary.missing ?? 0;
        const skipped = summary.skipped ?? 0;
        groupingSummaryText.textContent = note || `Matched ${matched} • Missing ${missing} • Skipped ${skipped}`;

        if (!rows.length) {
            groupingTableBody.innerHTML = '<tr><td colspan="5" class="px-3 py-4 text-center text-slate-400 text-sm">Grouping preview skipped.</td></tr>';
            return;
        }

        groupingTableBody.innerHTML = rows
            .map((row) => `
                <tr>
                    <td class="px-3 py-2 font-mono text-xs text-slate-800">${row.file_number || 'Unknown'}</td>
                    <td class="px-3 py-2 text-slate-600">${row.status || 'missing'}</td>
                    <td class="px-3 py-2 text-slate-600">${row.tracking_id || '—'}</td>
                    <td class="px-3 py-2 text-slate-600">${row.registry || '—'}</td>
                    <td class="px-3 py-2 text-slate-600">${row.group || '—'}</td>
                </tr>
            `)
            .join('');
    };

    const renderQcIssues = (qc = {}) => {
        const categories = Object.entries(qc).filter(([, items]) => Array.isArray(items) && items.length);
        if (!categories.length) {
            qcPanel.classList.add('hidden');
            qcIssues.innerHTML = '';
            return;
        }

        qcPanel.classList.remove('hidden');
        qcIssues.innerHTML = categories
            .map(([category, items]) => `
                <div class="border border-amber-100 rounded-xl p-4 bg-amber-50/40">
                    <p class="text-sm font-semibold text-amber-800 uppercase tracking-wide">${category} • ${items.length} issue${items.length === 1 ? '' : 's'}</p>
                    <div class="mt-3 space-y-2 max-h-60 overflow-auto pr-1">
                        ${items
                            .map(
                                (item) => `
                                    <div class="bg-white rounded-lg border border-amber-100 p-3 shadow-sm">
                                        <p class="text-sm font-medium text-slate-800">${item.file_number || 'Unknown'}</p>
                                        <p class="text-xs text-slate-500 mt-1">${item.description || 'QC flag'}</p>
                                        ${item.suggested_fix ? `<p class="text-xs text-emerald-600 mt-1">Suggested fix: ${item.suggested_fix}</p>` : ''}
                                    </div>
                                `
                            )
                            .join('')}
                    </div>
                </div>
            `)
            .join('');
    };

    const renderPreviewRows = (rows = []) => {
        if (!rows.length) {
            tableBody.innerHTML = '<tr><td colspan="8" class="px-4 py-6 text-center text-slate-400 text-sm">No rows to display.</td></tr>';
            paginationControls.classList.add('hidden');
            return;
        }

        const statusMap = {
            ready: { label: 'Ready', classes: 'bg-emerald-100 text-emerald-700' },
            invalid: { label: 'Invalid', classes: 'bg-rose-100 text-rose-700' },
            duplicate_upload: { label: 'Duplicate (Upload)', classes: 'bg-slate-200 text-slate-700' },
            existing: { label: 'Existing', classes: 'bg-slate-200 text-slate-700' },
        };

        tableBody.innerHTML = rows
            .map((row) => {
                const status = statusMap[row.status] || { label: row.status || 'Unknown', classes: 'bg-slate-100 text-slate-700' };
                const issues = (row.issues || []).join(', ');
                const grouping = row.grouping || {};
                const groupingText = grouping.status
                    ? `${grouping.status}${grouping.tracking_id ? ` • ${grouping.tracking_id}` : ''}`
                    : '—';

                return `
                    <tr>
                        <td class="px-4 py-3 text-slate-500">${row.index ?? '—'}</td>
                        <td class="px-4 py-3 font-mono text-xs text-slate-800">${row.file_number || '—'}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${status.classes}">${status.label}</span>
                            ${issues ? `<div class="text-xs text-slate-500 mt-1">${issues}</div>` : ''}
                        </td>
                        <td class="px-4 py-3 text-slate-700">${row.file_title || '—'}</td>
                        <td class="px-4 py-3 text-slate-500">${row.land_use_type || '—'}</td>
                        <td class="px-4 py-3 text-slate-500">${row.location || '—'}</td>
                        <td class="px-4 py-3 text-slate-500">${row.registry || '—'}</td>
                        <td class="px-4 py-3 text-slate-500">${groupingText}</td>
                    </tr>
                `;
            })
            .join('');

        paginationControls.classList.toggle('hidden', state.lastPage <= 1);
        paginationInfo.textContent = `Page ${state.page} of ${state.lastPage} • ${state.rowCount} rows`;
        prevPageBtn.disabled = state.page <= 1;
        nextPageBtn.disabled = state.page >= state.lastPage;
    };

    const loadPreviewRows = async () => {
        if (!state.sessionId) {
            return;
        }

        try {
            const query = new URLSearchParams({ page: state.page, per_page: state.perPage });
            const payload = await apiFetch(`/preview/${state.sessionId}/rows?${query.toString()}`);
            const pagination = payload.pagination || {};
            state.lastPage = pagination.last_page ?? 1;
            state.rowCount = pagination.total ?? state.rowCount;
            renderPreviewRows(payload.rows || []);
        } catch (error) {
            console.error(error);
            setStatus(error.message || 'Unable to load preview rows.', 'danger');
        }
    };

    const renderUploads = (uploads = []) => {
        if (!uploads.length) {
            uploadsBody.innerHTML = '<tr><td colspan="6" class="px-4 py-4 text-center text-slate-400 text-sm">No uploads logged yet.</td></tr>';
            return;
        }

        uploadsBody.innerHTML = uploads
            .map((upload) => {
                const summary = upload.import_summary
                    ? `${upload.import_summary.inserted ?? 0} ins / ${upload.import_summary.updated ?? 0} upd / ${upload.import_summary.skipped ?? 0} skip`
                    : '—';
                const createdAt = upload.created_at ? new Date(upload.created_at).toLocaleString() : '—';

                return `
                    <tr>
                        <td class="px-3 py-2">
                            <div class="font-semibold text-slate-800">${upload.file_name}</div>
                            <p class="text-xs text-slate-500">${createdAt}</p>
                        </td>
                        <td class="px-3 py-2 text-slate-600">${upload.row_count ?? '—'}</td>
                        <td class="px-3 py-2 text-slate-600">${upload.test_control ?? '—'}</td>
                        <td class="px-3 py-2">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${statusBadge(upload.status)}">${(upload.status || 'unknown').replace(/_/g, ' ')}</span>
                        </td>
                        <td class="px-3 py-2 text-slate-600">${summary}</td>
                        <td class="px-3 py-2">
                            <button class="px-3 py-1.5 text-xs font-medium rounded-lg border border-slate-300 text-slate-600 hover:bg-slate-50" data-open-session="${upload.session_id}">Open</button>
                        </td>
                    </tr>
                `;
            })
            .join('');
    };

    const loadUploads = async () => {
        try {
            const payload = await apiFetch('/uploads');
            state.uploads = payload.data || [];
            renderUploads(state.uploads);
        } catch (error) {
            console.error(error);
        }
    };

    const stopStatusPolling = () => {
        if (state.statusInterval) {
            clearInterval(state.statusInterval);
            state.statusInterval = null;
        }
    };

    const refreshStatus = async (announce = false) => {
        if (!state.sessionId) {
            return;
        }

        try {
            const payload = await apiFetch(`/status/${state.sessionId}`);
            previewFilename.textContent = payload.filename || 'Pending upload';
            previewSession.textContent = payload.session_id || state.sessionId;

            if (payload.summary) {
                state.summary = payload.summary;
                renderSummaryCards(payload.summary);
            }

            if (payload.status === 'preview_ready') {
                stopStatusPolling();
                importButton.disabled = false;
                setStatus(payload.progress?.message || 'Preview ready. Loading data...', 'success');
                await hydratePreview(state.sessionId);
                return;
            }

            if (payload.status === 'failed') {
                stopStatusPolling();
                setStatus(payload.error || 'Preview failed. Please check the logs.', 'danger');
                return;
            }

            if (payload.status === 'import_completed') {
                stopStatusPolling();
                importButton.disabled = true;
                setStatus(payload.progress?.message || 'Import complete.', 'success');
                loadUploads();
                return;
            }

            const progress = payload.progress;
            if (progress) {
                const label = progress.message || 'Processing...';
                const suffix = progress.total ? ` (${progress.processed}/${progress.total})` : '';
                setStatus(`${label}${suffix}`, 'neutral');
            } else if (announce) {
                setStatus('Processing...', 'neutral');
            }
        } catch (error) {
            console.error(error);
            setStatus(error.message || 'Unable to load status.', 'danger');
        }
    };

    const startStatusPolling = (sessionId) => {
        stopStatusPolling();
        state.sessionId = sessionId;
        refreshStatus(true);
        state.statusInterval = setInterval(() => refreshStatus(false), 5000);
    };

    const hydratePreview = async (sessionId) => {
        try {
            const payload = await apiFetch(`/preview/${sessionId}`);
            state.summary = payload.summary || null;
            state.suppressed = payload.suppressed || [];
            state.duplicates = payload.multiple_occurrences || {};
            state.grouping = payload.grouping_preview || {};
            state.qcFindings = payload.qc_findings || {};
            state.rowCount = payload.row_count ?? 0;
            state.perPage = payload.per_page ?? state.perPage;
            state.page = 1;

            previewPanel.classList.remove('hidden');
            tableWrapper.classList.remove('hidden');

            renderSummaryCards(state.summary);
            renderSuppressed(state.suppressed);
            renderDuplicates(state.duplicates);
            renderGrouping(state.grouping);
            renderQcIssues(state.qcFindings);

            await loadPreviewRows();
        } catch (error) {
            console.error(error);
            setStatus(error.message || 'Unable to load preview.', 'danger');
        }
    };

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const fileInput = document.getElementById('fileIndexingCsv');
        if (!fileInput?.files?.length) {
            setStatus('Please choose a CSV or Excel file before uploading.', 'danger');
            return;
        }

        resetPreviewPanels();
        toggleUpload(true);
        setStatus('Uploading file...', 'neutral');

        try {
            const formData = new FormData(form);
            const payload = await apiFetch('/upload', {
                method: 'POST',
                body: formData,
            });

            state.sessionId = payload.session_id;
            state.page = 1;
            previewFilename.textContent = payload.filename || 'Upload';
            previewSession.textContent = payload.session_id;
            previewPanel.classList.remove('hidden');
            importButton.disabled = true;

            setStatus(payload.message || 'File stored. Preparing preview...', 'neutral');
            startStatusPolling(payload.session_id);
            loadUploads();
        } catch (error) {
            console.error(error);
            setStatus(error.message || 'Upload failed.', 'danger');
        } finally {
            toggleUpload(false);
        }
    });

    importButton.addEventListener('click', async () => {
        if (!state.sessionId) {
            return;
        }

        importButton.disabled = true;
        setStatus('Queuing import...', 'neutral');

        try {
            const payload = await apiFetch(`/import/${state.sessionId}`, { method: 'POST' });
            setStatus(payload.message || 'Import queued. Monitoring progress...', 'neutral');
            startStatusPolling(state.sessionId);
        } catch (error) {
            console.error(error);
            setStatus(error.message || 'Import failed.', 'danger');
            importButton.disabled = false;
        }
    });

    prevPageBtn.addEventListener('click', () => {
        if (state.page <= 1) {
            return;
        }
        state.page -= 1;
        loadPreviewRows();
    });

    nextPageBtn.addEventListener('click', () => {
        if (state.page >= state.lastPage) {
            return;
        }
        state.page += 1;
        loadPreviewRows();
    });

    refreshUploadsBtn.addEventListener('click', () => loadUploads());

    uploadsBody.addEventListener('click', (event) => {
        const button = event.target.closest('[data-open-session]');
        if (!button) {
            return;
        }

        const sessionId = button.getAttribute('data-open-session');
        if (!sessionId) {
            return;
        }

        resetPreviewPanels();
        previewPanel.classList.remove('hidden');
        previewSession.textContent = sessionId;
        importButton.disabled = true;
        setStatus('Loading session...', 'neutral');
        startStatusPolling(sessionId);
    });

    resetPreviewPanels();
    loadUploads();
    state.uploadsInterval = setInterval(loadUploads, 20000);

    window.addEventListener('beforeunload', () => {
        stopStatusPolling();
        if (state.uploadsInterval) {
            clearInterval(state.uploadsInterval);
        }
    });
})();
</script>
@endpush
