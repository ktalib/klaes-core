@extends('layouts.app')

@section('page-title', 'CSV Importer · File Number Workspace')

@section('content')
<div class="flex-1 overflow-auto bg-gray-50 min-h-screen">
    @include('admin.header')

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
            <p class="text-sm uppercase tracking-wide text-blue-600 font-semibold">System Admin · CSV Importer</p>
            <h1 class="text-3xl font-bold text-gray-900 mt-1">File Number Importer</h1>
            <p class="text-gray-600 mt-3">
                Upload the legacy KANGIS CSV/Excel template to run quality-control checks, map grouping records, and
                import file numbers directly from Laravel. Tracking IDs, registry metadata, and validation logic all
                reuse the centralized services captured in <code class="px-2 py-0.5 bg-slate-100 rounded text-xs">correct_fileno.json</code>.
            </p>
            <ul class="mt-4 text-sm text-gray-600 list-disc pl-5 space-y-1">
                <li>Only System Admin users can access this workspace.</li>
                <li>Tracking IDs are resolved exclusively from the grouping table during mapping.</li>
                <li>The importer enforces padding/year/spacing fixes plus catalog validation before writes.</li>
            </ul>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
            <form id="fileNumberUploadForm" class="space-y-4">
                @csrf
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label for="fileNumberCsv" class="block text-sm font-medium text-slate-700">Upload CSV / Excel</label>
                        <input type="file" id="fileNumberCsv" name="file" accept=".csv,.xlsx,.xls"
                            class="mt-1 block w-full border border-slate-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 bg-white px-3 py-2 text-sm">
                        <p class="text-xs text-slate-500 mt-1">Required columns: <strong>mlsfNo</strong> and <strong>currentAllottee</strong>.</p>
                    </div>
                    <div>
                        <label for="importTestControl" class="block text-sm font-medium text-slate-700">Test Control</label>
                        <select id="importTestControl" name="test_control"
                            class="mt-1 block w-full border border-slate-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 bg-white px-3 py-2 text-sm">
                            <option value="TEST">TEST</option>
                            <option value="PRODUCTION">PRODUCTION</option>
                        </select>
                        <p class="text-xs text-slate-500 mt-1">Use TEST for rehearsal imports. PRODUCTION writes directly to SQL Server.</p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <button type="submit"
                        class="inline-flex items-center gap-2 px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-60 disabled:cursor-not-allowed"
                        id="uploadButton">
                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1M16 8l-4-4m0 0L8 8m4-4v12" />
                        </svg>
                        Upload &amp; Preview
                    </button>
                    <span class="text-sm text-slate-500" id="uploadStatus">Waiting for upload.</span>
                </div>
            </form>
        </div>

        <div id="previewPanel" class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 hidden">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-slate-500 font-semibold">Preview Ready</p>
                    <h2 class="text-2xl font-bold text-gray-900" id="previewFilename"></h2>
                    <p class="text-sm text-slate-500">Session ID: <span id="previewSession"></span></p>
                </div>
                <button id="runImportButton"
                    class="inline-flex items-center gap-2 px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-lg focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500 disabled:opacity-60 disabled:cursor-not-allowed"
                    disabled>
                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-3-3v6m8-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Import Records
                </button>
            </div>

            <div class="grid md:grid-cols-5 gap-4 mt-6" id="summaryGrid">
                @php
                    $summaryCards = [
                        ['label' => 'Total Rows', 'id' => 'summary-total_rows'],
                        ['label' => 'New Records', 'id' => 'summary-new_records'],
                        ['label' => 'Updates', 'id' => 'summary-update_records'],
                        ['label' => 'Duplicates', 'id' => 'summary-duplicates'],
                        ['label' => 'Invalid', 'id' => 'summary-invalid'],
                    ];
                @endphp
                @foreach($summaryCards as $card)
                <div class="bg-slate-50 border border-slate-200 rounded-xl p-4">
                    <p class="text-xs uppercase tracking-wide text-slate-500">{{ $card['label'] }}</p>
                    <p class="text-2xl font-bold text-slate-900 mt-1" id="{{ $card['id'] }}">0</p>
                </div>
                @endforeach
            </div>

            <div class="mt-6">
                <h3 class="text-lg font-semibold text-slate-900 mb-3">Quick Summary</h3>
                <div class="text-sm text-slate-600" id="summaryReadyCount">Ready for import: 0 rows.</div>
            </div>
        </div>

        <div id="qcPanel" class="bg-white rounded-2xl shadow-sm border border-amber-200 p-6 hidden">
            <div class="flex items-center gap-3">
                <div class="p-2 bg-amber-100 rounded-full text-amber-700">
                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v2m0 4h.01M10.29 3.86l-7.6 13.15A1.5 1.5 0 003.95 19.5h16.1a1.5 1.5 0 001.26-2.49L13.71 3.86a1.5 1.5 0 00-2.42 0z" />
                    </svg>
                </div>
                <div>
                    <p class="text-sm font-semibold text-amber-800">Quality Control Findings</p>
                    <p class="text-xs text-amber-600">Issues grouped by category. Auto-fixable items can be corrected before import.</p>
                </div>
            </div>
            <div id="qcIssuesContainer" class="mt-5 grid gap-4 lg:grid-cols-2"></div>
        </div>

        <div id="previewTableWrapper" class="bg-white rounded-2xl shadow-sm border border-slate-200 p-0 hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm" id="previewTable">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3 text-left">#</th>
                            <th class="px-4 py-3 text-left">File Number</th>
                            <th class="px-4 py-3 text-left">Status</th>
                            <th class="px-4 py-3 text-left">Allottee</th>
                            <th class="px-4 py-3 text-left">Location</th>
                            <th class="px-4 py-3 text-left">Plot</th>
                            <th class="px-4 py-3 text-left">TP No</th>
                            <th class="px-4 py-3 text-left">Grouping</th>
                        </tr>
                    </thead>
                    <tbody id="previewTableBody" class="divide-y divide-slate-100 bg-white">
                        <tr>
                            <td colspan="8" class="px-4 py-6 text-center text-slate-400 text-sm">Upload a file to see the preview.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const form = document.getElementById('fileNumberUploadForm');
    const statusEl = document.getElementById('uploadStatus');
    const previewPanel = document.getElementById('previewPanel');
    const qcPanel = document.getElementById('qcPanel');
    const previewTableWrapper = document.getElementById('previewTableWrapper');
    const previewTableBody = document.getElementById('previewTableBody');
    const qcContainer = document.getElementById('qcIssuesContainer');
    const runImportButton = document.getElementById('runImportButton');
    const summaryReadyCount = document.getElementById('summaryReadyCount');
    const previewFilename = document.getElementById('previewFilename');
    const previewSession = document.getElementById('previewSession');
    const uploadButton = document.getElementById('uploadButton');

    const summaryFields = [
        'total_rows',
        'new_records',
        'update_records',
        'duplicates',
        'invalid',
    ].reduce((acc, key) => {
        acc[key] = document.getElementById(`summary-${key}`);
        return acc;
    }, {});

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    let currentSessionId = null;

    const setStatus = (message, tone = 'neutral') => {
        const palette = {
            neutral: 'text-slate-500',
            success: 'text-emerald-600',
            danger: 'text-rose-600',
        };
        statusEl.textContent = message;
        statusEl.className = `text-sm ${palette[tone] || palette.neutral}`;
    };

    const toggleLoading = (state) => {
        uploadButton.disabled = state;
        uploadButton.classList.toggle('opacity-60', state);
        runImportButton.disabled = state || !currentSessionId;
    };

    const renderSummary = (summary = {}) => {
        Object.entries(summaryFields).forEach(([key, element]) => {
            element.textContent = summary[key] ?? 0;
        });
        const readyCount = (summary.new_records ?? 0) + (summary.update_records ?? 0);
        summaryReadyCount.textContent = `Ready for import: ${readyCount} row${readyCount === 1 ? '' : 's'}.`;
    };

    const renderPreviewRows = (rows = []) => {
        if (!rows.length) {
            previewTableBody.innerHTML = `<tr><td colspan="8" class="px-4 py-6 text-center text-slate-400 text-sm">No rows to display.</td></tr>`;
            return;
        }

        previewTableBody.innerHTML = rows.map((row) => {
            const badgeClasses = {
                insert: 'bg-emerald-100 text-emerald-700',
                update: 'bg-amber-100 text-amber-700',
                ready: 'bg-blue-100 text-blue-700',
                invalid: 'bg-rose-100 text-rose-700',
                duplicate_upload: 'bg-slate-200 text-slate-700',
                duplicate_existing: 'bg-slate-200 text-slate-700',
            };
            const badgeClass = badgeClasses[row.status] || 'bg-slate-100 text-slate-600';
            const issues = (row.issues || []).join(', ');

            return `
                <tr>
                    <td class="px-4 py-3 text-slate-500">${row.index}</td>
                    <td class="px-4 py-3 font-mono text-xs text-slate-800">${row.file_number || '—'}</td>
                    <td class="px-4 py-3">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${badgeClass}">
                            ${row.status_label}
                        </span>
                        ${issues ? `<div class="text-xs text-slate-500 mt-1">${issues}</div>` : ''}
                    </td>
                    <td class="px-4 py-3 text-slate-700">${row.file_name || '—'}</td>
                    <td class="px-4 py-3 text-slate-500">${row.location || '—'}</td>
                    <td class="px-4 py-3 text-slate-500">${row.plot_no || '—'}</td>
                    <td class="px-4 py-3 text-slate-500">${row.tp_no || '—'}</td>
                    <td class="px-4 py-3 text-slate-500">${row.grouping_status || '—'}</td>
                </tr>
            `;
        }).join('');
    };

    const renderQcFindings = (qc = {}) => {
        const categories = Object.entries(qc).filter(([, items]) => Array.isArray(items) && items.length);
        if (!categories.length) {
            qcContainer.innerHTML = `<p class="text-sm text-slate-500">No QC issues detected 🎉</p>`;
            return;
        }

        qcContainer.innerHTML = categories.map(([category, items]) => `
            <div class="border border-amber-100 rounded-xl p-4 bg-amber-50/40">
                <p class="text-sm font-semibold text-amber-800 uppercase tracking-wide">${category} · ${items.length} issue${items.length === 1 ? '' : 's'}</p>
                <div class="mt-3 space-y-3 max-h-64 overflow-auto pr-1">
                    ${items.map((item) => `
                        <div class="bg-white rounded-lg border border-amber-100 p-3 shadow-sm">
                            <p class="text-sm font-medium text-slate-800">${item.file_number || '—'}</p>
                            <p class="text-xs text-slate-500 mt-1">${item.description}</p>
                            ${item.suggested_fix ? `<p class="text-xs text-emerald-600 mt-1">Suggested fix: ${item.suggested_fix}</p>` : ''}
                        </div>
                    `).join('')}
                </div>
            </div>
        `).join('');
    };

    const handleResponseErrors = async (response) => {
        let message = `Request failed with status ${response.status}`;
        try {
            const data = await response.json();
            if (data?.message) {
                message = data.message;
            } else if (data?.errors) {
                message = Object.values(data.errors).flat().join(' ');
            } else if (typeof data === 'string') {
                message = data;
            }
        } catch (error) {
            // Ignore JSON parsing errors
        }
        throw new Error(message);
    };

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const fileInput = document.getElementById('fileNumberCsv');
        if (!fileInput.files.length) {
            setStatus('Please choose a CSV or Excel file before uploading.', 'danger');
            return;
        }

        const formData = new FormData(form);
        toggleLoading(true);
        setStatus('Uploading & validating...', 'neutral');

        try {
            const response = await fetch('/api/csv-import/file-number/upload', {
                method: 'POST',
                body: formData,
                headers: {
                    'Accept': 'application/json',
                    ...(csrfToken ? { 'X-XSRF-TOKEN': csrfToken, 'X-CSRF-TOKEN': csrfToken } : {}),
                },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                await handleResponseErrors(response);
            }

            const payload = await response.json();
            currentSessionId = payload.session_id;
            previewFilename.textContent = payload.filename;
            previewSession.textContent = payload.session_id;
            runImportButton.disabled = false;

            renderSummary(payload.summary);
            renderPreviewRows(payload.preview_rows);
            renderQcFindings(payload.qc_findings || {});

            previewPanel.classList.remove('hidden');
            qcPanel.classList.remove('hidden');
            previewTableWrapper.classList.remove('hidden');

            setStatus('Preview generated successfully.', 'success');
        } catch (error) {
            console.error(error);
            setStatus(error.message || 'Upload failed.', 'danger');
        } finally {
            toggleLoading(false);
        }
    });

    runImportButton.addEventListener('click', async () => {
        if (!currentSessionId) return;

        toggleLoading(true);
        setStatus('Import in progress...', 'neutral');

        try {
            const response = await fetch(`/api/csv-import/file-number/import/${currentSessionId}`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    ...(csrfToken ? { 'X-XSRF-TOKEN': csrfToken, 'X-CSRF-TOKEN': csrfToken } : {}),
                },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                await handleResponseErrors(response);
            }

            const payload = await response.json();
            const summary = payload.summary || {};
            const message = `Imported ${summary.inserted ?? 0} new, ${summary.updated ?? 0} updated, ${summary.skipped_duplicates ?? 0} duplicates skipped.`;

            setStatus(message, 'success');
            runImportButton.disabled = true;
        } catch (error) {
            console.error(error);
            setStatus(error.message || 'Import failed.', 'danger');
        } finally {
            toggleLoading(false);
        }
    });
})();
</script>
@endpush
