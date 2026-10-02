const config = window.fileSearchConfig ?? {};

const clampPerPage = (value) => {
    const numeric = Number(value) || Number(config.defaultPerPage) || 25;
    return Math.min(Math.max(numeric, 10), 100);
};

const formatNumber = (value) => {
    const numeric = Number(value);
    return Number.isFinite(numeric) ? numeric.toLocaleString() : '0';
};

const addEvent = (element, type, handler) => {
    if (element) {
        element.addEventListener(type, handler);
    }
};

const displayValue = (value) => {
    if (value === null || value === undefined) {
        return '—';
    }

    if (typeof value === 'number') {
        return Number.isFinite(value) ? value.toString() : '—';
    }

    const stringValue = String(value).trim();

    if (stringValue === '' || stringValue === '-') {
        return '—';
    }

    return stringValue;
};

const emptyToNull = (value) => {
    if (value === null || value === undefined) {
        return null;
    }

    const stringValue = String(value).trim();

    return stringValue === '' ? null : stringValue;
};

document.addEventListener('DOMContentLoaded', () => {
    if (!config.listUrl) {
        return;
    }

    const importButton = document.getElementById('file-search-import');
    const globalExportButton = document.getElementById('file-search-export');

    if (!config.importUrl && importButton) {
        importButton.disabled = true;
        importButton.classList.add('hidden');
    }

    const fallbackLabels = {
        duplicate: 'Duplicate Files',
        temporary: 'Temp Files',
        wrc: 'W/C/R Files',
    };

    const typeLabels = { ...(config.typeLabels || {}) };
    let typeOrder = Array.isArray(config.typeOrder) && config.typeOrder.length
        ? config.typeOrder.slice()
        : Object.keys(typeLabels);

    if (typeOrder.length === 0) {
        typeOrder = Object.keys(fallbackLabels);
    }

    typeOrder.forEach((type) => {
        if (!typeLabels[type]) {
            typeLabels[type] = fallbackLabels[type] || type;
        }
    });

    const defaultType = (config.defaultType && typeLabels[config.defaultType])
        ? config.defaultType
        : (typeOrder[0] || 'duplicate');

    const keywordInput = document.getElementById('file-search-keyword');
    const registrySelect = document.getElementById('file-search-registry');
    const perPageSelect = document.getElementById('file-search-per-page');
    const startDateInput = document.getElementById('file-search-start-date');
    const endDateInput = document.getElementById('file-search-end-date');
    const runButton = document.getElementById('file-search-run');
    const resetButton = document.getElementById('file-search-reset');
    const addButton = document.getElementById('file-search-add');
    const errorBanner = document.getElementById('file-search-error');

    const recordModal = document.getElementById('record-modal');
    const modalBackdrop = document.getElementById('modal-backdrop');
    const modalTitle = document.getElementById('modal-title');
    const modalClose = document.getElementById('modal-close');
    const modalCancel = document.getElementById('modal-cancel');
    const recordForm = document.getElementById('record-form');
    const formError = document.getElementById('form-error');
    const submitText = document.getElementById('submit-text');
    const submitLoading = document.getElementById('submit-loading');
    const modalSubmit = document.getElementById('modal-submit');

    const recordId = document.getElementById('record-id');
    const recordOriginalType = document.getElementById('record-original-type');
    const recordTypeSelect = document.getElementById('record-type');
    const recordRegistry = document.getElementById('record-registry');
    const recordFileNumber = document.getElementById('record-file-number');
    const recordFileTitle = document.getElementById('record-file-title');
    const recordFileTitleSingle = document.getElementById('record-file-title-single');
    const recordFileList = document.getElementById('record-file-list');
    const recordFileAdd = document.getElementById('record-file-add');
    const singleFileFields = document.getElementById('single-file-fields');
    const multiFileFields = document.getElementById('multi-file-fields');
    const sharedFileNumberField = document.getElementById('shared-file-number-field');
    const recordFileListLabel = document.getElementById('record-file-list-label');
    const recordFileListHelp = document.getElementById('record-file-list-help');
    const recordFileAddLabel = document.getElementById('record-file-add-label');
    const recordPlotNumber = document.getElementById('record-plot-number');
    const recordLocation = document.getElementById('record-location');
    const recordCommentSelect = document.getElementById('record-comment');
    const recordCommentNote = document.getElementById('record-comment-note');

    const filenoSelectorBtn = document.getElementById('fileno-selector-btn');

    const deleteModal = document.getElementById('delete-modal');
    const deleteBackdrop = document.getElementById('delete-backdrop');
    const deleteCancel = document.getElementById('delete-cancel');
    const deleteConfirm = document.getElementById('delete-confirm');
    const deleteRecordId = document.getElementById('delete-record-id');
    const deleteRecordType = document.getElementById('delete-record-type');
    const importModal = document.getElementById('import-modal');
    const importBackdrop = document.getElementById('import-backdrop');
    const importClose = document.getElementById('import-close');
    const importCancel = document.getElementById('import-cancel');
    const importForm = document.getElementById('import-form');
    const importTypeSelect = document.getElementById('import-type');
    const importModeSelect = document.getElementById('import-mode');
    const importFileInput = document.getElementById('import-file');
    const importError = document.getElementById('import-error');
    const importSubmitButton = document.getElementById('import-submit');
    const importSubmitText = document.getElementById('import-submit-text');
    const importSubmitLoading = document.getElementById('import-submit-loading');
    const importTemplateLink = document.getElementById('import-template-link');

    const sharedState = {
        search: '',
        registry: '',
        perPage: clampPerPage(perPageSelect?.value ?? config.defaultPerPage),
        startDate: startDateInput?.value?.trim() ?? '',
        endDate: endDateInput?.value?.trim() ?? '',
    };

    const syncDateRangeState = () => {
        sharedState.startDate = startDateInput?.value?.trim() ?? '';
        sharedState.endDate = endDateInput?.value?.trim() ?? '';
    };

    if (importTemplateLink) {
        if (config.templateUrl) {
            importTemplateLink.href = config.templateUrl;
        } else {
            importTemplateLink.classList.add('hidden');
        }
    }

    if (perPageSelect) {
        perPageSelect.value = sharedState.perPage.toString();
    }

    syncDateRangeState();

    const tableManagers = {};
    let activeTab = defaultType;

    function ensureSelectOption(select, value) {
        if (!select) {
            return;
        }

        const normalized = value == null ? '' : String(value).trim();

        if (normalized === '') {
            return;
        }

        const exists = Array.from(select.options).some((option) => option.value === normalized);

        if (!exists) {
            const option = new Option(normalized, normalized, true, true);
            option.dataset.custom = 'true';
            select.add(option);
        }
    }

    function resetCustomOptions(select) {
        if (!select) {
            return;
        }

        Array.from(select.options)
            .filter((option) => option.dataset && option.dataset.custom === 'true')
            .forEach((option) => option.remove());
    }

    /* ── Files repeater (Add mode) ────────────────────────────────────────────
     |
     | One block per FILE, not per title.
     |
     | This replaced a list of bare title strings that all hung off ONE file
     | number. Several distinct files could only be recorded by reopening the
     | card once each, and a duplicate PAIR -- the commonest thing this register
     | holds -- could not be entered as the two separate files it actually is.
     |
     | Registry and category are shared and read from the header above; the file
     | number, title, plot and location belong to the individual file. Each block
     | is saved as its own record.
     |
     | Edit mode does not use any of this: editing touches one existing row and
     | keeps the single set of fields, in #single-file-fields.
     */

    /** Duplicate entries share one file number; other bulk categories do not. */
    function usesSharedFileNumber() {
        return !recordId?.value && recordCommentSelect?.value === 'duplicate';
    }

    function fileRowInput(cls, placeholder) {
        const input = document.createElement('input');
        input.type = 'text';
        input.className = cls + ' w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100';
        input.placeholder = placeholder;
        return input;
    }

    function fileRowField(labelText, input, extra) {
        const wrap = document.createElement('div');
        const label = document.createElement('label');
        label.className = 'block text-xs font-medium text-slate-600 mb-1';
        label.innerHTML = labelText;
        wrap.appendChild(label);
        wrap.appendChild(extra || input);
        return wrap;
    }

    /** Renumber the blocks and hide Remove when only one is left. */
    function updateFileRowChrome() {
        if (!recordFileList) {
            return;
        }

        const rows = recordFileList.querySelectorAll('.record-file-row');
        rows.forEach((row, i) => {
            const badge = row.querySelector('.record-file-index');
            if (badge) {
                badge.textContent = String(i + 1);
            }
            const removeBtn = row.querySelector('.record-file-remove');
            if (removeBtn) {
                removeBtn.classList.toggle('hidden', rows.length <= 1);
                removeBtn.disabled = rows.length <= 1;
            }
        });
    }

    function addFileRow(values = {}) {
        if (!recordFileList) {
            return null;
        }

        const row = document.createElement('div');
        row.className = 'record-file-row rounded-2xl border border-slate-200 bg-slate-50/50 p-4 space-y-3';

        const sharedNumber = usesSharedFileNumber();
        const head = document.createElement('div');
        head.className = 'flex items-center justify-between';
        head.innerHTML = `<p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">${sharedNumber ? 'Title' : 'File'} <span class="record-file-index">1</span></p>`;

        const removeButton = document.createElement('button');
        removeButton.type = 'button';
        removeButton.className = 'record-file-remove rounded-lg border border-slate-200 bg-white px-3 py-1 text-xs font-semibold text-slate-600 hover:border-rose-200 hover:bg-rose-50 hover:text-rose-700';
        removeButton.textContent = 'Remove';
        removeButton.addEventListener('click', () => {
            row.remove();
            if (recordFileList.children.length === 0) {
                addFileRow({});
            }
            updateFileRowChrome();
        });
        head.appendChild(removeButton);

        /*
         | Picked, never typed.
         |
         | The file number has to be one the registry already holds, and the global
         | picker is the only thing that knows which those are. A typed box invites a
         | number that looks right and matches nothing -- and this register exists
         | precisely to track files whose numbering has gone wrong, so a mistyped one
         | here is worse than useless.
         |
         | readOnly rather than disabled: a disabled input is skipped by the row reader
         | and greys out the value the officer just chose.
         */
        const numberInput = fileRowInput('record-file-number-input', 'Click to select file number');
        numberInput.classList.remove('rounded-xl');
        numberInput.classList.add('rounded-l-xl', 'cursor-pointer');
        numberInput.readOnly = true;
        numberInput.title = 'Select from existing file numbers';
        numberInput.value = values.file_number || '';
        // Click only. A focus handler as well would fire twice on one click, and
        // would re-open the picker when focus returns to the field after it closes.
        numberInput.addEventListener('click', () => openFileNoLookup(numberInput));

        const lookupButton = document.createElement('button');
        lookupButton.type = 'button';
        lookupButton.className = 'record-file-lookup flex items-center px-3 border border-l-0 border-slate-200 bg-white text-slate-500 hover:text-blue-600 hover:bg-blue-50 transition-colors rounded-r-xl';
        lookupButton.title = 'Select from existing file numbers';
        lookupButton.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>';
        lookupButton.addEventListener('click', () => openFileNoLookup(numberInput));

        const numberGroup = document.createElement('div');
        numberGroup.className = 'relative flex';
        numberGroup.appendChild(numberInput);
        numberGroup.appendChild(lookupButton);

        const titleInput = fileRowInput('record-file-title-input', 'Enter file title');
        titleInput.value = values.file_title || '';
        titleInput.required = sharedNumber;

        const plotInput = fileRowInput('record-file-plot-input', 'e.g., Plot 123');
        plotInput.value = values.plot_number || '';

        const locationInput = fileRowInput('record-file-location-input', 'Enter location');
        locationInput.value = values.location || '';

        const topGrid = document.createElement('div');
        topGrid.className = sharedNumber ? 'grid grid-cols-1 gap-3' : 'grid grid-cols-1 sm:grid-cols-2 gap-3';
        if (!sharedNumber) {
            topGrid.appendChild(fileRowField('File Number <span class="text-red-500">*</span>', numberInput, numberGroup));
        }
        topGrid.appendChild(fileRowField(sharedNumber ? 'File Title <span class="text-red-500">*</span>' : 'File Title', titleInput));

        const bottomGrid = document.createElement('div');
        bottomGrid.className = 'grid grid-cols-1 sm:grid-cols-2 gap-3';
        bottomGrid.appendChild(fileRowField('Plot Number', plotInput));
        bottomGrid.appendChild(fileRowField('Location', locationInput));

        row.appendChild(head);
        row.appendChild(topGrid);
        row.appendChild(bottomGrid);
        recordFileList.appendChild(row);

        updateFileRowChrome();

        return row;
    }

    function setFileRows(list) {
        if (!recordFileList) {
            return;
        }

        recordFileList.innerHTML = '';

        const rows = Array.isArray(list) && list.length ? list : [{}];
        rows.forEach((values) => addFileRow(values || {}));

        updateFileRowChrome();
    }

    /**
     * The captured files, as the API wants them. A block whose file number is
     * blank is dropped rather than rejected: an officer who adds a block and
     * changes their mind should not have to remove it to be able to save.
     */
    function getFileRows() {
        if (!recordFileList) {
            return [];
        }

        const sharedNumber = usesSharedFileNumber() ? (recordFileNumber?.value.trim() ?? '') : null;

        return Array.from(recordFileList.querySelectorAll('.record-file-row'))
            .map((row) => ({
                file_number: sharedNumber ?? (row.querySelector('.record-file-number-input')?.value.trim() ?? ''),
                file_title: row.querySelector('.record-file-title-input')?.value.trim() ?? '',
                plot_number: row.querySelector('.record-file-plot-input')?.value.trim() ?? '',
                location: row.querySelector('.record-file-location-input')?.value.trim() ?? '',
            }))
            .filter((file) => usesSharedFileNumber()
                ? file.file_number !== '' && (file.file_title !== '' || file.plot_number !== '' || file.location !== '')
                : file.file_number !== '');
    }

    /**
     * Open the global file-number picker for one input.
     *
     * The shape of the call -- initialValue + callback, and the fallback chain over
     * the several names the picker returns a number under -- is the existing single
     * field's, kept identical so both go through the same contract.
     */
    function openFileNoLookup(targetInput) {
        if (typeof GlobalFileNoModal === 'undefined' || typeof GlobalFileNoModal.open !== 'function') {
            console.warn('GlobalFileNoModal not available');
            return;
        }

        GlobalFileNoModal.open({
            initialValue: targetInput?.value || '',
            callback: (fileData) => {
                if (!fileData) return;

                const selectedNumber =
                    fileData.fileNumber ||
                    fileData.fileno ||
                    fileData.mlsFNo ||
                    fileData.kangisFileNo ||
                    fileData.NewKANGISFileno ||
                    (fileData.record && (fileData.record.file_number || fileData.record.mlsFNo || fileData.record.kangisFileNo)) ||
                    '';

                if (targetInput && selectedNumber) {
                    targetInput.value = selectedNumber;
                    targetInput.dispatchEvent(new Event('input', { bubbles: true }));
                    targetInput.dispatchEvent(new Event('change', { bubbles: true }));
                }
            },
        });
    }

    /** Which half of the card is live. Add captures many files; edit, exactly one. */
    function setFormMode(isEdit) {
        if (singleFileFields) {
            singleFileFields.classList.toggle('hidden', !isEdit);
        }
        if (multiFileFields) {
            multiFileFields.classList.toggle('hidden', isEdit);
        }
        if (recordFileNumber) {
            recordFileNumber.required = !!isEdit || usesSharedFileNumber();
        }
        if (sharedFileNumberField) {
            sharedFileNumberField.classList.toggle('hidden', !isEdit && !usesSharedFileNumber());
        }
        if (recordFileListLabel) {
            recordFileListLabel.textContent = usesSharedFileNumber() ? 'Duplicate Files' : 'Files';
        }
        if (recordFileListHelp) {
            recordFileListHelp.textContent = usesSharedFileNumber()
                ? 'Every title below will be saved against the selected file number above.'
                : 'Each file below is saved as its own record, under the registry and category above.';
        }
        if (recordFileAddLabel) {
            recordFileAddLabel.textContent = 'Add File';
        }
    }

    if (recordFileAdd) {
        recordFileAdd.addEventListener('click', () => {
            const row = addFileRow({});
            row?.querySelector(usesSharedFileNumber() ? '.record-file-title-input' : '.record-file-number-input')?.focus();
        });
    }

    if (recordFileList && recordFileList.children.length === 0) {
        setFileRows([{}]);
    }

    function switchTab(tabKey) {
        if (!typeLabels[tabKey] || activeTab === tabKey) {
            return;
        }

        // Update tab buttons
        document.querySelectorAll('.tab-button').forEach(btn => {
            const isActive = btn.dataset.tab === tabKey;
            btn.classList.toggle('active', isActive);
            btn.classList.toggle('bg-white', isActive);
            btn.classList.toggle('text-emerald-900', isActive);
            btn.classList.toggle('border-b-2', isActive);
            btn.classList.toggle('border-emerald-500', isActive);
            btn.classList.toggle('text-emerald-700', !isActive);
            btn.classList.toggle('hover:text-emerald-900', !isActive);
            btn.classList.toggle('hover:bg-emerald-100', !isActive);
            
            // Update count badge styling
            const countBadge = btn.querySelector('.tab-count');
            if (countBadge) {
                countBadge.classList.toggle('bg-emerald-100', isActive);
                countBadge.classList.toggle('text-emerald-700', isActive);
                countBadge.classList.toggle('bg-emerald-200', !isActive);
                countBadge.classList.toggle('text-emerald-600', !isActive);
            }
        });

        // Update tab content visibility
        document.querySelectorAll('.tab-content').forEach(content => {
            const isActive = content.dataset.tabContent === tabKey;
            content.classList.toggle('active', isActive);
            content.classList.toggle('hidden', !isActive);
        });

        activeTab = tabKey;

        // Load data for the newly active tab if it hasn't been loaded yet
        const manager = tableManagers[tabKey];
        if (manager && !manager.hasLoaded) {
            manager.fetch({ resetPage: true });
        }
    }

    function updateTabCount(type, count) {
        const countElement = document.getElementById(`count-${type}`);
        if (countElement) {
            countElement.textContent = formatNumber(count || 0);
        }
    }

    function renderGlobalError(message) {
        if (!errorBanner) {
            return;
        }

        if (!message) {
            errorBanner.classList.add('hidden');
            errorBanner.textContent = '';
            return;
        }

        errorBanner.textContent = message;
        errorBanner.classList.remove('hidden');
    }

    function setSubmitting(submitting) {
        if (modalSubmit) {
            modalSubmit.disabled = submitting;
        }
        if (submitText) {
            submitText.classList.toggle('hidden', submitting);
        }
        if (submitLoading) {
            submitLoading.classList.toggle('hidden', !submitting);
        }
    }

    function renderFormError(message) {
        if (!formError) {
            return;
        }

        if (!message) {
            formError.classList.add('hidden');
            formError.textContent = '';
            return;
        }

        formError.textContent = message;
        formError.classList.remove('hidden');
    }

    function showModal() {
        if (recordModal) {
            recordModal.classList.remove('hidden');
        }
    }

    function hideModal() {
        if (recordModal) {
            recordModal.classList.add('hidden');
        }
        resetForm();
    }

    function showDeleteModal(id, recordType) {
        if (deleteModal && deleteRecordId) {
            deleteRecordId.value = id;
            if (deleteRecordType) {
                deleteRecordType.value = recordType || defaultType;
            }
            deleteModal.classList.remove('hidden');
        }
    }

    function hideDeleteModal() {
        if (deleteModal) {
            deleteModal.classList.add('hidden');
        }
        if (deleteRecordId) {
            deleteRecordId.value = '';
        }
        if (deleteRecordType) {
            deleteRecordType.value = '';
        }
    }

    function showImportModal() {
        if (importModal) {
            importModal.classList.remove('hidden');
        }
        if (importTypeSelect) {
            importTypeSelect.value = activeTab || defaultType;
        }
        if (importModeSelect) {
            importModeSelect.value = 'append';
        }
        if (importFileInput) {
            importFileInput.value = '';
        }
        renderImportError('');
    }

    function hideImportModal() {
        if (importModal) {
            importModal.classList.add('hidden');
        }
        if (importForm) {
            importForm.reset();
        }
        renderImportError('');
        setImportSubmitting(false);
    }

    function setImportSubmitting(submitting) {
        if (!importSubmitButton || !importSubmitText || !importSubmitLoading) {
            return;
        }

        importSubmitButton.disabled = submitting;
        importSubmitText.classList.toggle('hidden', submitting);
        importSubmitLoading.classList.toggle('hidden', !submitting);
    }

    function renderImportError(message) {
        if (!importError) {
            return;
        }

        if (!message) {
            importError.classList.add('hidden');
            importError.textContent = '';
            return;
        }

        importError.textContent = message;
        importError.classList.remove('hidden');
    }

    function resetForm() {
        if (recordForm) {
            recordForm.reset();
        }
        if (recordId) {
            recordId.value = '';
        }
        if (recordOriginalType) {
            recordOriginalType.value = '';
        }
        if (recordFileTitleSingle) {
            recordFileTitleSingle.value = '';
        }
        if (recordFileTitle) {
            recordFileTitle.value = '';
        }
        if (recordRegistry) {
            resetCustomOptions(recordRegistry);
            recordRegistry.value = '';
        }
        const initialType = activeTab || defaultType;
        if (recordCommentSelect) {
            recordCommentSelect.value = initialType;
        }
        if (recordTypeSelect) {
            recordTypeSelect.value = initialType;
        }
        setFileRows([{}]);
        setFormMode(false);
        if (recordCommentNote) {
            recordCommentNote.value = '';
        }
        if (modalTitle) {
            modalTitle.textContent = 'Add Record';
        }
        if (submitText) {
            submitText.textContent = 'Save Record';
        }
        renderFormError('');
    }

    function openAddModal() {
        resetForm();
        showModal();
    }

    async function openEditModal(id, fallbackType) {
        resetForm();

        if (modalTitle) {
            modalTitle.textContent = 'Edit Record';
        }
        if (submitText) {
            submitText.textContent = 'Update Record';
        }
        if (recordId) {
            recordId.value = id;
        }
        if (recordOriginalType) {
            recordOriginalType.value = fallbackType || defaultType;
        }

        try {
            const url = config.showUrl.replace('__ID__', id);
            const response = await fetch(url, {
                headers: {
                    Accept: 'application/json',
                },
            });

            if (!response.ok) {
                throw new Error('Failed to load record.');
            }

            const payload = await response.json();

            if (!payload?.success) {
                throw new Error(payload?.message ?? 'Failed to load record.');
            }

            const data = payload.data ?? {};

            const registryValue = data.registry == null ? '' : String(data.registry).trim();
            ensureSelectOption(recordRegistry, registryValue);

            if (recordRegistry) {
                recordRegistry.value = registryValue;
            }
            if (recordFileNumber) {
                recordFileNumber.value = data.file_number ?? '';
            }
            /* Editing one row: show the single fields and fill the one title.
               Rows written before this change can still hold several titles in
               one newline-separated column, so those are joined back onto one
               line rather than silently losing all but the first. */
            setFormMode(true);
            const existingTitle = typeof data.file_title === 'string'
                ? data.file_title.split(/\r?\n/).map((v) => v.trim()).filter((v) => v !== '').join(' / ')
                : '';
            if (recordFileTitleSingle) {
                recordFileTitleSingle.value = existingTitle;
            }
            if (recordFileTitle) {
                recordFileTitle.value = existingTitle;
            }
            if (recordPlotNumber) {
                recordPlotNumber.value = data.plot_number ?? '';
            }
            if (recordLocation) {
                recordLocation.value = data.location ?? '';
            }
            if (recordCommentNote) {
                recordCommentNote.value = data.comment ?? '';
            }

            const resolvedType = data.comment_label || data.record_type || fallbackType || defaultType;

            if (recordCommentSelect) {
                recordCommentSelect.value = resolvedType;
            }
            if (recordTypeSelect) {
                recordTypeSelect.value = resolvedType;
            }
            if (recordOriginalType) {
                recordOriginalType.value = resolvedType;
            }

            showModal();
        } catch (error) {
            renderGlobalError(error.message ?? 'Failed to load record for editing.');
        }
    }

    function refreshTable(type, { resetPage = false } = {}) {
        const manager = tableManagers[type];
        if (!manager) {
            return;
        }

        if (resetPage) {
            manager.state.page = 1;
        }

        manager.fetch({ resetPage });
    }

    function refreshAllTables({ resetPage = false } = {}) {
        Object.values(tableManagers).forEach((manager) => {
            if (resetPage) {
                manager.state.page = 1;
            }
            manager.fetch({ resetPage });
        });
    }

    function exportTableDataAsCSV(tableBodyId, tableLabel) {
        const tbody = document.getElementById(tableBodyId);
        if (!tbody) {
            renderGlobalError('Table not found.');
            return;
        }

        const rows = Array.from(tbody.querySelectorAll('tr'));
        const emptyRow = rows.find(r => r.querySelector('td[colspan]'));

        if (rows.length === 0 || (rows.length === 1 && emptyRow)) {
            renderGlobalError('No data to export. Run a search first.');
            return;
        }

        const headers = ['SN', 'Registry', 'File Number', 'File Title', 'Plot Number', 'Location', 'Category', 'Comment', 'Created At'];
        const csvRows = [headers.map(h => `"${h}"`).join(',')];

        rows.forEach((row, idx) => {
            if (row.querySelector('td[colspan]')) {
                return;
            }

            const cells = row.querySelectorAll('td');
            if (cells.length === 0) return;

            const rowData = Array.from(cells).map(cell => {
                const text = cell.textContent.trim();
                return `"${text.replace(/"/g, '""')}"`;
            });

            if (rowData.length > 0) {
                csvRows.push(rowData.join(','));
            }
        });

        if (csvRows.length === 1) {
            renderGlobalError('No data rows to export.');
            return;
        }

        const csv = csvRows.join('\n');
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);

        const timestamp = new Date().toISOString().slice(0, 10);
        link.href = url;
        link.download = `${tableLabel}-export-${timestamp}.csv`;
        link.click();
        URL.revokeObjectURL(url);

        renderGlobalError('');
    }

    function handleExport(type) {
        syncDateRangeState();

        const exportType = type || activeTab || defaultType;

        if (!config.exportUrl) {
            renderGlobalError('Export URL is not configured.');
            return;
        }

        // Read directly from inputs so we always have the latest values
        const searchVal    = keywordInput?.value?.trim() || '';
        const registryVal  = registrySelect?.value?.trim() || '';
        const startDateVal = startDateInput?.value?.trim() || '';
        const endDateVal   = endDateInput?.value?.trim() || '';

        const params = new URLSearchParams();
        params.set('type', exportType);

        if (searchVal)    params.set('search',     searchVal);
        if (registryVal)  params.set('registry',   registryVal);
        if (startDateVal) params.set('start_date', startDateVal);
        if (endDateVal)   params.set('end_date',   endDateVal);

        // Trigger browser file download via navigation — server returns a streamed CSV
        window.location.href = config.exportUrl + '?' + params.toString();
    }

    async function handleFormSubmit(event) {
        event.preventDefault();

        if (!recordForm || !recordCommentSelect || !recordTypeSelect) {
            return;
        }

        if (modalSubmit && modalSubmit.disabled) {
            return;
        }

        setSubmitting(true);
        renderFormError('');

        const id = recordId?.value;
        const isEdit = Boolean(id);
        const previousType = recordOriginalType?.value || null;
        const selectedCommentLabel = recordCommentSelect.value || defaultType;

        if (recordTypeSelect.value !== selectedCommentLabel) {
            recordTypeSelect.value = selectedCommentLabel;
        }

        /*
         | Two shapes, because the card does two jobs.
         |
         | EDIT touches one existing row and posts the flat fields it always did.
         | ADD posts `files[]` -- one entry per captured file -- and the server
         | writes one record each under the shared registry and category.
         */
        let formData;

        if (isEdit) {
            const singleTitle = recordFileTitleSingle?.value?.trim() ?? '';
            if (recordFileTitle) {
                recordFileTitle.value = singleTitle;
            }

            formData = {
                registry: recordRegistry?.value?.trim() ?? '',
                file_number: recordFileNumber?.value?.trim() ?? '',
                file_title: emptyToNull(singleTitle),
                plot_number: emptyToNull(recordPlotNumber?.value),
                location: emptyToNull(recordLocation?.value),
                comment: emptyToNull(recordCommentNote?.value),
                comment_label: selectedCommentLabel,
                record_type: selectedCommentLabel,
            };
        } else {
            const files = getFileRows();

            if (files.length === 0) {
                renderFormError(usesSharedFileNumber()
                    ? 'Select a file number and enter at least one duplicate title.'
                    : 'Enter at least one file number.');
                setSubmitting(false);
                return;
            }

            formData = {
                registry: recordRegistry?.value?.trim() ?? '',
                comment: emptyToNull(recordCommentNote?.value),
                comment_label: selectedCommentLabel,
                record_type: selectedCommentLabel,
                files: files.map((file) => ({
                    file_number: file.file_number,
                    file_title: emptyToNull(file.file_title),
                    plot_number: emptyToNull(file.plot_number),
                    location: emptyToNull(file.location),
                })),
            };
        }

        try {
            const url = isEdit ? config.updateUrl.replace('__ID__', id) : config.storeUrl;
            const method = isEdit ? 'PUT' : 'POST';

            const response = await fetch(url, {
                method,
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': config.csrfToken,
                },
                body: JSON.stringify(formData),
            });

            const payload = await response.json();

            if (!response.ok || !payload?.success) {
                if (payload?.errors) {
                    const firstError = Object.values(payload.errors)[0];
                    const message = Array.isArray(firstError) ? firstError[0] : firstError;
                    throw new Error(message);
                }
                throw new Error(payload?.message ?? 'Failed to save record.');
            }

            hideModal();
            renderGlobalError('');

            const newType = payload?.data?.record_type || selectedCommentLabel;

            const shouldResetNewTable = !isEdit;
            refreshTable(newType, { resetPage: shouldResetNewTable });

            if (previousType && previousType !== newType) {
                refreshTable(previousType, { resetPage: false });
            }

            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'success',
                    title: isEdit ? 'Record Updated' : 'Record Created',
                    text: payload.message ?? 'Operation completed successfully.',
                    timer: 2000,
                    showConfirmButton: false,
                });
            }
        } catch (error) {
            renderFormError(error.message ?? 'Failed to save record.');
        } finally {
            setSubmitting(false);
        }
    }

    async function handleImportSubmit(event) {
        event.preventDefault();

        if (!importForm || !config.importUrl) {
            return;
        }

        if (importSubmitButton && importSubmitButton.disabled) {
            return;
        }

        renderImportError('');

        const selectedType = importTypeSelect?.value || activeTab || defaultType;
        const modeValue = importModeSelect?.value || 'append';
        const file = importFileInput?.files && importFileInput.files[0] ? importFileInput.files[0] : null;

        if (!file) {
            renderImportError('Please choose a CSV file to import.');
            return;
        }

        const formData = new FormData(importForm);
        formData.set('type', selectedType);
        formData.set('mode', modeValue);

        setImportSubmitting(true);

        try {
            const response = await fetch(config.importUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': config.csrfToken,
                },
                body: formData,
            });

            const payload = await response.json();

            if (!response.ok || !payload?.success) {
                if (payload?.errors) {
                    const firstError = Object.values(payload.errors)[0];
                    const message = Array.isArray(firstError) ? firstError[0] : firstError;
                    throw new Error(message);
                }
                throw new Error(payload?.message ?? 'Import failed.');
            }

            hideImportModal();

            const data = payload.data ?? {};
            const importedType = data.type || selectedType;

            refreshTable(importedType, { resetPage: true });

            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'success',
                    title: 'Import Complete',
                    html: `
                        <div class="text-left text-sm">
                            <p class="font-semibold mb-2">Processed summary</p>
                            <ul class="space-y-1">
                                <li><strong>Processed:</strong> ${formatNumber(data.processed || 0)}</li>
                                <li><strong>Saved:</strong> ${formatNumber(data.affected || 0)}</li>
                                <li><strong>Skipped:</strong> ${formatNumber(data.skipped || 0)}</li>
                                ${data.deleted ? `<li><strong>Replaced:</strong> ${formatNumber(data.deleted)} previous records</li>` : ''}
                            </ul>
                        </div>
                    `,
                    timer: 3500,
                    showConfirmButton: false,
                });
            }
        } catch (error) {
            renderImportError(error.message ?? 'Import failed.');
        } finally {
            setImportSubmitting(false);
        }
    }

    async function handleDelete() {
        const id = deleteRecordId?.value;
        const recordType = deleteRecordType?.value || defaultType;

        if (!id) {
            return;
        }

        try {
            const url = config.deleteUrl.replace('__ID__', id);
            const response = await fetch(url, {
                method: 'DELETE',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': config.csrfToken,
                },
            });

            const payload = await response.json();

            if (!response.ok || !payload?.success) {
                throw new Error(payload?.message ?? 'Failed to delete record.');
            }

            hideDeleteModal();
            renderGlobalError('');

            refreshTable(recordType, { resetPage: false });

            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'success',
                    title: 'Record Deleted',
                    text: payload.message ?? 'Record deleted successfully.',
                    timer: 2000,
                    showConfirmButton: false,
                });
            }
        } catch (error) {
            hideDeleteModal();
            renderGlobalError(error.message ?? 'Failed to delete record.');
        }
    }

    function createTableManager(type) {
        const sectionId = `file-search-${type}`;
        const section = document.querySelector(`section[data-record-type="${type}"]`);
        const tableBody = document.getElementById(`${sectionId}-tbody`);
        const summaryText = document.getElementById(`${sectionId}-summary`);
        const prevButton = document.getElementById(`${sectionId}-prev`);
        const nextButton = document.getElementById(`${sectionId}-next`);
        const pageBadge = document.getElementById(`${sectionId}-page`);
        const statusText = document.getElementById(`${sectionId}-status`);
        const errorText = document.getElementById(`${sectionId}-error`);
        const exportButton = document.getElementById(`${sectionId}-export`);

        if (!tableBody) {
            return null;
        }

        const state = {
            page: 1,
            isLoading: false,
            lastSynced: null,
        };

        let hasLoaded = false;

        const setLoading = (loading) => {
            state.isLoading = loading;

            if (statusText) {
                if (loading) {
                    statusText.classList.remove('hidden');
                    statusText.textContent = 'Fetching…';
                } else if (state.lastSynced) {
                    statusText.classList.remove('hidden');
                    statusText.textContent = `Synced ${state.lastSynced}`;
                } else {
                    statusText.classList.add('hidden');
                    statusText.textContent = '';
                }
            }

            if (section) {
                section.classList.toggle('opacity-70', loading);
            }

            if (prevButton) {
                prevButton.disabled = loading || state.page <= 1;
            }

            if (nextButton) {
                nextButton.disabled = loading;
            }
        };

        const renderError = (message) => {
            if (!errorText) {
                return;
            }

            if (!message) {
                errorText.classList.add('hidden');
                errorText.textContent = '';
                return;
            }

            errorText.textContent = message;
            errorText.classList.remove('hidden');
        };

        const renderRows = (records) => {
            tableBody.innerHTML = '';

            if (!Array.isArray(records) || records.length === 0) {
                const row = document.createElement('tr');
                const cell = document.createElement('td');
                cell.colSpan = 9;
                cell.className = 'px-4 py-10 text-center text-slate-500';
                cell.textContent = `No ${typeLabels[type].toLowerCase()} found for the supplied filters.`;
                row.appendChild(cell);
                tableBody.appendChild(row);
                return;
            }

            records.forEach((record) => {
                const row = document.createElement('tr');
                row.className = 'hover:bg-slate-50';
                row.dataset.recordType = record.record_type || type;

                const cellValues = [
                    displayValue(record.serial),
                    displayValue(record.registry),
                    displayValue(record.file_number),
                    displayValue(record.file_title),
                    displayValue(record.plot_number),
                    displayValue(record.location),
                    displayValue(record.comment_display || record.comment_prefixed || record.comment),
                    displayValue(record.created_at_display || record.created_at),
                ];

                cellValues.forEach((value, index) => {
                    const cell = document.createElement('td');
                    cell.className = 'px-4 py-3 align-top text-sm text-slate-700';
                    if (index === 0) {
                        cell.classList.add('font-semibold', 'text-slate-900');
                    }
                    const isFileTitleCell = index === 3;

                    if (
                        isFileTitleCell
                        && typeof value === 'string'
                        && value.includes('\n')
                    ) {
                        const parts = value.split(/\r?\n/).map((part) => part.trim()).filter((part) => part !== '');

                        if (parts.length === 0) {
                            cell.textContent = '—';
                        } else {
                            parts.forEach((part, partIndex) => {
                                if (partIndex > 0) {
                                    cell.appendChild(document.createElement('br'));
                                }
                                cell.appendChild(document.createTextNode(part));
                            });
                        }
                    } else {
                        cell.textContent = value;
                    }
                    row.appendChild(cell);
                });

                const actionsCell = document.createElement('td');
                actionsCell.className = 'px-4 py-3 text-center whitespace-nowrap';
                actionsCell.innerHTML = `
                    <div class="inline-flex items-center gap-1">
                        <button type="button" class="callup-btn rounded-lg p-2 text-rose-600 hover:bg-rose-50" data-file-number="${record.file_number || ''}" data-id="${record.id}" title="Duplicate Call-up">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
                        </button>
                        <button type="button" class="edit-btn rounded-lg p-2 text-blue-600 hover:bg-blue-50" data-id="${record.id}" data-record-type="${record.record_type || type}" title="Edit">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                        </button>
                        <button type="button" class="delete-btn rounded-lg p-2 text-rose-600 hover:bg-rose-50" data-id="${record.id}" data-record-type="${record.record_type || type}" title="Delete">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                        </button>
                    </div>
                `;
                row.appendChild(actionsCell);

                tableBody.appendChild(row);
            });

            tableBody.querySelectorAll('.callup-btn').forEach((btn) => {
                btn.addEventListener('click', () => {
                    const fn = btn.getAttribute('data-file-number');
                    const id = btn.getAttribute('data-id');
                    if (fn) {
                        window.open(`/duplicate-callup?file_number=${encodeURIComponent(fn)}&duplicate_id=${encodeURIComponent(id || '')}`, '_blank');
                    }
                });
            });

            tableBody.querySelectorAll('.edit-btn').forEach((btn) => {
                btn.addEventListener('click', () => {
                    const id = btn.getAttribute('data-id');
                    const recordType = btn.getAttribute('data-record-type') || type;
                    if (id) {
                        openEditModal(id, recordType);
                    }
                });
            });

            tableBody.querySelectorAll('.delete-btn').forEach((btn) => {
                btn.addEventListener('click', () => {
                    const id = btn.getAttribute('data-id');
                    const recordType = btn.getAttribute('data-record-type') || type;
                    if (id) {
                        showDeleteModal(id, recordType);
                    }
                });
            });
        };

        const updatePagination = (meta) => {
            const current = Number(meta?.current_page) || state.page;
            const perPage = Number(meta?.per_page) || sharedState.perPage;
            const total = Number(meta?.total) || 0;
            const lastPage = Number(meta?.last_page) || 1;

            state.page = current;

            const start = total === 0 ? 0 : (current - 1) * perPage + 1;
            const end = total === 0 ? 0 : Math.min(current * perPage, total);

            if (summaryText) {
                summaryText.textContent = total === 0
                    ? 'No records found'
                    : `Showing ${formatNumber(start)} – ${formatNumber(end)} of ${formatNumber(total)} records`;
            }

            if (pageBadge) {
                pageBadge.textContent = `Page ${current} / ${Math.max(lastPage, 1)}`;
            }

            if (prevButton) {
                prevButton.disabled = state.isLoading || current <= 1;
            }

            if (nextButton) {
                nextButton.disabled = state.isLoading || current >= lastPage;
            }

            // Update tab count badge
            updateTabCount(type, total);
        };

        const fetchRecords = ({ resetPage = false } = {}) => {
            if (state.isLoading) {
                return;
            }

            if (resetPage) {
                state.page = 1;
            }

            setLoading(true);
            renderError('');

            const params = new URLSearchParams({
                page: Math.max(1, state.page).toString(),
                per_page: clampPerPage(sharedState.perPage).toString(),
                type,
            });

            if (sharedState.search) {
                params.append('search', sharedState.search);
            }

            if (sharedState.registry) {
                params.append('registry', sharedState.registry);
            }

            if (sharedState.startDate) {
                params.append('start_date', sharedState.startDate);
            }

            if (sharedState.endDate) {
                params.append('end_date', sharedState.endDate);
            }

            fetch(`${config.listUrl}?${params.toString()}`, {
                headers: {
                    Accept: 'application/json',
                },
            })
                .then(async (response) => {
                    if (!response.ok) {
                        throw new Error('Unable to load file search data.');
                    }

                    return response.json();
                })
                .then((payload) => {
                    if (!payload?.success) {
                        throw new Error(payload?.message ?? 'Search failed.');
                    }

                    const items = payload?.data?.items ?? [];
                    renderRows(items);
                    updatePagination(payload?.data?.pagination ?? {});

                    state.lastSynced = new Date().toLocaleTimeString();
                    hasLoaded = true;

                    if (statusText && !state.isLoading) {
                        statusText.classList.remove('hidden');
                        statusText.textContent = `Synced ${state.lastSynced}`;
                    }
                })
                .catch((error) => {
                    renderRows([]);
                    updatePagination({ current_page: 1, per_page: sharedState.perPage, total: 0, last_page: 1 });
                    renderError(error.message ?? 'Unexpected error.');
                })
                .finally(() => {
                    setLoading(false);
                });
        };

        addEvent(prevButton, 'click', () => {
            if (state.page > 1) {
                state.page -= 1;
                fetchRecords();
            }
        });

        addEvent(nextButton, 'click', () => {
            state.page += 1;
            fetchRecords();
        });

        if (exportButton) {
            if (!config.exportUrl) {
                exportButton.disabled = true;
                exportButton.classList.add('hidden');
            } else {
                addEvent(exportButton, 'click', () => handleExport(type));
            }
        }

        return {
            type,
            label: typeLabels[type] || type,
            fetch: fetchRecords,
            state,
            hasLoaded: () => hasLoaded,
            get hasLoaded() { return hasLoaded; },
        };
    }

    typeOrder.forEach((type) => {
        const manager = createTableManager(type);
        if (manager) {
            tableManagers[type] = manager;
        }
    });

    // Add tab click event listeners
    document.querySelectorAll('.tab-button').forEach(button => {
        addEvent(button, 'click', () => {
            const tabKey = button.dataset.tab;
            if (tabKey) {
                switchTab(tabKey);
            }
        });
    });

    addEvent(runButton, 'click', () => {
        sharedState.search = keywordInput?.value?.trim() ?? '';
        sharedState.registry = registrySelect?.value?.trim() ?? '';
        syncDateRangeState();
        refreshAllTables({ resetPage: true });
    });

    addEvent(resetButton, 'click', () => {
        if (keywordInput) {
            keywordInput.value = '';
        }
        if (registrySelect) {
            registrySelect.value = '';
        }
        if (perPageSelect) {
            perPageSelect.value = String(config.defaultPerPage ?? 25);
        }
        if (startDateInput) {
            startDateInput.value = '';
        }
        if (endDateInput) {
            endDateInput.value = '';
        }

        sharedState.search = '';
        sharedState.registry = '';
        sharedState.perPage = clampPerPage(config.defaultPerPage);
        sharedState.startDate = '';
        sharedState.endDate = '';

        refreshAllTables({ resetPage: true });
    });

    addEvent(perPageSelect, 'change', () => {
        sharedState.perPage = clampPerPage(perPageSelect.value);
        refreshAllTables({ resetPage: true });
    });

    addEvent(keywordInput, 'keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            sharedState.search = keywordInput?.value?.trim() ?? '';
            syncDateRangeState();
            refreshAllTables({ resetPage: true });
        }
    });

    addEvent(startDateInput, 'change', () => {
        syncDateRangeState();
    });

    addEvent(endDateInput, 'change', () => {
        syncDateRangeState();
    });

    if (recordCommentSelect && recordTypeSelect) {
        addEvent(recordCommentSelect, 'change', () => {
            const value = recordCommentSelect.value || defaultType;
            if (recordTypeSelect.value !== value) {
                recordTypeSelect.value = value;
            }
            if (!recordId?.value) {
                const rows = Array.from(recordFileList?.querySelectorAll('.record-file-row') ?? []).map((row) => ({
                    file_number: row.querySelector('.record-file-number-input')?.value.trim() ?? '',
                    file_title: row.querySelector('.record-file-title-input')?.value.trim() ?? '',
                    plot_number: row.querySelector('.record-file-plot-input')?.value.trim() ?? '',
                    location: row.querySelector('.record-file-location-input')?.value.trim() ?? '',
                }));
                setFileRows(rows);
                setFormMode(false);
            }
        });
    }

    addEvent(addButton, 'click', openAddModal);
    addEvent(modalClose, 'click', hideModal);
    addEvent(modalCancel, 'click', hideModal);
    addEvent(modalBackdrop, 'click', hideModal);
    addEvent(recordForm, 'submit', handleFormSubmit);

    addEvent(deleteCancel, 'click', hideDeleteModal);
    addEvent(deleteBackdrop, 'click', hideDeleteModal);
    addEvent(deleteConfirm, 'click', handleDelete);

    addEvent(importButton, 'click', showImportModal);
    addEvent(importClose, 'click', hideImportModal);
    addEvent(importCancel, 'click', hideImportModal);
    addEvent(importBackdrop, 'click', hideImportModal);
    addEvent(importForm, 'submit', handleImportSubmit);
    addEvent(globalExportButton, 'click', () => handleExport());

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            if (recordModal && !recordModal.classList.contains('hidden')) {
                hideModal();
            }
            if (deleteModal && !deleteModal.classList.contains('hidden')) {
                hideDeleteModal();
            }
            if (importModal && !importModal.classList.contains('hidden')) {
                hideImportModal();
            }
        }

        if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
            event.preventDefault();
            keywordInput?.focus();
        }
    });

    // The edit card's single field, now going through the same helper as the rows.
    addEvent(filenoSelectorBtn, 'click', () => openFileNoLookup(recordFileNumber));
    addEvent(recordFileNumber, 'click', () => openFileNoLookup(recordFileNumber));

    if (typeof GlobalFileNoModal !== 'undefined' && typeof GlobalFileNoModal.init === 'function') {
        GlobalFileNoModal.init();
    }

    if (Object.keys(tableManagers).length > 0) {
        // Only load the active tab initially
        const activeManager = tableManagers[activeTab];
        if (activeManager) {
            activeManager.fetch({ resetPage: true });
        }
    }
});
