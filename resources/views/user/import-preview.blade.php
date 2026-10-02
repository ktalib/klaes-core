<!-- CSV Import Preview Table Component -->
<div id="previewContainer" class="hidden space-y-4">
    <!-- Preview Header -->
    <div class="bg-gradient-to-r from-emerald-500 to-teal-600 px-6 py-4 rounded-lg">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <div>
                    <h4 class="text-lg font-bold text-white">{{ __('CSV Preview & Review') }}</h4>
                    <p class="text-emerald-100 text-sm">{{ __('Review, edit, and verify your data before importing') }}</p>
                </div>
            </div>
            <div class="bg-white bg-opacity-20 px-3 py-1 rounded-full">
                <p class="text-white text-sm font-medium"><span id="recordCount">0</span> {{ __('records') }}</p>
            </div>
        </div>
    </div>

    <!-- Preview Stats -->
    <div class="grid grid-cols-3 gap-3">
        <div class="bg-blue-50 rounded-lg p-3 border-l-4 border-blue-500">
            <p class="text-xs text-blue-600 font-medium">{{ __('Total Records') }}</p>
            <p class="text-2xl font-bold text-blue-900" id="totalRecords">0</p>
        </div>
        <div class="bg-green-50 rounded-lg p-3 border-l-4 border-green-500">
            <p class="text-xs text-green-600 font-medium">{{ __('Valid Records') }}</p>
            <p class="text-2xl font-bold text-green-900" id="validRecords">0</p>
        </div>
        <div class="bg-amber-50 rounded-lg p-3 border-l-4 border-amber-500">
            <p class="text-xs text-amber-600 font-medium">{{ __('Issues Found') }}</p>
            <p class="text-2xl font-bold text-amber-900" id="issuesCount">0</p>
        </div>
    </div>

    <!-- Error Summary (if any) -->
    <div id="errorSummary" class="hidden bg-red-50 border-l-4 border-red-500 p-4 rounded">
        <p class="font-semibold text-red-900 mb-2">{{ __('Issues Found:') }}</p>
        <ul id="errorList" class="text-sm text-red-800 space-y-1"></ul>
    </div>

    <!-- Table Controls -->
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div class="flex items-center space-x-2">
            <span class="text-sm text-gray-600">{{ __('Actions:') }}</span>
            <button type="button" onclick="deleteAllRows()" class="inline-flex items-center px-3 py-1 bg-red-50 text-red-700 text-xs font-medium rounded-lg border border-red-200 hover:bg-red-100 transition">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                </svg>
                {{ __('Clear All') }}
            </button>
            <button type="button" onclick="addNewRow()" class="inline-flex items-center px-3 py-1 bg-blue-50 text-blue-700 text-xs font-medium rounded-lg border border-blue-200 hover:bg-blue-100 transition">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                {{ __('Add Row') }}
            </button>
        </div>
        <div class="text-xs text-gray-500">
            💡 {{ __('Click any cell to edit • Double-click to confirm changes') }}
        </div>
    </div>

    <!-- Scrollable Table Container -->
    <div class="overflow-x-auto rounded-lg border border-gray-300 shadow-sm">
        <table class="w-full border-collapse">
            <thead class="bg-gray-100 sticky top-0 z-10">
                <tr class="border-b border-gray-300">
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 bg-gray-100 w-10">
                        <input type="checkbox" id="selectAll" onchange="toggleSelectAll()" class="rounded border-gray-300">
                    </th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 bg-gray-100 w-10">#</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 bg-gray-100 min-w-32">
                        {{ __('First Name') }} <span class="text-red-500">*</span>
                    </th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 bg-gray-100 min-w-32">
                        {{ __('Last Name') }} <span class="text-red-500">*</span>
                    </th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 bg-gray-100 min-w-40">
                        {{ __('Username') }} <span class="text-red-500">*</span>
                    </th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 bg-gray-100 min-w-28">
                        {{ __('Type') }} <span class="text-red-500">*</span>
                    </th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 bg-gray-100 min-w-28">
                        {{ __('Department') }}
                    </th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 bg-gray-100 min-w-24">
                        {{ __('Level') }}
                    </th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 bg-gray-100 min-w-40">
                        {{ __('Assign Role') }}
                    </th>
                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-700 bg-gray-100 w-20">
                        {{ __('Status') }}
                    </th>
                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-700 bg-gray-100 w-16">
                        {{ __('Actions') }}
                    </th>
                </tr>
            </thead>
            <tbody id="previewTableBody" class="divide-y divide-gray-200">
                <!-- Rows will be populated by JavaScript -->
            </tbody>
        </table>
    </div>

    <!-- Empty State -->
    <div id="emptyState" class="text-center py-12 bg-gray-50 rounded-lg border border-dashed border-gray-300">
        <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
        </svg>
        <p class="text-gray-600 font-medium">{{ __('No data to preview') }}</p>
        <p class="text-sm text-gray-500">{{ __('Upload a CSV file to see preview here') }}</p>
    </div>

    <!-- Summary Before Import -->
    <div class="bg-indigo-50 border-l-4 border-indigo-500 p-4 rounded">
        <p class="font-semibold text-indigo-900 mb-2">{{ __('Ready to Import?') }}</p>
        <ul class="text-sm text-indigo-800 space-y-1">
            <li>✓ {{ __('Review all data above before proceeding') }}</li>
            <li>✓ {{ __('Fix any issues or delete problematic rows') }}</li>
            <li>✓ {{ __('Click the Import button when ready') }}</li>
        </ul>
    </div>
</div>

<script>
// Global preview data storage
let previewData = [];
let selectedRows = new Set();

// Parse CSV file and show preview
function parseCSVAndShowPreview(file) {
    const reader = new FileReader();
    reader.onload = function(e) {
        try {
            const csv = e.target.result;
            const lines = csv.trim().split('\n');
            
            if (lines.length < 2) {
                showStatusMessage('{{ __("CSV file must contain headers and at least one row") }}', 'error');
                return;
            }

            // Parse headers
            const headers = lines[0].split(',').map(h => h.trim());
            
            // Parse rows
            previewData = [];
            const errors = [];
            
            for (let i = 1; i < lines.length; i++) {
                if (lines[i].trim() === '') continue;
                
                const values = parseCSVLine(lines[i]);
                const row = {};
                
                headers.forEach((header, index) => {
                    row[header] = values[index] || '';
                });
                
                // Validate required fields
                const rowErrors = validateRow(row, i);
                row._errors = rowErrors;
                row._id = Date.now() + Math.random();
                row._selected = false;
                
                previewData.push(row);
                if (rowErrors.length > 0) {
                    errors.push(...rowErrors.map(e => `Row ${i}: ${e}`));
                }
            }

            // Update UI
            updatePreviewTable();
            updatePreviewStats(errors);
            
            if (previewData.length === 0) {
                showStatusMessage('{{ __("No valid rows found in CSV file") }}', 'error');
            } else {
                document.getElementById('previewContainer').classList.remove('hidden');
            }
        } catch (error) {
            showStatusMessage('{{ __("Error parsing CSV file: ") }}' + error.message, 'error');
        }
    };
    reader.readAsText(file);
}

function parseCSVLine(line) {
    const result = [];
    let current = '';
    let insideQuotes = false;

    for (let i = 0; i < line.length; i++) {
        const char = line[i];
        const nextChar = line[i + 1];

        if (char === '"') {
            insideQuotes = !insideQuotes;
        } else if (char === ',' && !insideQuotes) {
            result.push(current.trim());
            current = '';
        } else {
            current += char;
        }
    }
    result.push(current.trim());
    return result;
}

function validateRow(row, lineNumber) {
    const errors = [];

    if (!row.first_name || row.first_name.trim() === '') {
        errors.push('{{ __("first_name is required") }}');
    }
    if (!row.last_name || row.last_name.trim() === '') {
        errors.push('{{ __("last_name is required") }}');
    }
    if (!row.username || row.username.trim() === '') {
        errors.push('{{ __("username is required") }}');
    } else if (!/^[a-zA-Z0-9._-]{3,50}$/.test(row.username.trim())) {
        errors.push('{{ __("username must be 3-50 chars, alphanumeric with dots, underscores, hyphens") }}');
    }
    if (!row.type || row.type.trim() === '') {
        errors.push('{{ __("type is required") }}');
    }

    return errors;
}

function updatePreviewTable() {
    const tbody = document.getElementById('previewTableBody');
    const emptyState = document.getElementById('emptyState');
    
    if (previewData.length === 0) {
        tbody.innerHTML = '';
        emptyState.classList.remove('hidden');
        return;
    }

    emptyState.classList.add('hidden');
    
    tbody.innerHTML = previewData.map((row, index) => {
        const hasErrors = row._errors && row._errors.length > 0;
        return `
            <tr class="border-b border-gray-200 hover:bg-gray-50 transition ${hasErrors ? 'bg-red-50' : ''}">
                <td class="px-4 py-3">
                    <input type="checkbox" class="row-select rounded border-gray-300" data-row-id="${row._id}" onchange="updateRowSelection('${row._id}')">
                </td>
                <td class="px-4 py-3 text-xs text-gray-500 font-medium">${index + 1}</td>
                <td class="px-4 py-3">
                    <input type="text" class="editable-cell w-full px-2 py-1 border border-gray-200 rounded text-xs focus:ring-2 focus:ring-indigo-500 focus:border-transparent" 
                           data-field="first_name" data-row-id="${row._id}" value="${escapeHtml(row.first_name || '')}" onchange="updateCell('${row._id}', 'first_name', this.value)">
                </td>
                <td class="px-4 py-3">
                    <input type="text" class="editable-cell w-full px-2 py-1 border border-gray-200 rounded text-xs focus:ring-2 focus:ring-indigo-500 focus:border-transparent" 
                           data-field="last_name" data-row-id="${row._id}" value="${escapeHtml(row.last_name || '')}" onchange="updateCell('${row._id}', 'last_name', this.value)">
                </td>
                <td class="px-4 py-3">
                    <input type="text" class="editable-cell w-full px-2 py-1 border border-gray-200 rounded text-xs focus:ring-2 focus:ring-indigo-500 focus:border-transparent" 
                           data-field="username" data-row-id="${row._id}" value="${escapeHtml(row.username || '')}" onchange="updateCell('${row._id}', 'username', this.value)">
                </td>
                <td class="px-4 py-3">
                    <input type="text" class="editable-cell w-full px-2 py-1 border border-gray-200 rounded text-xs focus:ring-2 focus:ring-indigo-500 focus:border-transparent" 
                           placeholder="User" data-field="type" data-row-id="${row._id}" value="${escapeHtml(row.type || '')}" onchange="updateCell('${row._id}', 'type', this.value)">
                </td>
                <td class="px-4 py-3">
                    <input type="text" class="editable-cell w-full px-2 py-1 border border-gray-200 rounded text-xs focus:ring-2 focus:ring-indigo-500 focus:border-transparent" 
                           data-field="department_id" data-row-id="${row._id}" value="${escapeHtml(row.department_id || '')}" onchange="updateCell('${row._id}', 'department_id', this.value)">
                </td>
                <td class="px-4 py-3">
                    <input type="text" class="editable-cell w-full px-2 py-1 border border-gray-200 rounded text-xs focus:ring-2 focus:ring-indigo-500 focus:border-transparent" 
                           placeholder="Low" data-field="user_level" data-row-id="${row._id}" value="${escapeHtml(row.user_level || '')}" onchange="updateCell('${row._id}', 'user_level', this.value)">
                </td>
                <td class="px-4 py-3">
                    <input type="text" class="editable-cell w-full px-2 py-1 border border-gray-200 rounded text-xs focus:ring-2 focus:ring-indigo-500 focus:border-transparent" 
                           data-field="assign_role" data-row-id="${row._id}" value="${escapeHtml(row.assign_role || '')}" onchange="updateCell('${row._id}', 'assign_role', this.value)">
                </td>
                <td class="px-4 py-3 text-center">
                    ${hasErrors ? `<span class="inline-flex items-center px-2 py-1 bg-red-100 text-red-800 text-xs font-medium rounded">⚠ Error</span>` : `<span class="inline-flex items-center px-2 py-1 bg-green-100 text-green-800 text-xs font-medium rounded">✓ Valid</span>`}
                </td>
                <td class="px-4 py-3 text-center">
                    <button type="button" onclick="deleteRow('${row._id}')" class="text-red-600 hover:text-red-800 transition" title="{{ __('Delete row') }}">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                    </button>
                </td>
            </tr>
            ${hasErrors ? `
                <tr class="bg-red-50 border-b border-red-200">
                    <td colspan="11" class="px-4 py-2">
                        <p class="text-xs text-red-700 font-medium">⚠ Issues:</p>
                        <ul class="text-xs text-red-600 space-y-1 mt-1">
                            ${row._errors.map(err => `<li>• ${err}</li>`).join('')}
                        </ul>
                    </td>
                </tr>
            ` : ''}
        `;
    }).join('');
}

function updatePreviewStats(errors) {
    const totalRecords = previewData.length;
    const invalidRecords = previewData.filter(r => r._errors && r._errors.length > 0).length;
    const validRecords = totalRecords - invalidRecords;

    document.getElementById('totalRecords').textContent = totalRecords;
    document.getElementById('validRecords').textContent = validRecords;
    document.getElementById('recordCount').textContent = totalRecords;
    document.getElementById('issuesCount').textContent = errors.length;

    if (errors.length > 0) {
        document.getElementById('errorSummary').classList.remove('hidden');
        document.getElementById('errorList').innerHTML = errors.map(err => `<li>• ${err}</li>`).join('');
    } else {
        document.getElementById('errorSummary').classList.add('hidden');
    }
}

function updateCell(rowId, field, value) {
    const row = previewData.find(r => r._id === rowId);
    if (row) {
        row[field] = value;
        row._errors = validateRow(row, previewData.indexOf(row) + 1);
        updatePreviewTable();
    }
}

function deleteRow(rowId) {
    previewData = previewData.filter(r => r._id !== rowId);
    selectedRows.delete(rowId);
    updatePreviewTable();
    updatePreviewStats([]);
}

function deleteAllRows() {
    if (confirm('{{ __("Are you sure you want to delete all rows?") }}')) {
        previewData = [];
        selectedRows.clear();
        updatePreviewTable();
        document.getElementById('previewContainer').classList.add('hidden');
    }
}

function addNewRow() {
    const newRow = {
        first_name: '',
        last_name: '',
        username: '',
        type: '',
        department_id: '',
        user_level: '',
        assign_role: '',
        _errors: ['{{ __("All required fields must be filled") }}'],
        _id: Date.now() + Math.random(),
        _selected: false
    };
    previewData.push(newRow);
    updatePreviewTable();
}

function toggleSelectAll() {
    const isChecked = document.getElementById('selectAll').checked;
    document.querySelectorAll('.row-select').forEach(checkbox => {
        checkbox.checked = isChecked;
        const rowId = checkbox.dataset.rowId;
        if (isChecked) {
            selectedRows.add(rowId);
        } else {
            selectedRows.delete(rowId);
        }
    });
}

function updateRowSelection(rowId) {
    const checkbox = document.querySelector(`input[data-row-id="${rowId}"]`);
    if (checkbox.checked) {
        selectedRows.add(rowId);
    } else {
        selectedRows.delete(rowId);
    }
}

function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
}

// Export preview data for final import
window.getPreviewData = function() {
    return previewData.filter(row => row._errors && row._errors.length === 0);
};

// Make functions globally available
window.parseCSVAndShowPreview = parseCSVAndShowPreview;
window.updateCell = updateCell;
window.deleteRow = deleteRow;
window.deleteAllRows = deleteAllRows;
window.addNewRow = addNewRow;
window.toggleSelectAll = toggleSelectAll;
window.updateRowSelection = updateRowSelection;
</script>
