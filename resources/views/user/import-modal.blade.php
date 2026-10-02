<div class="bg-white rounded-xl shadow-2xl overflow-hidden w-full max-w-6xl mx-auto">
    <!-- Modal Header -->
    <div class="bg-gradient-to-r from-indigo-500 to-purple-600 px-6 py-4 flex justify-between items-center">
        <div class="flex items-center space-x-3">
            <div class="bg-white bg-opacity-20 rounded-lg p-2">
                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
            </div>
            <h3 class="text-lg font-bold text-white">{{ __('Import Users from CSV') }}</h3>
        </div>
        <button onclick="closeImportModal()" class="text-white hover:text-gray-200 transition-colors duration-200 p-1 rounded-full hover:bg-white hover:bg-opacity-20">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>

    <!-- Modal Body -->
    <div class="p-6 space-y-6 max-h-[75vh] overflow-y-auto">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <button type="button" onclick="downloadUserImportTemplate()" class="inline-flex items-center justify-center px-4 py-2 bg-blue-100 text-blue-700 text-sm font-medium rounded-lg border border-blue-200 hover:bg-blue-200 transition">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
                {{ __('Download CSV Template') }}
            </button>

            <button type="button" onclick="downloadDepartmentLookup()" class="inline-flex items-center justify-center px-4 py-2 bg-amber-100 text-amber-700 text-sm font-medium rounded-lg border border-amber-200 hover:bg-amber-200 transition">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                {{ __('Download Department Lookup') }}
            </button>

            <button type="button" onclick="clearTestData()" class="inline-flex items-center justify-center px-4 py-2 bg-red-50 text-red-700 text-sm font-medium rounded-lg border border-red-200 hover:bg-red-100 transition">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z"/>
                </svg>
                {{ __('Clear Test Data') }}
            </button>
        </div>
        
        <!-- Info Box -->
        <div class="bg-blue-50 border-l-4 border-blue-500 p-4 rounded">
            <div class="flex items-start space-x-3">
                <svg class="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                </svg>
                <div class="space-y-1">
                    <p class="font-semibold text-blue-900">{{ __('CSV Import Instructions') }}</p>
                    <ul class="text-sm text-blue-800 space-y-1">
                        <li>✓ {{ __('Import up to 50 users per upload') }}</li>
                        <li>✓ {{ __('Default password will be set to: password') }}</li>
                        <li>✓ {{ __('Emails are auto-generated: username@klaes.ng') }}</li>
                        <li>✓ {{ __('Required columns: first_name, last_name, username, type') }}</li>
                        <li>✓ {{ __('Optional columns: department_id, user_level, assign_role') }}</li>
                        <li>✓ {{ __('Username must be unique (letters, numbers, dots, underscores, hyphens)') }}</li>
                        <li>✓ {{ __('Users will be marked as Active by default') }}</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Default Password Warning -->
        <div class="bg-amber-50 border-l-4 border-amber-500 p-4 rounded">
            <div class="flex items-start space-x-3">
                <svg class="w-5 h-5 text-amber-500 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                </svg>
                <div class="space-y-1">
                    <p class="font-semibold text-amber-900">{{ __('⚠ Important: Default Password') }}</p>
                    <p class="text-sm text-amber-800">{{ __('All imported users will have the default password:') }} <code class="bg-white px-2 py-1 rounded font-mono text-red-600">password</code></p>
                    <p class="text-sm text-amber-800">{{ __('Users should change their password on first login. You can edit user passwords after import.') }}</p>
                </div>
            </div>
        </div>

        <!-- Form -->
        <form id="csvImportForm" class="space-y-4">
            @csrf

            <!-- Environment Selection -->
            <div class="space-y-2">
                <label class="block text-sm font-medium text-gray-700">
                    {{ __('Environment') }}
                    <span class="text-red-500">*</span>
                </label>
                <select id="environment" name="environment" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent" required>
                    <option value="">{{ __('Select environment...') }}</option>
                    <option value="TEST">
                        🧪 {{ __('TEST') }} - {{ __('For testing purposes (can be cleared)') }}
                    </option>
                    <option value="PRO">
                        🚀 {{ __('PRO') }} - {{ __('For production use') }}
                    </option>
                </select>
                <p class="text-xs text-gray-600">{{ __('TEST users can be deleted using "Clear Test Data" button. PRO users are permanent.') }}</p>
            </div>

            <!-- File Upload -->
            <div class="space-y-2">
                <label class="block text-sm font-medium text-gray-700">
                    {{ __('CSV File') }}
                    <span class="text-red-500">*</span>
                </label>
                <div class="relative border-2 border-dashed border-gray-300 rounded-lg p-6 text-center hover:border-indigo-500 transition-colors cursor-pointer" id="dropZone">
                    <input type="file" id="csvFile" name="csv_file" accept=".csv,.txt" class="hidden" required>
                    
                    <svg class="w-12 h-12 text-gray-400 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    
                    <p class="text-gray-600 font-medium" id="fileName">{{ __('Click or drag CSV file here') }}</p>
                    <p class="text-xs text-gray-500 mt-1">{{ __('Max file size: 1MB') }}</p>
                </div>
            </div>

            <!-- CSV Format Example -->
            <details class="bg-gray-50 rounded-lg p-4 cursor-pointer">
                <summary class="font-medium text-gray-700 select-none">
                    {{ __('📋 View CSV Format Example') }}
                </summary>
                <div class="mt-3 space-y-3">
                    <p class="text-sm text-gray-600">{{ __('Your CSV file should look like this (Required fields marked with *)') }}</p>
                    <div class="bg-gray-800 text-gray-100 p-3 rounded font-mono text-xs overflow-x-auto">
                        <pre>first_name*,last_name*,username*,type*,department_id,user_level,assign_role
John,Doe,john.doe,User,1,High,Dashboard; GIS - Records
Jane,Smith,jane.smith,User,5,Low,ST - Overview; ST - Applications
Bob,Johnson,bob.johnson,User,2,High,Dashboard</pre>
                    </div>
                    <div class="bg-blue-50 border-l-4 border-blue-500 p-3 rounded">
                        <p class="text-sm text-blue-900"><strong>{{ __('Field Descriptions:') }}</strong></p>
                        <ul class="text-xs text-blue-800 space-y-1 mt-2">
                            <li><strong>first_name*</strong>: {{ __('User first name (required)') }}</li>
                            <li><strong>last_name*</strong>: {{ __('User last name (required)') }}</li>
                            <li><strong>username*</strong>: {{ __('Unique username 3-50 chars (required, alphanumeric, dots, underscores, hyphens)') }}</li>
                            <li><strong>type*</strong>: {{ __('User type: User, super admin, etc. (required)') }}</li>
                            <li><strong>department_id</strong>: {{ __('Department ID from lookup table - Download Department Lookup PDF ') }}</li>
                            <li><strong>user_level</strong>: {{ __('Numeric user level ') }}</li>
                            <li><strong>assign_role</strong>: {{ __('Semicolon-separated role names e.g., Dashboard, GIS - Records, ST - Overview ') }}</li>
                        </ul>
                        <p class="text-xs text-blue-800 mt-2"><strong>Note:</strong> {{ __('Email addresses are auto-generated in the format: username@klaes.ng') }}</p>
                    </div>
                </div>
            </details>

            <!-- Status Messages -->
            <div id="statusMessages" class="space-y-2"></div>

            <!-- CSV Import Preview Component -->
            @include('user.import-preview')

            <!-- Progress Bar -->
            <div id="progressContainer" class="hidden">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-sm font-medium text-gray-700">{{ __('Uploading...') }}</span>
                    <span id="progressPercent" class="text-sm text-gray-600">0%</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2">
                    <div id="progressBar" class="bg-indigo-600 h-2 rounded-full transition-all duration-300" style="width: 0%"></div>
                </div>
            </div>
        </form>
    </div>

    <!-- Modal Footer -->
    <div class="bg-gray-50 px-6 py-4 flex justify-end space-x-3 border-t border-gray-200">
        <button onclick="closeImportModal()" 
                class="inline-flex items-center px-4 py-2 bg-gray-300 hover:bg-gray-400 text-gray-800 text-sm font-medium rounded-lg transition-colors duration-200">
            {{ __('Cancel') }}
        </button>
        <button id="importBtn" type="button" onclick="submitImport()" 
                class="inline-flex items-center px-6 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition-colors duration-200">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
            </svg>
            {{ __('Import') }}
        </button>
    </div>
</div>

<script>
// File drag and drop
const dropZone = document.getElementById('dropZone');
const csvFile = document.getElementById('csvFile');
const fileName = document.getElementById('fileName');

dropZone.addEventListener('click', () => csvFile.click());

dropZone.addEventListener('dragover', (e) => {
    e.preventDefault();
    dropZone.classList.add('border-indigo-500', 'bg-indigo-50');
});

dropZone.addEventListener('dragleave', () => {
    dropZone.classList.remove('border-indigo-500', 'bg-indigo-50');
});

dropZone.addEventListener('drop', (e) => {
    e.preventDefault();
    dropZone.classList.remove('border-indigo-500', 'bg-indigo-50');
    
    const files = e.dataTransfer.files;
    if (files.length > 0) {
        csvFile.files = files;
        updateFileName();
    }
});

csvFile.addEventListener('change', updateFileName);

function updateFileName() {
    if (csvFile.files.length > 0) {
        fileName.textContent = csvFile.files[0].name;
        dropZone.classList.add('border-green-500', 'bg-green-50');
        
        // Parse and show preview
        setTimeout(() => {
            parseCSVAndShowPreview(csvFile.files[0]);
        }, 100);
    }
}

// Form submission
function submitImport() {
    const form = document.getElementById('csvImportForm');
    const environment = document.getElementById('environment').value;
    const statusMessages = document.getElementById('statusMessages');
    const progressContainer = document.getElementById('progressContainer');
    const progressBar = document.getElementById('progressBar');
    const progressPercent = document.getElementById('progressPercent');
    const importBtn = document.getElementById('importBtn');

    // Validate
    if (!environment) {
        showStatusMessage('{{ __("Please select an environment") }}', 'error');
        return;
    }

    // Use preview data
    if (!previewData || previewData.length === 0) {
        showStatusMessage('{{ __("Please upload and verify a CSV file") }}', 'error');
        return;
    }

    // Filter only valid records
    const validRecords = previewData.filter(row => !row._errors || row._errors.length === 0);
    if (validRecords.length === 0) {
        showStatusMessage('{{ __("No valid records to import. Please fix all errors in the preview table.") }}', 'error');
        return;
    }

    // Disable button and show progress
    importBtn.disabled = true;
    progressContainer.classList.remove('hidden');
    statusMessages.innerHTML = '';

    // Send preview data to server
    const payload = {
        environment: environment,
        records: validRecords,
        _token: document.querySelector('input[name="_token"]').value
    };

    // Remove internal fields
    payload.records = payload.records.map(record => {
        const clean = {};
        Object.keys(record).forEach(key => {
            if (!key.startsWith('_')) {
                clean[key] = record[key];
            }
        });
        return clean;
    });

    // Simulate progress
    let progress = 0;
    const progressInterval = setInterval(() => {
        if (progress < 90) {
            progress += Math.random() * 30;
            updateProgress(Math.min(progress, 90));
        }
    }, 500);

    fetch('{{ route("users.import.process") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value
        },
        body: JSON.stringify(payload)
    })
    .then(response => response.json())
    .then(data => {
        clearInterval(progressInterval);
        updateProgress(100);

        if (data.success) {
            showStatusMessage(
                `✓ ${data.message}`,
                'success'
            );

            if (data.errors && data.errors.length > 0) {
                const errorHtml = `<div class="bg-red-50 border-l-4 border-red-500 p-3 rounded">
                    <p class="font-medium text-red-900">{{ __('Errors found:') }}</p>
                    <ul class="text-sm text-red-800 mt-2 space-y-1">
                        ${data.errors.map(err => `<li>• ${err}</li>`).join('')}
                    </ul>
                </div>`;
                statusMessages.innerHTML += errorHtml;
            }

            // Reload table after 2 seconds
            setTimeout(() => {
                if (typeof location !== 'undefined') {
                    location.reload();
                }
            }, 2000);
        } else {
            showStatusMessage(data.message, 'error');
            if (Array.isArray(data.errors) && data.errors.length > 0) {
                appendErrorList(data.errors);
            }
            importBtn.disabled = false;
            progressContainer.classList.add('hidden');
        }
    })
    .catch(error => {
        clearInterval(progressInterval);
        showStatusMessage('{{ __("Upload failed: ") }}' + error.message, 'error');
        importBtn.disabled = false;
        progressContainer.classList.add('hidden');
    });
}

function updateProgress(percent) {
    const progressBar = document.getElementById('progressBar');
    const progressPercent = document.getElementById('progressPercent');
    progressBar.style.width = percent + '%';
    progressPercent.textContent = Math.round(percent) + '%';
}

function showStatusMessage(message, type) {
    const statusMessages = document.getElementById('statusMessages');
    const bgClass = type === 'success' ? 'bg-green-50 border-green-500' : 'bg-red-50 border-red-500';
    const textClass = type === 'success' ? 'text-green-900' : 'text-red-900';
    
    const html = `<div class="border-l-4 ${bgClass} p-3 rounded">
        <p class="${textClass} text-sm">${message}</p>
    </div>`;
    
    statusMessages.innerHTML += html;
}

function appendErrorList(errors) {
    if (!errors.length) {
        return;
    }

    const statusMessages = document.getElementById('statusMessages');
    const errorHtml = `<div class="bg-red-50 border-l-4 border-red-500 p-3 rounded">
        <p class="font-medium text-red-900">{{ __('Errors found:') }}</p>
        <ul class="text-sm text-red-800 mt-2 space-y-1">
            ${errors.map(err => `<li>• ${err}</li>`).join('')}
        </ul>
    </div>`;

    statusMessages.innerHTML += errorHtml;
}

window.downloadUserImportTemplate = function () {
    window.open('{{ route('users.import.template') }}', '_blank');
};

window.downloadDepartmentLookup = function () {
    window.open('{{ route('users.department.lookup-pdf') }}', '_blank');
};

window.closeImportModal = function () {
    if (window.currentModalId) {
        const modal = document.getElementById(window.currentModalId);
        if (modal) {
            modal.remove();
        }
        window.currentModalId = null;
        return;
    }

    const fallback = document.querySelector('.fixed.inset-0');
    if (fallback) {
        fallback.remove();
    }
};
</script>
