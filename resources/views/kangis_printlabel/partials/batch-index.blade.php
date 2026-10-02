{{-- ====== BATCH INDEX TAB ======
     Generates a shelf/rack-ordered index of already generated & printed
     QR-coded label batches. Read-only: it reports on existing batches
     (kangis_print_label_batches + items), it never creates them. --}}
<div id="batchindex-tab" class="tab-content mt-6">
    <div class="bg-white rounded-lg border">
        <div class="p-6 border-b bg-gradient-to-r from-slate-50 to-white">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h3 class="text-lg font-semibold">Batch Index for QR-Coded Files</h3>
                    <p class="text-sm text-gray-600">
                        Build a registry index of already generated/printed QR label batches, ordered by
                        <strong>Shelf/Rack</strong>. The printout carries two signature blocks.
                    </p>
                </div>
                <div class="flex gap-2">
                    <button
                        id="biGenerateBtn"
                        class="inline-flex items-center gap-2 justify-center rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
                    >
                        <i data-lucide="list-ordered" class="h-4 w-4"></i>
                        Generate Batch Index
                    </button>
                    <button
                        id="biPrintBtn"
                        disabled
                        class="inline-flex items-center gap-2 justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <i data-lucide="printer" class="h-4 w-4"></i>
                        Print Index
                    </button>
                </div>
            </div>
        </div>

        <div class="p-6">
            {{-- Filters: same field set as the Select Files panel (prefix, registry
                 batch no, rack, backup rack, shelf, auto-derived full label).
                 Each dropdown carries a blank "all" option so a whole rack or the
                 entire registry can be indexed in one sheet. --}}
            <div class="rounded-lg border border-blue-200 bg-blue-50/30 px-4 py-4 mb-6">
                <div class="grid grid-cols-1 gap-4 lg:grid-cols-6">
                    <div class="flex flex-col">
                        <span class="text-xs font-semibold uppercase tracking-wide text-slate-600">Prefix</span>
                        <select id="biPrefixSelect" class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
                            <option value="">Select Prefix</option>
                            <option value="KN">KN</option>
                            <option value="KNML">KNML</option>
                            <option value="MLKN">MLKN</option>
                            <option value="KNGP">KNGP</option>
                        </select>
                        <span class="mt-1 text-xs text-slate-500">KANGIS file prefix to index.</span>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-xs font-semibold uppercase tracking-wide text-slate-600">Registry Batch No</span>
                        <input
                            type="text"
                            id="biBatchNoInput"
                            placeholder="e.g. 1 or 1,4,5"
                            class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"
                        />
                        <span class="mt-1 text-xs text-slate-500">Filter by sys_batch_no.</span>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-xs font-semibold uppercase tracking-wide text-slate-600">Rack</span>
                        <select id="biRackSelect" class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
                            <option value="">All</option>
                            @foreach(range('A', 'Z') as $letter)
                                <option value="{{ $letter }}">{{ $letter }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-xs font-semibold uppercase tracking-wide text-slate-600">Backup Rack</span>
                        <select id="biRackSecondarySelect" class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
                            <option value="">None</option>
                            @foreach(range('A', 'Z') as $letter)
                                <option value="{{ $letter }}">{{ $letter }}</option>
                            @endforeach
                        </select>
                        <span class="mt-1 text-xs text-slate-500">Optional secondary rack.</span>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-xs font-semibold uppercase tracking-wide text-slate-600">Shelf</span>
                        <select id="biShelfSelect" class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
                            <option value="">All</option>
                            @for($i = 1; $i <= 100; $i++)
                                <option value="{{ $i }}">{{ $i }}</option>
                            @endfor
                        </select>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-xs font-semibold uppercase tracking-wide text-slate-600">Full Label</span>
                        <input
                            type="text"
                            id="biFullLabelInput"
                            value="All"
                            readonly
                            class="mt-1 w-full rounded-md border border-gray-300 bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-700"
                        />
                        <span class="mt-1 text-xs text-slate-500">Auto-generated from rack &amp; shelf.</span>
                    </div>
                </div>
                <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-3">
                    <div class="flex flex-col">
                        <span class="text-xs font-semibold uppercase tracking-wide text-slate-600">Label Status</span>
                        <select id="biStatusSelect" class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
                            <option value="any">All generated batches</option>
                            <option value="printed">Printed only</option>
                            <option value="generated">Generated (not yet printed)</option>
                            <option value="completed">Completed</option>
                        </select>
                        <span class="mt-1 text-xs text-slate-500">Only batches with QR labels already generated are listed.</span>
                    </div>
                    <label for="biDetailedToggle" class="md:col-span-2 flex cursor-pointer items-start gap-3 rounded-lg border border-dashed border-slate-200 bg-white px-3 py-3 text-xs text-slate-600">
                        <input type="checkbox" id="biDetailedToggle" class="mt-1 rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                        <span>
                            <span class="block font-semibold uppercase tracking-wide text-[11px] text-slate-500">Detailed rows</span>
                            <span>By default the print batches of one registry batch on the same shelf are merged into a single index row. Tick this to list every print batch separately.</span>
                        </span>
                    </label>
                </div>
            </div>

            {{-- Summary tiles --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <div class="bg-blue-50 rounded-lg p-4">
                    <div class="text-sm font-medium text-blue-700">Batches Indexed</div>
                    <div class="text-2xl font-bold text-blue-900" id="biBatchCount">0</div>
                </div>
                <div class="bg-green-50 rounded-lg p-4">
                    <div class="text-sm font-medium text-green-700">QR-Coded Files</div>
                    <div class="text-2xl font-bold text-green-900" id="biFileCount">0</div>
                </div>
                <div class="bg-purple-50 rounded-lg p-4">
                    <div class="text-sm font-medium text-purple-700">Shelves Covered</div>
                    <div class="text-2xl font-bold text-purple-900" id="biShelfCount">0</div>
                </div>
            </div>

            {{-- Index preview (same columns as the printout) --}}
            <div class="rounded-md border overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-yellow-100">
                        <tr class="text-center">
                            <th class="border px-3 py-2 font-semibold">SN</th>
                            <th class="border px-3 py-2 font-semibold">Registry BatchNo</th>
                            <th class="border px-3 py-2 font-semibold">File Prefix</th>
                            <th class="border px-3 py-2 font-semibold">Serial Range</th>
                            <th class="border px-3 py-2 font-semibold">Rack</th>
                            <th class="border px-3 py-2 font-semibold">Shelf</th>
                            <th class="border px-3 py-2 font-semibold">Shelf/Rack</th>
                            <th class="border px-3 py-2 font-semibold">Files</th>
                            <th class="border px-3 py-2 font-semibold">Status</th>
                        </tr>
                    </thead>
                    <tbody id="biTableBody">
                        <tr>
                            <td colspan="9" class="p-8 text-center text-gray-500">
                                <i data-lucide="table" class="h-8 w-8 mx-auto text-gray-400"></i>
                                <p class="mt-2">No index generated yet.</p>
                                <p class="text-xs text-gray-400 mt-1">Set your filters and click Generate Batch Index.</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const BI_API   = "{{ route('kangis-printlabel.api.batch-index') }}";
    const BI_PRINT = "{{ route('kangis-printlabel.batch-index.print') }}";

    const el = function (id) { return document.getElementById(id); };
    const body = el('biTableBody');
    let biRows = [];

    function biFilters() {
        return {
            prefix:            el('biPrefixSelect').value,
            registry_batch_no: el('biBatchNoInput').value.trim(),
            rack:              el('biRackSelect').value,
            rack_secondary:    el('biRackSecondarySelect').value,
            shelf:             el('biShelfSelect').value,
            status:            el('biStatusSelect').value,
            detailed:          el('biDetailedToggle').checked ? '1' : '',
        };
    }

    // Full Label mirrors the Select Files panel: rack + shelf, or "All" when the
    // scope is left open (whole rack / whole registry).
    function biUpdateFullLabel() {
        var rack  = (el('biRackSelect').value || '').toUpperCase().trim();
        var shelf = (el('biShelfSelect').value || '').trim();
        var label;
        if (!rack) label = shelf ? 'All racks · Shelf ' + shelf : 'All';
        else label = shelf ? rack + shelf : 'Rack ' + rack + ' (all shelves)';
        el('biFullLabelInput').value = label;
    }

    function biQueryString() {
        const f = biFilters();
        const params = new URLSearchParams();
        Object.keys(f).forEach(function (k) {
            if (f[k] !== '' && f[k] !== null && f[k] !== undefined) params.append(k, f[k]);
        });
        return params.toString();
    }

    function escapeHtml(value) {
        return String(value === null || value === undefined ? '' : value)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    // File numbers as a fixed 6-column grid, mirroring the printed sheet.
    const BI_FILES_PER_ROW = 6;

    function biFilesGrid(numbers) {
        let html = '<table class="w-full table-fixed border-collapse">';
        for (let i = 0; i < numbers.length; i += BI_FILES_PER_ROW) {
            const chunk = numbers.slice(i, i + BI_FILES_PER_ROW);
            html += '<tr>';
            chunk.forEach(function (n) {
                html += '<td class="border px-2 py-1 text-center text-xs text-slate-700 truncate">' + escapeHtml(n) + '</td>';
            });
            for (let pad = chunk.length; pad < BI_FILES_PER_ROW; pad++) {
                html += '<td class="border px-2 py-1 bg-slate-100"></td>';
            }
            html += '</tr>';
        }
        return html + '</table>';
    }

    function biEmptyRow(message, hint) {
        body.innerHTML =
            '<tr><td colspan="9" class="p-8 text-center text-gray-500"><p>' + escapeHtml(message) + '</p>' +
            (hint ? '<p class="text-xs text-gray-400 mt-1">' + escapeHtml(hint) + '</p>' : '') +
            '</td></tr>';
    }

    function biRender(rows) {
        biRows = rows || [];
        el('biPrintBtn').disabled = biRows.length === 0;

        const files = biRows.reduce(function (sum, r) { return sum + (r.file_count || 0); }, 0);
        const shelves = new Set(biRows.map(function (r) { return r.shelf_rack; }).filter(Boolean));
        el('biBatchCount').textContent = biRows.length.toLocaleString();
        el('biFileCount').textContent  = files.toLocaleString();
        el('biShelfCount').textContent = shelves.size.toLocaleString();

        if (!biRows.length) {
            biEmptyRow('No QR-coded label batches match the selected filters.', 'Try widening the rack, shelf or batch-number range.');
            return;
        }

        body.innerHTML = biRows.map(function (r) {
            const dash = '&mdash;';
            const numbers = r.file_numbers || [];
            const filesRow = numbers.length
                ? '<tr class="bg-slate-50/60"><td class="border p-0" colspan="9">' +
                      '<div class="px-3 py-1 border-b font-semibold uppercase tracking-wide text-[10px] text-slate-500">' +
                          'File Nos (' + numbers.length + ')</div>' +
                      biFilesGrid(numbers) +
                  '</td></tr>'
                : '';
            return '<tr class="text-center hover:bg-slate-50">' +
                '<td class="border px-3 py-1.5">' + r.sn + '</td>' +
                '<td class="border px-3 py-1.5">' + (r.registry_batch_no ? escapeHtml(r.registry_batch_no) : dash) + '</td>' +
                '<td class="border px-3 py-1.5">' + (r.file_prefix ? escapeHtml(r.file_prefix) : dash) + '</td>' +
                '<td class="border px-3 py-1.5">' + escapeHtml(r.serial_range) + '</td>' +
                '<td class="border px-3 py-1.5">' + (r.rack ? escapeHtml(r.rack) : dash) + '</td>' +
                '<td class="border px-3 py-1.5">' + (r.shelf ? escapeHtml(r.shelf) : dash) + '</td>' +
                '<td class="border px-3 py-1.5 font-semibold">' + (r.shelf_rack ? escapeHtml(r.shelf_rack) : dash) + '</td>' +
                '<td class="border px-3 py-1.5">' + (r.file_count || 0) + '</td>' +
                '<td class="border px-3 py-1.5 capitalize">' + escapeHtml(r.status || '') + '</td>' +
            '</tr>' + filesRow;
        }).join('');
    }

    function biLoad(options) {
        options = options || {};
        if (!options.silent) {
            biEmptyRow('Building batch index…');
        }

        return fetch(BI_API + '?' + biQueryString(), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (res) { return res.json(); })
            .then(function (json) {
                if (!json.success) throw new Error(json.message || 'Unable to build batch index.');
                biRender(json.data.rows);
            })
            .catch(function (err) {
                biRender([]);
                biEmptyRow('Unable to build batch index.', err.message || '');
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon: 'error', title: 'Batch Index', text: err.message || 'Request failed.', confirmButtonColor: '#3b82f6' });
                }
            });
    }

    ['biRackSelect', 'biShelfSelect'].forEach(function (id) {
        el(id).addEventListener('change', biUpdateFullLabel);
    });
    biUpdateFullLabel();

    el('biGenerateBtn').addEventListener('click', function () { biLoad(); });

    el('biPrintBtn').addEventListener('click', function () {
        if (!biRows.length) return;
        window.open(BI_PRINT + '?' + biQueryString() + '&auto_print=1', '_blank');
    });

    // Auto-load the first time the tab is opened.
    let biLoadedOnce = false;
    const biTabBtn = document.querySelector('.tab-btn[data-tab="batchindex"]');
    if (biTabBtn) {
        biTabBtn.addEventListener('click', function () {
            if (!biLoadedOnce) { biLoadedOnce = true; biLoad(); }
        });
    }
});
</script>
