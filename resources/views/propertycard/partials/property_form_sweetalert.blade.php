<script>
// Escape HTML to safely render duplicate details
function _escHtml(v) {
    if (v === null || v === undefined || v === '') return '—';
    return String(v)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

function _fmtDate(v) {
    if (!v) return '—';
    try { return String(v).substring(0, 10); } catch (_) { return _escHtml(v); }
}

// Build a CofO-focused details table from the duplicate record returned by the API
function buildDuplicateDetailsHtml(dup, dupType) {
    const isCofO = dupType === 'cofo_staging';
    const rows = [];
    const add = (label, value) => rows.push(
        `<tr><th class="text-left pr-3 py-1 text-gray-600">${_escHtml(label)}</th><td class="py-1">${_escHtml(value)}</td></tr>`
    );

    if (isCofO) {
        add('File No.', dup.mlsFNo || dup.kangisFileNo || dup.NewKANGISFileno || dup.np_fileno || dup.temp_fileno || dup.fileno);
        add('Party 2', dup.Grantee || dup.Assignee || dup.party_2 || dup.party_1);
        add('Transaction Type', dup.transaction_type);
        add('Transaction Date', _fmtDate(dup.transaction_date));
        add('Reg No.', dup.regNo);
        add('Location', [dup.plot_no, dup.location || dup.lgsaOrCity].filter(Boolean).join(', '));
    } else {
        add('Transaction Type', dup.transaction_type);
        add('Prop ID', dup.prop_id);
        add('File No.', dup.mlsFNo || dup.kangisFileNo || dup.temp_fileno);
        add('Reg No.', dup.regNo);
        add('Vol / Page / Serial', [dup.volumeNo, dup.pageNo, dup.serialNo].filter(Boolean).join(' / '));
        add('Party 1', dup.party_1);
        add('Record ID', dup.id);
    }

    return `
        <div class="text-sm">
            <p class="mb-2 text-gray-700">An existing record matched your submission. Please review the details below.</p>
            <table class="w-full border-collapse"><tbody>${rows.join('')}</tbody></table>
        </div>
    `;
}

// Disable and grey out every interactive control inside the form, then show a banner
function lockPropertyRecordForm(form, message, recordId = null, sourceType = null) {
    if (!form || form.dataset.duplicateLocked === '1') return;
    form.dataset.duplicateLocked = '1';

    // Disable all inputs/selects/textareas/buttons (except those marked to remain interactive)
    form.querySelectorAll('input, select, textarea, button').forEach((el) => {
        if (el.hasAttribute('data-allow-when-locked')) return;
        try { el.disabled = true; } catch (_) {}
        el.setAttribute('aria-disabled', 'true');
    });

    // Grey out visually
    form.classList.add('opacity-60', 'pointer-events-none');
    form.style.filter = 'grayscale(0.4)';

    // Insert a dismissible banner at the top of the form
    if (!document.getElementById('duplicate-lock-banner')) {
        const banner = document.createElement('div');
        banner.id = 'duplicate-lock-banner';
        banner.className = 'mb-3 p-3 rounded border border-red-300 bg-red-50 text-red-800 text-sm flex items-start justify-between';
        
        let actionButtonsHtml = `
            <button type="button" id="duplicate-lock-reset"
                class="ml-3 px-3 py-1 rounded bg-red-600 text-white text-xs hover:bg-red-700 pointer-events-auto">
                Start Over
            </button>
        `;

        if (recordId) {
            actionButtonsHtml = `
                <div class="flex gap-2">
                    <button type="button" id="duplicate-lock-update"
                        class="px-3 py-1 rounded bg-blue-600 text-white text-xs hover:bg-blue-700 pointer-events-auto font-bold">
                        Update Existing Record
                    </button>
                    <button type="button" id="duplicate-lock-reset"
                        class="px-3 py-1 rounded bg-gray-600 text-white text-xs hover:bg-gray-700 pointer-events-auto">
                        Start Over
                    </button>
                </div>
            `;
        }

        banner.innerHTML = `
            <div class="pr-3">
                <strong class="block mb-1">Duplicate record detected</strong>
                <span>${_escHtml(message || 'This submission matches an existing record and has been blocked to prevent duplicates.')}</span>
            </div>
            ${actionButtonsHtml}
        `;
        // Re-enable pointer events on the button itself
        banner.style.pointerEvents = 'auto';
        form.prepend(banner);

        const resetBtn = banner.querySelector('#duplicate-lock-reset');
        if (resetBtn) {
            resetBtn.addEventListener('click', () => unlockPropertyRecordForm(form));
        }

        const updateBtn = banner.querySelector('#duplicate-lock-update');
        if (updateBtn && recordId) {
            updateBtn.addEventListener('click', () => prepareFormForUpdateFromDuplicate(form, recordId, sourceType));
        }
    }
}

function unlockPropertyRecordForm(form, opts = {}) {
    if (!form) return;
    const silent = !!opts.silent;
    delete form.dataset.duplicateLocked;
    form.querySelectorAll('input, select, textarea, button').forEach((el) => {
        try { el.disabled = false; } catch (_) {}
        el.removeAttribute('aria-disabled');
    });
    form.classList.remove('opacity-60', 'pointer-events-none');
    form.style.filter = '';
    if (!silent) {
        form.reset();
        const sourceInput = form.querySelector('input[name="update_source"]');
        if (sourceInput) sourceInput.value = '';
    }
    const banner = document.getElementById('duplicate-lock-banner');
    if (banner) banner.remove();
    if (!silent) {
        clearCofoDuplicateCard();
        clearPraDuplicateCard();
    }
}

function handleDuplicateDetected(errData, form) {
    const dup = errData.duplicate || {};
    const dupType = errData.duplicate_type || 'pra';
    const lock = errData.lock_form !== false; // default to locking

    // For inline pre-check duplicates, render the card + lock instead of a popup.
    if ((dupType === 'cofo_staging' || dupType === 'pra') && form) {
        if (dupType === 'cofo_staging') {
            clearPraDuplicateCard();
            renderCofoDuplicateCard(form, [dup], lock);
            if (lock) lockPropertyRecordForm(form, errData.message, dup.id, 'cofo_staging');
        } else {
            // PRA duplicate: show card then enter update mode directly — do NOT block the form.
            clearCofoDuplicateCard();
            renderPraDuplicateCard(form, [dup], false, dup.transaction_type || getActiveTransactionType(form));
            if (dup.id) {
                prepareFormForUpdateFromDuplicate(form, dup.id);
            }
        }
        return;
    }

    // Non-CofO duplicates (PRA, generic): SweetAlert popup with a "View File History" action
    const historyFileNumber = dup.mlsFNo || dup.kangisFileNo || dup.NewKANGISFileno
        || dup.np_fileno || dup.temp_fileno || dup.fileno || (form ? getActiveFileNumber(form) : '');

    const swalOptions = {
        icon: 'warning',
        title: 'Duplicate Property Record',
        html: buildDuplicateDetailsHtml(dup, dupType),
        showConfirmButton: true,
        confirmButtonText: 'OK',
        showDenyButton: !!historyFileNumber,
        denyButtonText: '<i class="mr-1"></i> View File History',
        denyButtonColor: '#2563eb',
        showCancelButton: !!dup.id,
        cancelButtonText: 'Update Existing Record',
        cancelButtonColor: '#059669',
        reverseButtons: true,
        width: 620,
        customClass: { htmlContainer: 'text-left' }
    };

    Swal.fire(swalOptions).then((result) => {
        if (result.isDenied && historyFileNumber) {
            // Open timeline, then re-open the Swal flow so the user can still lock/confirm
            openFileHistoryTimelineModal(historyFileNumber);
            // Re-trigger the duplicate flow after the user closes the history modal
            // (they can also manually dismiss via the Start Over banner)
            if (lock && form) lockPropertyRecordForm(form, errData.message, dup.id);
            return;
        }

        if (result.dismiss === Swal.DismissReason.cancel && dup.id && form) {
            prepareFormForUpdateFromDuplicate(form, dup.id);
            return;
        }

        if (lock && form) {
            lockPropertyRecordForm(form, errData.message, dup.id);
        }
    });
}

/**
 * Transitions the 'Add' form into 'Update' mode using the duplicate record's ID.
 */
function prepareFormForUpdateFromDuplicate(form, recordId, sourceType = null) {
    if (!form || !recordId) return;

    // 1. Unlock the form but do NOT reset it (keep user's data)
    unlockPropertyRecordForm(form, { silent: true });

    // 2. Set hidden fields to indicate update mode
    const propertyIdInput = form.querySelector('#property_id');
    const formActionInput = form.querySelector('#form_action');
    const updateBase = form.getAttribute('data-update-base');

    if (propertyIdInput) propertyIdInput.value = recordId;
    if (formActionInput) formActionInput.value = 'update';

    // 2b. CofO duplicates live in the CofO_staging table, not `pra`. Signal the
    // target table to the backend `update()` handler via a hidden field so the
    // edit writes back to the correct row instead of the pra table.
    let sourceInput = form.querySelector('input[name="update_source"]');
    if (sourceType === 'cofo_staging') {
        if (!sourceInput) {
            sourceInput = document.createElement('input');
            sourceInput.type = 'hidden';
            sourceInput.name = 'update_source';
            form.appendChild(sourceInput);
        }
        sourceInput.value = 'cofo_staging';
    } else if (sourceInput) {
        sourceInput.value = '';
    }

    // 3. Update form action URL for the specific record
    if (updateBase) {
        // Ensure trailing slash logic
        const baseUrl = updateBase.endsWith('/') ? updateBase : updateBase + '/';
        form.action = `${baseUrl}${recordId}`;
        
        // Ensure we add a _method=PUT for Laravel resource updates if it doesn't exist
        let methodInput = form.querySelector('input[name="_method"]');
        if (!methodInput) {
            methodInput = document.createElement('input');
            methodInput.type = 'hidden';
            methodInput.name = '_method';
            methodInput.value = 'PUT';
            form.appendChild(methodInput);
        } else {
            methodInput.value = 'PUT';
        }
    }

    // 4. Show visual update banner
    const banner = form.querySelector('[data-role="update-banner"]');
    const label = form.querySelector('[data-role="update-label"]');
    if (banner) {
        banner.classList.remove('hidden');
        if (label) {
            label.textContent = sourceType === 'cofo_staging'
                ? 'CofO #' + recordId
                : recordId;
        }
    }

    // 5. Enhance the submit button to reflect update mode
    const submitBtn = form.querySelector('#property-submit-btn');
    if (submitBtn) {
        submitBtn.innerHTML = '<i class="fas fa-save mr-2"></i>Update Existing Record';
        submitBtn.classList.remove('btn-primary');
        // Use a distinct color (amber/orange) for update mode
        submitBtn.style.backgroundColor = '#d97706'; 
        submitBtn.style.color = '#ffffff';
    }

    // 6. Scroll to top of form to see the banner
    form.scrollIntoView({ behavior: 'smooth', block: 'start' });

    Swal.fire({
        icon: 'success',
        title: 'Update Mode Enabled',
        text: 'The form is now set to update the existing record. You can make changes and submit.',
        timer: 2000,
        showConfirmButton: false
    });
}

// Direct form submission handler with SweetAlert
function submitPropertyForm() {
    const form = document.getElementById('property-record-form');
    const formData = new FormData(form);

    // Show loading
    Swal.fire({
        title: 'Submitting...',
        text: 'Please wait while we save your property record',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    // Submit form via fetch (robust handling with CSRF, timeout, redirects, and non-JSON)
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || form.querySelector('input[name="_token"]')?.value || '';
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 60000); // 60 second timeout
    fetch(form.action, {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': token
        },
        signal: controller.signal
    })
    .then(async (response) => {
        clearTimeout(timeoutId);
        if (response.redirected) {
            Swal.close();
            window.location.href = response.url;
            return null;
        }
        const contentType = response.headers.get('Content-Type') || '';
        if (!response.ok) {
            if (contentType.includes('application/json')) {
                const errData = await response.json().catch(() => ({}));

                // Duplicate detected (422 with duplicate payload) — show details and lock form
                if (response.status === 422 && errData && errData.duplicate) {
                    handleDuplicateDetected(errData, form);
                    return null;
                }

                const messages = errData.errors ? Object.values(errData.errors).flat() : [errData.message || 'Request failed'];
                throw new Error(messages.join('\n'));
            } else {
                const text = await response.text().catch(() => '');
                throw new Error(text || ('HTTP ' + response.status));
            }
        }
        if (contentType.includes('application/json')) {
            return response.json();
        } else {
            // Non-JSON success, reload to reflect changes
            Swal.close();
            window.location.reload();
            return null;
        }
    })
    .then((data) => {
        if (!data) return;
        if (data.status === 'success' || data.success === true) {
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: data.message || 'Property record created successfully',
                confirmButtonText: 'OK'
            }).then(() => {
                // Reset form and close dialog
                form.reset();
                const dialog = document.getElementById('property-form-dialog');
                if (dialog) {
                    dialog.classList.add('hidden');
                }
                // Reload page to show new record
                window.location.reload();
            });
        } else {
            // Handle validation errors
            let errorMessage = data.message || 'An error occurred';
            if (data.errors) {
                const errorList = Object.values(data.errors).flat();
                errorMessage = errorList.join('\n');
            }
            Swal.fire({
                icon: 'error',
                title: 'Validation Error',
                text: errorMessage,
                confirmButtonText: 'OK'
            });
        }
    })
    .catch((error) => {
        clearTimeout(timeoutId);
        console.error('Error:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: error.message || 'An unexpected error occurred. Please try again.',
            confirmButtonText: 'OK'
        });
    });
}

// CofO transaction types that trigger the CofO duplicate pre-check
const COFO_TRANSACTION_TYPES = [
    'Certificate of Occupancy',
    'ST Certificate of Occupancy',
    'SLTR Certificate of Occupancy',
];

const COFO_PRECHECK_URL = @json(route('property-records.check-cofo-duplicate'));

function _getFieldValue(form, selector) {
    if (!form) return '';
    const el = form.querySelector(selector);
    return el && el.value ? String(el.value).trim() : '';
}

function getActiveFileNumber(form) {
    if (!form) return '';
    const sources = ['mlsFNo', 'kangisFileNo', 'NewKANGISFileno', 'np_fileno', 'temp_fileno', 'fileno'];
    for (const name of sources) {
        const val = _getFieldValue(form, `[name="${name}"]`);
        if (val) return val;
    }
    return '';
}

function getActiveTransactionType(form) {
    return _getFieldValue(form, '[name="transactionType"]')
        || _getFieldValue(form, '#transactionType-record');
}

function collectCofoMatchFields(form) {
    return {
        file_number: getActiveFileNumber(form),
        transaction_type: getActiveTransactionType(form),
        party_2: _getFieldValue(form, '[name="party_2"]'),
        transaction_date: _getFieldValue(form, '[name="transactionDate"]')
            || _getFieldValue(form, '[name="transaction_date"]'),
        reg_no: _getFieldValue(form, '[name="regNo"]'),
        vol: _getFieldValue(form, '[name="volumeNo"]'),
        page: _getFieldValue(form, '[name="pageNo"]'),
        serial: _getFieldValue(form, '[name="serialNo"]'),
        lgsaOrCity: _getFieldValue(form, '[name="lgsaOrCity"]'),
        location: _getFieldValue(form, '[name="location"]')
            || _getFieldValue(form, '[name="property_description"]'),
    };
}

// Render a CofO duplicate card styled like "Existing Records for this File"
function renderCofoDuplicateCard(form, records, locked) {
    if (!form) return;
    const anchor = document.getElementById('matching-records-list')
        || form.querySelector('.form-section') || form.firstElementChild;
    if (!anchor) return;

    let card = document.getElementById('cofo-duplicate-card');
    if (!records || records.length === 0) {
        if (card) card.remove();
        return;
    }

    if (!card) {
        card = document.createElement('div');
        card.id = 'cofo-duplicate-card';
        card.className = 'mt-3';
        card.style.pointerEvents = 'auto';
        anchor.parentNode.insertBefore(card, anchor);
    }

    const rowsHtml = records.map((r, i) => {
        const party2 = r.party_2 || r.Grantee || r.Assignee || r.Lessee || r.Mortgagee || '—';
        const regParts = [r.volumeNo, r.pageNo, r.serialNo].filter(Boolean).join(' / ');
        const loc = [r.plot_no, r.location || r.lgsaOrCity].filter(Boolean).join(', ') || '—';
        return `
            <div class="p-3 bg-white border ${locked ? 'border-red-300' : 'border-amber-200'} rounded-lg shadow-sm">
                <div class="flex justify-between items-start mb-2">
                    <div class="flex-1 min-w-0 pr-2">
                        <div class="flex items-center">
                            <span class="mr-1.5 text-red-600 font-bold text-[10px]">${i + 1}.</span>
                            <div class="text-[11px] font-bold text-gray-800 truncate">${_escHtml(r.transaction_type || 'Certificate of Occupancy')}</div>
                            <span class="ml-1 px-1.5 py-0.5 text-[8px] font-bold bg-red-100 text-red-700 rounded border border-red-200 uppercase">CofO</span>
                        </div>
                        <div class="text-[10px] text-gray-500 font-medium">
                            <span class="font-bold text-gray-700">Reg No.:</span>
                            <span class="text-blue-900 font-bold">${_escHtml(r.regNo || regParts || '—')}</span>
                        </div>
                        <div class="text-[10px] text-blue-600 font-semibold mt-0.5">
                            <span class="font-bold text-gray-700">Transaction Date:</span> ${_escHtml(_fmtDate(r.transaction_date))}
                        </div>
                    </div>
                </div>
                <div class="text-[10px] text-gray-600 border-t border-gray-50 pt-1.5 mt-1.5 leading-tight">
                    <span class="font-semibold text-gray-700">File No:</span> ${_escHtml(r.mlsFNo || r.kangisFileNo || r.NewKANGISFileno || r.np_fileno || r.temp_fileno || r.fileno)}
                    <span class="text-gray-300 mx-1">|</span>
                    <span class="font-semibold text-gray-700">Party 2:</span> ${_escHtml(party2)}
                    <span class="text-gray-300 mx-1">|</span>
                    <span class="font-semibold text-gray-700">Location:</span> ${_escHtml(loc)}
                </div>
            </div>
        `;
    }).join('');

    const headerClass = locked
        ? 'bg-red-50 border border-red-300 text-red-800'
        : 'bg-amber-50 border border-amber-200 text-amber-800';

    card.innerHTML = `
        <div class="rounded-lg ${headerClass} p-3 mb-2">
            <div class="flex items-center justify-between mb-1">
                <div class="text-[10px] font-bold uppercase tracking-tight flex items-center">
                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 9v2m0 4h.01M4.93 19h14.14a2 2 0 001.73-3L13.73 4a2 2 0 00-3.46 0L3.2 16a2 2 0 001.73 3z"/>
                    </svg>
                    Existing CofO for this File (${records.length})
                </div>
                <button type="button" id="cofo-view-file-history"
                    data-allow-when-locked
                    class="inline-flex items-center gap-1 px-2 py-1 text-[10px] font-semibold rounded border border-blue-300 bg-white text-blue-700 hover:bg-blue-50 pointer-events-auto">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    View File History
                </button>
            </div>
            <div class="text-[11px]">
                ${_escHtml(locked
                    ? 'A Certificate of Occupancy with matching details already exists. The form has been locked.'
                    : 'A Certificate of Occupancy already exists for this file number. The form will be locked if the remaining details also match.')}
            </div>
        </div>
        <div class="space-y-2 max-h-64 overflow-y-auto pr-1">${rowsHtml}</div>
    `;

    // Hook up the "View File History" button to open the timeline modal for the active file number
    const historyBtn = card.querySelector('#cofo-view-file-history');
    if (historyBtn) {
        historyBtn.style.pointerEvents = 'auto';
        historyBtn.addEventListener('click', (e) => {
            e.preventDefault();
            const fileNumber = getActiveFileNumber(form);
            if (fileNumber) openFileHistoryTimelineModal(fileNumber);
        });
    }
}

function renderPraDuplicateCard(form, records, locked, transactionType) {
    if (!form) return;
    const anchor = document.getElementById('matching-records-list')
        || form.querySelector('.form-section') || form.firstElementChild;
    if (!anchor) return;

    let card = document.getElementById('pra-duplicate-card');
    if (!records || records.length === 0) {
        if (card) card.remove();
        return;
    }

    if (!card) {
        card = document.createElement('div');
        card.id = 'pra-duplicate-card';
        card.className = 'mt-3';
        card.style.pointerEvents = 'auto';
        anchor.parentNode.insertBefore(card, anchor);
    }

    const rowsHtml = records.map((r, i) => {
        const regParts = [r.volumeNo, r.pageNo, r.serialNo].filter(Boolean).join(' / ');
        const fileNo = r.mlsFNo || r.kangisFileNo || r.NewKANGISFileno || r.np_fileno || r.temp_fileno || r.fileno;
        const updateBtnHtml = r.id
            ? `<button type="button" class="pra-dup-update-btn ml-2 px-2 py-1 text-[9px] font-bold text-white bg-blue-600 rounded hover:bg-blue-700 pointer-events-auto" data-record-id="${_escHtml(r.id)}">Update</button>`
            : '';
        return `
            <div class="p-3 bg-white border border-amber-200 rounded-lg shadow-sm">
                <div class="flex justify-between items-start mb-2">
                    <div class="flex-1 min-w-0 pr-2">
                        <div class="flex items-center">
                            <span class="mr-1.5 text-amber-600 font-bold text-[10px]">${i + 1}.</span>
                            <div class="text-[11px] font-bold text-gray-800 truncate">${_escHtml(r.transaction_type || transactionType || 'Property Record')}</div>
                            <span class="ml-1 px-1.5 py-0.5 text-[8px] font-bold bg-amber-100 text-amber-700 rounded border border-amber-200 uppercase">Duplicate</span>
                        </div>
                        <div class="text-[10px] text-gray-500 font-medium">
                            <span class="font-bold text-gray-700">Reg No.:</span>
                            <span class="text-blue-900 font-bold">${_escHtml(r.regNo || regParts || '—')}</span>
                        </div>
                        <div class="text-[10px] text-blue-600 font-semibold mt-0.5">
                            <span class="font-bold text-gray-700">Transaction Date:</span> ${_escHtml(_fmtDate(r.transaction_date))}
                        </div>
                    </div>
                    <div class="shrink-0">${updateBtnHtml}</div>
                </div>
                <div class="text-[10px] text-gray-600 border-t border-gray-50 pt-1.5 mt-1.5 leading-tight">
                    <span class="font-semibold text-gray-700">File No:</span> ${_escHtml(fileNo)}
                    <span class="text-gray-300 mx-1">|</span>
                    <span class="font-semibold text-gray-700">Party 1:</span> ${_escHtml(r.party_1)}
                    <span class="text-gray-300 mx-1">|</span>
                    <span class="font-semibold text-gray-700">Record ID:</span> ${_escHtml(r.id)}
                </div>
            </div>
        `;
    }).join('');

    const headerClass = 'bg-amber-50 border border-amber-200 text-amber-800';

    card.innerHTML = `
        <div class="rounded-lg ${headerClass} p-3 mb-2">
            <div class="flex items-center justify-between mb-1">
                <div class="text-[10px] font-bold uppercase tracking-tight flex items-center">
                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 9v2m0 4h.01M4.93 19h14.14a2 2 0 001.73-3L13.73 4a2 2 0 00-3.46 0L3.2 16a2 2 0 001.73 3z"/>
                    </svg>
                    Existing ${_escHtml(transactionType || 'Instrument')} for this File (${records.length})
                </div>
                <button type="button" id="pra-view-file-history"
                    data-allow-when-locked
                    class="inline-flex items-center gap-1 px-2 py-1 text-[10px] font-semibold rounded border border-blue-300 bg-white text-blue-700 hover:bg-blue-50 pointer-events-auto">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    View File History
                </button>
            </div>
            <div class="text-[11px]">
                This file already holds a matching instrument. You can carry on and save this
                one as a new record, or click <strong>Update</strong> on a record below to edit
                that one instead.
            </div>
        </div>
        <div class="space-y-2 max-h-64 overflow-y-auto pr-1">${rowsHtml}</div>
    `;

    const historyBtn = card.querySelector('#pra-view-file-history');
    if (historyBtn) {
        historyBtn.style.pointerEvents = 'auto';
        historyBtn.addEventListener('click', (e) => {
            e.preventDefault();
            const fileNumber = getActiveFileNumber(form);
            if (fileNumber) openFileHistoryTimelineModal(fileNumber);
        });
    }

    // Wire up per-row Update buttons
    card.querySelectorAll('.pra-dup-update-btn').forEach((btn) => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const recordId = btn.dataset.recordId;
            if (recordId) prepareFormForUpdateFromDuplicate(form, recordId);
        });
    });
}

function clearCofoDuplicateCard() {
    const card = document.getElementById('cofo-duplicate-card');
    if (card) card.remove();
}

function clearPraDuplicateCard() {
    const card = document.getElementById('pra-duplicate-card');
    if (card) card.remove();
}

// ──────────────────────────────────────────────
//  File History Timeline modal (self-contained)
// ──────────────────────────────────────────────
function ensureFileHistoryModal() {
    let modal = document.getElementById('pra-cofo-file-history-modal');
    if (modal) return modal;

    modal = document.createElement('div');
    modal.id = 'pra-cofo-file-history-modal';
    modal.className = 'fixed inset-0 hidden items-center justify-center bg-black/60 backdrop-blur-sm';
    modal.style.zIndex = '10080';
    modal.innerHTML = `
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-3xl mx-4 max-h-[85vh] overflow-hidden flex flex-col">
            <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-5 py-3 flex items-center justify-between">
                <div class="text-white font-semibold text-base flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    File History Timeline
                    <span id="pra-cofo-file-history-fileno" class="ml-2 text-xs font-mono bg-white/20 px-2 py-0.5 rounded"></span>
                </div>
                <button type="button" id="pra-cofo-file-history-close"
                    class="text-white/80 hover:text-white" style="background:none;border:none;cursor:pointer;">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <div class="px-5 py-2 bg-blue-50 border-b border-blue-100 text-xs text-blue-700">
                Chronological view of all recorded transactions for this file number.
            </div>
            <div id="pra-cofo-file-history-body" class="p-5 overflow-y-auto flex-1">
                <div class="py-12 text-center text-sm text-gray-500">Loading…</div>
            </div>
            <div class="flex justify-end px-5 py-3 border-t border-gray-200 bg-gray-50">
                <button type="button" id="pra-cofo-file-history-dismiss"
                    class="px-4 py-2 rounded-md bg-blue-600 text-white text-sm font-semibold hover:bg-blue-700">
                    Close
                </button>
            </div>
        </div>
    `;
    document.body.appendChild(modal);

    const close = () => {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    };
    modal.addEventListener('click', (e) => { if (e.target === modal) close(); });
    modal.querySelector('#pra-cofo-file-history-close').addEventListener('click', close);
    modal.querySelector('#pra-cofo-file-history-dismiss').addEventListener('click', close);

    return modal;
}

function _renderFileHistoryTimeline(transactions) {
    if (!transactions || transactions.length === 0) {
        return `<div class="py-10 text-center text-sm text-gray-500">No history found for this file number.</div>`;
    }

    // Sort oldest first
    const sorted = [...transactions].sort((a, b) => {
        const da = new Date(a.cofo_date || a.display_date || a.transaction_date || 0).getTime() || 0;
        const db = new Date(b.cofo_date || b.display_date || b.transaction_date || 0).getTime() || 0;
        return da - db;
    });

    const rows = sorted.map((t, i) => {
        const date = t.cofo_date || t.display_date || t.transaction_date || null;
        const label = (t.transaction_type || 'Unknown');
        const isCofo = /cofo|certificate of occupancy|c\s*of\s*o/i.test(label);
        const reg = [t.volumeNo, t.pageNo, t.serialNo].filter(Boolean).join(' / ');
        const parties = [
            t.party_1 || t.Assignor || t.Mortgagor || t.Grantor || t.Lessor || t.Surrenderor,
            t.party_2 || t.Assignee || t.Mortgagee || t.Grantee || t.Lessee || t.Surrenderee,
        ].filter(Boolean).map(_escHtml).join(' &rarr; ');
        return `
            <li class="relative pl-10 pb-5">
                <span class="absolute left-3 top-1 w-3 h-3 rounded-full ${isCofo ? 'bg-red-500' : 'bg-blue-500'} ring-2 ring-white"></span>
                <div class="bg-white border border-gray-200 rounded-lg p-3">
                    <div class="flex items-start justify-between gap-2">
                        <div class="font-semibold text-sm text-gray-900">${i + 1}. ${_escHtml(label)}
                            ${isCofo ? '<span class="ml-1 px-1.5 py-0.5 text-[9px] font-bold bg-red-100 text-red-700 rounded border border-red-200 uppercase">CofO</span>' : ''}
                        </div>
                        <div class="text-xs font-medium text-blue-700 whitespace-nowrap">${_escHtml(_fmtDate(date))}</div>
                    </div>
                    ${reg ? `<div class="text-xs text-gray-600 mt-1"><span class="font-semibold">Reg:</span> ${_escHtml(reg)}</div>` : ''}
                    ${parties ? `<div class="text-xs text-gray-600 mt-0.5">${parties}</div>` : ''}
                    ${t.location ? `<div class="text-xs text-gray-500 mt-0.5"><span class="font-semibold">Location:</span> ${_escHtml(t.location)}</div>` : ''}
                </div>
            </li>
        `;
    }).join('');

    return `
        <ol class="relative border-l-2 border-blue-100 ml-2">
            ${rows}
        </ol>
    `;
}

function openFileHistoryTimelineModal(fileNumber, propId) {
    // Delegate to the shared cross-module Property Timeline modal
    // (loaded via public/js/property-timeline-modal.js on the property card page).
    if (typeof window.openPropertyTimeline === 'function') {
        window.openPropertyTimeline(propId || '', fileNumber || '');
        return;
    }
    // Fallback: open the timeline page in a new tab
    const params = new URLSearchParams();
    if (propId) params.set('prop_id', propId);
    if (fileNumber) params.set('file_number', fileNumber);
    window.open('/property-search/timeline?' + params.toString(), '_blank');
}

let _cofoPrecheckInFlight = false;
let _cofoPrecheckLastKey = '';

async function runCofoDuplicatePreCheck(form, { force = false } = {}) {
    if (!form) return;

    const transactionType = getActiveTransactionType(form);
    const isCofOType = COFO_TRANSACTION_TYPES.includes(transactionType);

    const params = collectCofoMatchFields(form);
    const fileNumber = params.file_number;
    console.debug('[PRA pre-check] trigger', { fileNumber, transactionType, isCofOType, force });
    if (!fileNumber || !transactionType) {
        console.debug('[PRA pre-check] skipped: missing fileNumber or transactionType');
        clearCofoDuplicateCard();
        clearPraDuplicateCard();
        if (form.dataset.duplicateLocked === '1') {
            unlockPropertyRecordForm(form, { silent: true });
        }
        return;
    }

    const key = JSON.stringify(params);
    if (!force && key === _cofoPrecheckLastKey) return;
    _cofoPrecheckLastKey = key;

    if (_cofoPrecheckInFlight) return;
    _cofoPrecheckInFlight = true;

    try {
        const url = new URL(COFO_PRECHECK_URL, window.location.origin);
        Object.entries(params).forEach(([k, v]) => {
            if (v !== '' && v !== null && v !== undefined) url.searchParams.set(k, v);
        });

        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const res = await fetch(url.toString(), {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': token,
            },
            credentials: 'same-origin',
        });

        if (!res.ok) {
            console.warn('[PRA pre-check] HTTP', res.status, res.statusText);
            return;
        }
        const data = await res.json().catch(() => null);
        if (!data) return;
        console.debug('[PRA pre-check] response', data);

        const matches = Array.isArray(data.matches) ? data.matches : [];
        const shouldLock = !!data.lock_form;

        if (data.duplicate_type === 'cofo_staging') {
            clearPraDuplicateCard();
            renderCofoDuplicateCard(form, matches, shouldLock);
            // CofO duplicates retain lock behaviour, but expose an "Update Existing
            // Record" action so the user can edit the matching CofO instead of only
            // being able to start over. The locking record's id comes back in
            // `data.duplicate`; fall back to the first match if absent.
            const cofoDupId = (data.duplicate && data.duplicate.id)
                || (matches.length > 0 ? matches[0].id : null);
            if (shouldLock && form.dataset.duplicateLocked !== '1') {
                lockPropertyRecordForm(form, data.message, cofoDupId, 'cofo_staging');
            } else if (!shouldLock && form.dataset.duplicateLocked === '1') {
                unlockPropertyRecordForm(form, { silent: true });
            }
        } else {
            // PRA duplicates are ADVISORY. The card lists what the file already holds
            // and offers an Update button per row; nothing else changes.
            //
            // It used to call prepareFormForUpdateFromDuplicate() here whenever the
            // server returned lock_form, so merely picking a file number silently
            // repointed the form at an existing record — action switched to
            // PUT /property-records/{id}, submit relabelled "Update Existing Record".
            // An officer capturing a SECOND instrument on the file would have
            // overwritten the first one without ever asking to. Entering update mode
            // is now the Update button's job alone, which is the only place the user
            // says which record they mean.
            clearCofoDuplicateCard();
            renderPraDuplicateCard(form, matches, false, transactionType);
            if (form.dataset.duplicateLocked === '1') {
                // Only ever a leftover from a CofO type selected earlier on this form.
                unlockPropertyRecordForm(form, { silent: true });
            }
        }
    } catch (err) {
        console.warn('CofO pre-check failed:', err);
    } finally {
        _cofoPrecheckInFlight = false;
    }
}

function bindCofoPreCheck(form) {
    if (!form || form.dataset.cofoPrecheckBound === '1') return;
    form.dataset.cofoPrecheckBound = '1';

    const watchedNames = [
        'mlsFNo', 'kangisFileNo', 'NewKANGISFileno', 'np_fileno', 'temp_fileno', 'fileno',
        'party_2',
        'transactionDate', 'transaction_date',
        'regNo', 'volumeNo', 'pageNo', 'serialNo',
        'lgsaOrCity',
        'location', 'property_description',
    ];
    watchedNames.forEach((name) => {
        const el = form.querySelector(`[name="${name}"]`);
        if (!el) return;
        el.addEventListener('change', () => runCofoDuplicatePreCheck(form));
        el.addEventListener('input', () => runCofoDuplicatePreCheck(form));
    });

    const txnSelect = form.querySelector('[name="transactionType"]') || form.querySelector('#transactionType-record');
    if (txnSelect) {
        txnSelect.addEventListener('change', () => runCofoDuplicatePreCheck(form, { force: true }));
    }

    // Watch hidden file-number inputs for programmatic value changes (smart picker / party hidden field)
    const observer = new MutationObserver(() => runCofoDuplicatePreCheck(form));
    ['mlsFNo', 'kangisFileNo', 'NewKANGISFileno', 'np_fileno', 'temp_fileno', 'fileno', 'party_2'].forEach((name) => {
        const el = form.querySelector(`[name="${name}"]`);
        if (el) observer.observe(el, { attributes: true, attributeFilter: ['value'] });
    });
}

// Override the Alpine.js form submission when the page loads
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('property-record-form');
    const submitBtn = document.getElementById('property-submit-btn');
    
    if (form && submitBtn) {
        // Change button type to prevent default form submission
        submitBtn.type = 'button';
        
        // Remove any existing event listeners and add our custom handler
        submitBtn.onclick = function(e) {
            e.preventDefault();
            e.stopPropagation();
            submitPropertyForm();
            return false;
        };
        
        // Also prevent form submission
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            e.stopPropagation();
            submitPropertyForm();
            return false;
        }, true);

        // Bind live CofO duplicate pre-check on file number / instrument type changes
        bindCofoPreCheck(form);

        // Wire the "View File History" button in the "Existing Records for this File" card
        const matchingHistoryBtn = form.querySelector('[data-role="matching-records-history-btn"]');
        if (matchingHistoryBtn) {
            matchingHistoryBtn.addEventListener('click', (e) => {
                e.preventDefault();
                const fileNumber = getActiveFileNumber(form);
                if (fileNumber) openFileHistoryTimelineModal(fileNumber);
            });
        }

        // If the form is opened already populated (e.g. from an AI suggestion), run once on open
        runCofoDuplicatePreCheck(form, { force: true });
    }
});
</script>