<div id="cofoDetailsModal"
    class="fixed inset-0 z-[1000] hidden bg-black bg-opacity-50 flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-7xl max-h-[92vh] overflow-hidden flex flex-col">
        <div class="flex items-center justify-between px-6 py-4 border-b">
            <div>
                <h5 class="text-xl font-semibold">CoFO Details</h5>
                <p id="cofoModalApplicantName" class="text-sm font-medium text-gray-600"></p>
                <p id="cofoModalSubtitle" class="text-sm text-gray-500"></p>
            </div>
            <button type="button" class="p-2 rounded-full text-gray-400 hover:text-gray-600 hover:bg-gray-100"
                onclick="closeCofoDetailsModal()" aria-label="Close CofO modal">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <div id="cofoModalAlert" class="hidden mb-4 px-4 py-3 rounded-md text-sm font-medium"></div>

        <div class="flex flex-col md:flex-row flex-1 overflow-hidden">
            <div class="md:w-72 lg:w-80 xl:w-96 border-b md:border-b-0 md:border-r border-gray-200 p-6 overflow-y-auto">
                <div class="flex items-center justify-between mb-4">
                    <h6 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Unit Applications</h6>
                    <button type="button" class="p-2 rounded-full text-gray-500 hover:text-gray-700 hover:bg-gray-100"
                        onclick="refreshCofoUnits()" title="Refresh unit list">
                        <i data-lucide="refresh-cw" class="w-4 h-4"></i>
                    </button>
                </div>

                <div id="cofoUnitsList" class="space-y-2">
                    <p class="text-sm text-gray-500">Select a unit to load the CofO card.</p>
                </div>
            </div>

            <div id="cofoFormContainer" class="flex-1 bg-gray-50 overflow-y-auto p-6">
                <div class="text-sm text-gray-500">Select a unit to load the CofO card.</div>
            </div>
        </div>
    </div>
</div>

<script>
    (function () {
        const state = {
            primaryId: null,
            fileNumber: '',
            primaryName: '',
            units: [],
            selectedUnitId: null,
        };

        function updateHeader() {
            const subtitle = document.getElementById('cofoModalSubtitle');
            const applicantNameLabel = document.getElementById('cofoModalApplicantName');

            if (subtitle) {
                subtitle.textContent = state.fileNumber ? `Primary File No: ${state.fileNumber}` : '';
            }

            if (applicantNameLabel) {
                const displayName = state.primaryName ? state.primaryName.toUpperCase() : '';
                applicantNameLabel.textContent = displayName ? `Primary Applicant: ${displayName}` : '';
            }
        }

        window.openCofoDetailsModal = function (primaryId, fileNumber, primaryApplicantName = '') {
            const unitsList = document.getElementById('cofoUnitsList');
            const formContainer = document.getElementById('cofoFormContainer');

            if (!unitsList || !formContainer) {
                console.error('CoFO modal elements missing');
                return;
            }

            state.primaryId = primaryId;
            state.fileNumber = typeof fileNumber === 'string' ? fileNumber : (fileNumber ?? '');
            state.primaryName = typeof primaryApplicantName === 'string' ? primaryApplicantName.trim() : '';
            state.units = [];
            state.selectedUnitId = null;

            updateHeader();

            unitsList.innerHTML = '<p class="text-sm text-gray-500">Loading units...</p>';
            formContainer.innerHTML = '<div class="text-sm text-gray-500">Select a unit to load the CofO card.</div>';

            showModal();
            setAlert('info', 'Fetching unit applications...');
            fetchUnits(primaryId);
        };

        function showModal() {
            const modal = document.getElementById('cofoDetailsModal');
            if (!modal) {
                return;
            }

            modal.classList.remove('hidden');
            modal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('overflow-hidden');

            if (window.lucide?.createIcons) {
                window.lucide.createIcons();
            }
        }

        window.closeCofoDetailsModal = function () {
            const modal = document.getElementById('cofoDetailsModal');
            if (!modal) {
                return;
            }

            modal.classList.add('hidden');
            modal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('overflow-hidden');

            const unitsList = document.getElementById('cofoUnitsList');
            const formContainer = document.getElementById('cofoFormContainer');

            state.primaryId = null;
            state.fileNumber = '';
            state.primaryName = '';
            state.units = [];
            state.selectedUnitId = null;

            updateHeader();

            if (unitsList) {
                unitsList.innerHTML = '<p class="text-sm text-gray-500">Select a unit to load the CofO card.</p>';
            }

            if (formContainer) {
                formContainer.innerHTML = '<div class="text-sm text-gray-500">Select a unit to load the CofO card.</div>';
            }

            clearAlert();
        };

        window.refreshCofoUnits = function () {
            if (!state.primaryId) {
                return;
            }

            fetchUnits(state.primaryId, { preserveSelection: true });
        };

        async function fetchUnits(primaryId, options = {}) {
            if (!primaryId) {
                setAlert('error', 'Unable to determine the primary application.');
                return;
            }

            const { preserveSelection = false } = options;
            const previousSelection = preserveSelection ? state.selectedUnitId : null;

            try {
                const response = await fetch(`/sectionaltitling/primary/${primaryId}/cofo-units`, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                });

                if (!response.ok) {
                    const errorPayload = await response.json().catch(() => null);
                    throw new Error(errorPayload?.message || 'Unable to load unit applications.');
                }

                const payload = await response.json();
                if (!payload.success) {
                    throw new Error(payload.message || 'Unable to load unit applications.');
                }

                state.units = Array.isArray(payload.data) ? payload.data : [];

                if (typeof payload.primary_owner_name === 'string') {
                    const incomingName = payload.primary_owner_name.trim();
                    if (incomingName !== '') {
                        state.primaryName = incomingName;
                    }
                }

                updateHeader();

                if (!state.units.length) {
                    renderUnitList(null);
                    setAlert('warning', 'No unit applications found for this primary record.');
                    return;
                }

                clearAlert();
                const selectedByServer = payload.selected_unit_id ? Number(payload.selected_unit_id) : null;
                const restoredSelection = selectedByServer ?? previousSelection;
                renderUnitList(restoredSelection);
            } catch (error) {
                console.error('Failed to fetch COFO units', error);
                setAlert('error', error.message || 'Unable to load unit applications.');
                const unitsList = document.getElementById('cofoUnitsList');
                if (unitsList) {
                    unitsList.innerHTML = '<p class="text-sm text-red-600">Failed to load units.</p>';
                }
            }
        }

        function renderUnitList(previousSelection = null) {
            const unitsList = document.getElementById('cofoUnitsList');
            const formContainer = document.getElementById('cofoFormContainer');

            if (!unitsList) {
                return;
            }

            if (!state.units.length) {
                unitsList.innerHTML = '<p class="text-sm text-gray-500">No unit applications found.</p>';
                if (formContainer) {
                    formContainer.innerHTML = '<div class="text-sm text-gray-500">No units are available for this primary application.</div>';
                }
                state.selectedUnitId = null;
                return;
            }

            const fragment = document.createDocumentFragment();
            const escapeHtml = (value) => {
                if (typeof value !== 'string') {
                    return value ?? '';
                }

                return value
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            };

            state.units.forEach((unit) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'w-full text-left px-3 py-2 border border-gray-200 rounded-md hover:border-green-400 hover:bg-green-50 transition focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-1';
                button.dataset.unitId = unit.id;

                const certificateBadge = unit.cofo_exists
                    ? `<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-green-100 text-green-700">COFO: ${escapeHtml(unit.cofo_certificate_number || 'Generated')}</span>`
                    : '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-yellow-100 text-yellow-700">Pending</span>';

                const memoBadge = unit.has_st_memo
                    ? '<span class="text-xs text-green-600">PA Memo ✓</span>'
                    : '<span class="text-xs text-amber-500">PA Memo pending</span>';

                const unitParts = [unit.unit_number, unit.block_number, unit.floor_number];
                const unitLabel = unitParts.filter(Boolean).join(' · ');
                const unitLabels = ['Unit', 'Block', 'Floor']
                    .filter((_, index) => Boolean(unitParts[index]))
                    .join(' · ');

                const termDisplay = unit.cofo_start_date && unit.cofo_total_term
                    ? `<p class="text-[11px] text-gray-400 mt-0.5">${escapeHtml(unit.cofo_start_date)} • ${escapeHtml(String(unit.cofo_total_term))} yrs</p>`
                    : '';

                button.innerHTML = `
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-[11px] uppercase tracking-wide text-gray-400">${escapeHtml(unitLabels || 'Unit Reference')}</p>
                        <p class="text-sm font-semibold text-gray-800 mt-0.5">${escapeHtml(unitLabel || `Unit #${unit.id}`)}</p>
                        <p class="text-xs text-gray-500 mt-0.5">${escapeHtml(unit.owner_name || 'Unknown owner')}</p>
                        <p class="text-[11px] text-gray-400 mt-0.5">File No: ${escapeHtml(unit.fileno || 'N/A')}</p>
                        ${termDisplay}
                    </div>
                    <div class="flex flex-col items-end gap-1">
                        ${certificateBadge}
                        ${memoBadge}
                    </div>
                </div>
            `;

                button.addEventListener('click', () => {
                    selectUnit(Number(unit.id));
                });

                fragment.appendChild(button);
            });

            unitsList.innerHTML = '';
            unitsList.appendChild(fragment);

            const availableIds = state.units.map((unit) => Number(unit.id));
            const resolvedSelection = previousSelection && availableIds.includes(Number(previousSelection))
                ? Number(previousSelection)
                : availableIds[0] ?? null;

            if (resolvedSelection) {
                selectUnit(resolvedSelection);
            } else {
                state.selectedUnitId = null;
                if (formContainer) {
                    formContainer.innerHTML = '<div class="text-sm text-gray-500">No unit selected.</div>';
                }
            }
        }

        function selectUnit(unitId) {
            if (!unitId) {
                return;
            }

            state.selectedUnitId = Number(unitId);

            const buttons = document.querySelectorAll('#cofoUnitsList button[data-unit-id]');
            buttons.forEach((button) => {
                const currentId = Number(button.dataset.unitId);
                if (currentId === state.selectedUnitId) {
                    button.classList.add('border-green-500', 'bg-green-50');
                } else {
                    button.classList.remove('border-green-500', 'bg-green-50');
                }
            });

            loadForm(state.selectedUnitId);
        }

        async function loadForm(unitId) {
            const formContainer = document.getElementById('cofoFormContainer');
            if (!formContainer || !unitId) {
                return;
            }

            formContainer.innerHTML = '<div class="flex items-center justify-center h-full text-sm text-gray-500">Loading CofO card...</div>';

            try {
                const response = await fetch(`/programmes/generate_cofo/${unitId}?modal=1`, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                });

                if (!response.ok) {
                    const errorPayload = await response.json().catch(() => null);
                    throw new Error(errorPayload?.message || 'Unable to load the CofO card.');
                }

                const payload = await response.json();
                if (!payload.success) {
                    throw new Error(payload.message || 'Unable to load the CofO card.');
                }

                insertFormHtml(formContainer, payload.html || '', payload.meta || {});
                clearAlert();

                if (window.lucide?.createIcons) {
                    window.lucide.createIcons();
                }
            } catch (error) {
                console.error('Failed to load COFO card', error);
                setAlert('error', error.message || 'Unable to load the CofO card.');
                formContainer.innerHTML = `<div class="text-sm text-red-600">${error.message || 'Unable to load the CofO card.'}</div>`;
            }
        }

        function insertFormHtml(container, html, meta = {}) {
            container.innerHTML = html || '<div class="text-sm text-gray-500">Card unavailable.</div>';

            const scriptNodes = Array.from(container.querySelectorAll('script'));
            scriptNodes.forEach((originalScript) => {
                const script = document.createElement('script');
                if (originalScript.src) {
                    script.src = originalScript.src;
                } else {
                    script.textContent = originalScript.textContent;
                }

                document.head.appendChild(script);
                document.head.removeChild(script);
                originalScript.remove();
            });

            if (typeof window.initializeCofoForm === 'function') {
                window.initializeCofoForm(container);
            }

            const dismissButton = container.querySelector('[data-cofo-dismiss]');
            if (dismissButton) {
                dismissButton.addEventListener('click', window.closeCofoDetailsModal);
            }

            if (state.selectedUnitId && state.units.length) {
                const foundUnit = state.units.find((unit) => Number(unit.id) === Number(state.selectedUnitId));
                if (foundUnit && meta.certificate_number) {
                    foundUnit.cofo_exists = true;
                    foundUnit.cofo_certificate_number = meta.certificate_number;
                    foundUnit.cofo_start_date = meta.start_date ?? foundUnit.cofo_start_date;
                    foundUnit.cofo_total_term = meta.total_term ?? foundUnit.cofo_total_term;
                }
            }

            attachFormHandlers(container, state.selectedUnitId);
        }

        function attachFormHandlers(container, unitId) {
            const form = container.querySelector('[data-cofo-form]');
            if (!form) {
                return;
            }

            if (form.dataset.cofoAjaxBound === '1') {
                return;
            }

            form.dataset.cofoAjaxBound = '1';

            const submitButton = form.querySelector('[data-cofo-submit]');
            if (submitButton && !submitButton.dataset.originalLabel) {
                submitButton.dataset.originalLabel = submitButton.textContent || 'Submit';
            }

            form.addEventListener('submit', (event) => {
                event.preventDefault();

                if (typeof window.initializeCofoForm === 'function') {
                    window.initializeCofoForm(container);
                }

                const formData = new FormData(form);
                if (!formData.has('modal')) {
                    formData.append('modal', '1');
                }

                if (submitButton) {
                    submitButton.disabled = true;
                    submitButton.textContent = 'Saving...';
                }

                fetch(form.action, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                    body: formData,
                })
                    .then(async (response) => {
                        if (response.status === 422) {
                            const validationPayload = await response.json().catch(() => null);
                            const errors = validationPayload?.errors || {};
                            const firstError = Object.values(errors)[0]?.[0] ?? validationPayload?.message ?? 'Validation failed.';
                            throw new Error(firstError);
                        }

                        if (!response.ok) {
                            const errorPayload = await response.json().catch(() => null);
                            throw new Error(errorPayload?.message || 'Unable to save the CofO information.');
                        }

                        return response.json();
                    })
                    .then((payload) => {
                        if (!payload.success) {
                            throw new Error(payload.message || 'Unable to save the CofO information.');
                        }

                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                icon: 'success',
                                title: 'Success',
                                text: payload.message || 'CofO details saved successfully.',
                            });
                        }

                        window.refreshCofoUnits();
                        loadForm(unitId);
                    })
                    .catch((error) => {
                        console.error('Failed to save COFO data', error);
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: error.message || 'Unable to save the CofO information.',
                            });
                        } else {
                            setAlert('error', error.message || 'Unable to save the CofO information.');
                        }
                    })
                    .finally(() => {
                        if (submitButton) {
                            submitButton.disabled = false;
                            submitButton.textContent = submitButton.dataset.originalLabel || 'Submit';
                        }
                    });
            });
        }

        function setAlert(type, message) {
            const alertBox = document.getElementById('cofoModalAlert');
            if (!alertBox) {
                return;
            }

            const baseClasses = ['mb-4', 'px-4', 'py-3', 'rounded-md', 'text-sm', 'font-medium'];
            const variants = {
                info: ['bg-blue-50', 'text-blue-700', 'border', 'border-blue-100'],
                success: ['bg-green-50', 'text-green-700', 'border', 'border-green-100'],
                warning: ['bg-yellow-50', 'text-yellow-700', 'border', 'border-yellow-100'],
                error: ['bg-red-50', 'text-red-700', 'border', 'border-red-100'],
            };

            alertBox.className = baseClasses.join(' ');
            (variants[type] || variants.info).forEach((cls) => alertBox.classList.add(cls));
            alertBox.textContent = message || '';
            alertBox.classList.remove('hidden');
        }

        function clearAlert() {
            const alertBox = document.getElementById('cofoModalAlert');
            if (!alertBox) {
                return;
            }

            alertBox.className = 'hidden mb-4 px-4 py-3 rounded-md text-sm font-medium';
            alertBox.textContent = '';
        }

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                const modal = document.getElementById('cofoDetailsModal');
                if (modal && !modal.classList.contains('hidden')) {
                    window.closeCofoDetailsModal();
                }
            }
        });

        document.addEventListener('click', (event) => {
            const modal = document.getElementById('cofoDetailsModal');
            if (!modal || modal.classList.contains('hidden')) {
                return;
            }

            if (event.target === modal) {
                window.closeCofoDetailsModal();
            }
        });
    })();
</script>