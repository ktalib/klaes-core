<div id="rofoDetailsModal"
    class="fixed inset-0 z-[1000] hidden bg-black bg-opacity-50 flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-7xl max-h-[92vh] overflow-hidden flex flex-col">
        <div class="flex items-center justify-between px-6 py-4 border-b">
            <div>
                <h5 class="text-xl font-semibold">RofO Details</h5>
                <p id="rofoModalApplicantName" class="text-sm font-medium text-gray-600"></p>
                <p id="rofoModalSubtitle" class="text-sm text-gray-500"></p>
            </div>
            <button type="button" class="p-2 rounded-full text-gray-400 hover:text-gray-600 hover:bg-gray-100"
                onclick="closeRofoDetailsModal()" aria-label="Close Rofo modal">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <div id="rofoModalAlert" class="hidden mb-4 px-4 py-3 rounded-md text-sm font-medium"></div>

        <div class="flex flex-col md:flex-row flex-1 overflow-hidden">
            <div class="md:w-72 lg:w-80 xl:w-96 border-b md:border-b-0 md:border-r border-gray-200 p-6 overflow-y-auto">
                <div class="flex items-center justify-between mb-4">
                    <h6 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Unit Applications</h6>
                    <button type="button" class="p-2 rounded-full text-gray-500 hover:text-gray-700 hover:bg-gray-100"
                        onclick="refreshRofoUnits()" title="Refresh unit list">
                        <i data-lucide="refresh-cw" class="w-4 h-4"></i>
                    </button>
                </div>

                <div id="rofoUnitsList" class="space-y-2">
                    <p class="text-sm text-gray-500">Select a unit to load the RofO form.</p>
                </div>
            </div>

            <div id="rofoFormContainer" class="flex-1 bg-gray-50 overflow-y-auto p-6">
                <div class="text-sm text-gray-500">Select a unit to load the RofO form.</div>
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
            primaryTermYears: null,
        };

        function normalizeTermYears(value) {
            if (value === null || value === undefined) {
                return null;
            }

            const parsed = parseInt(value, 10);
            if (Number.isNaN(parsed) || parsed <= 0) {
                return null;
            }

            return parsed;
        }

        function updateHeader() {
            const subtitle = document.getElementById('rofoModalSubtitle');
            const applicantNameLabel = document.getElementById('rofoModalApplicantName');

            if (subtitle) {
                subtitle.textContent = state.fileNumber ? `Primary File No: ${state.fileNumber}` : '';
            }

            if (applicantNameLabel) {
                const displayName = state.primaryName ? state.primaryName.toUpperCase() : '';
                applicantNameLabel.textContent = displayName ? `Primary Applicant: ${displayName}` : '';
            }
        }

        window.openRofoDetailsModal = function (primaryId, fileNumber, primaryApplicantName = '') {
            const unitsList = document.getElementById('rofoUnitsList');
            const formContainer = document.getElementById('rofoFormContainer');

            if (!unitsList || !formContainer) {
                console.error('ROFO modal elements missing');
                return;
            }

            state.primaryId = primaryId;
            state.fileNumber = typeof fileNumber === 'string' ? fileNumber : (fileNumber ?? '');
            state.primaryName = typeof primaryApplicantName === 'string' ? primaryApplicantName.trim() : '';
            state.units = [];
            state.selectedUnitId = null;
            state.primaryTermYears = null;

            updateHeader();

            unitsList.innerHTML = '<p class="text-sm text-gray-500">Loading units...</p>';
            formContainer.innerHTML = '<div class="text-sm text-gray-500">Select a unit to load the RofO form.</div>';

            showModal();
            setAlert('info', 'Fetching unit applications...');
            fetchUnits(primaryId);
        };

        function showModal() {
            const modal = document.getElementById('rofoDetailsModal');
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

        window.closeRofoDetailsModal = function () {
            const modal = document.getElementById('rofoDetailsModal');
            if (!modal) {
                return;
            }

            modal.classList.add('hidden');
            modal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('overflow-hidden');

            const unitsList = document.getElementById('rofoUnitsList');
            const formContainer = document.getElementById('rofoFormContainer');

            state.primaryId = null;
            state.fileNumber = '';
            state.primaryName = '';
            state.units = [];
            state.selectedUnitId = null;
            state.primaryTermYears = null;

            updateHeader();

            if (unitsList) {
                unitsList.innerHTML = '<p class="text-sm text-gray-500">Select a unit to load the RofO form.</p>';
            }

            if (formContainer) {
                formContainer.innerHTML = '<div class="text-sm text-gray-500">Select a unit to load the RofO form.</div>';
            }

            clearAlert();
        };

        window.refreshRofoUnits = function () {
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
                const response = await fetch(`/sectionaltitling/primary/${primaryId}/rofo-units`, {
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

                const primaryTermYears = normalizeTermYears(payload.primary_term_years ?? null);
                state.primaryTermYears = primaryTermYears;

                const unitsData = Array.isArray(payload.data) ? payload.data : [];
                state.units = unitsData.map((unit) => {
                    const unitTerm = normalizeTermYears(unit && Object.prototype.hasOwnProperty.call(unit, 'term_years') ? unit.term_years : null);

                    return Object.assign({}, unit, {
                        term_years: unitTerm !== null ? unitTerm : primaryTermYears,
                    });
                });

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
                renderUnitList(previousSelection);
            } catch (error) {
                console.error('Failed to fetch ROFO units', error);
                setAlert('error', error.message || 'Unable to load unit applications.');
                const unitsList = document.getElementById('rofoUnitsList');
                if (unitsList) {
                    unitsList.innerHTML = '<p class="text-sm text-red-600">Failed to load units.</p>';
                }
            }
        }

        function renderUnitList(previousSelection = null) {
            const unitsList = document.getElementById('rofoUnitsList');
            const formContainer = document.getElementById('rofoFormContainer');

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

                const rofoBadge = unit.rofo_exists
                    ? `<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-green-100 text-green-700">ROFO: ${escapeHtml(unit.rofo_no || 'Generated')}</span>`
                    : '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-yellow-100 text-yellow-700">Pending</span>';

                const memoBadge = unit.has_st_memo
                    ? '<span class="text-xs text-green-600">PA Memo ✓</span>'
                    : '<span class="text-xs text-amber-500">PA Memo pending</span>';

                const unitParts = [unit.unit_number, unit.block_number, unit.floor_number];
                const unitLabel = unitParts
                    .filter(Boolean)
                    .join(' · ');
                const unitLabels = ['Unit', 'Block', 'Floor']
                    .filter((_, index) => Boolean(unitParts[index]))
                    .join(' · ');

                button.innerHTML = `
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-[11px] uppercase tracking-wide text-gray-400">${escapeHtml(unitLabels || 'Unit Reference')}</p>
                        <p class="text-sm font-semibold text-gray-800 mt-0.5">${escapeHtml(unitLabel || `Unit #${unit.id}`)}</p>
                        <p class="text-xs text-gray-500 mt-0.5">${escapeHtml(unit.owner_name || 'Unknown owner')}</p>
                        <p class="text-[11px] text-gray-400 mt-0.5">File No: ${escapeHtml(unit.fileno || 'N/A')}</p>
                    </div>
                    <div class="flex flex-col items-end gap-1">
                        ${rofoBadge}
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

            const buttons = document.querySelectorAll('#rofoUnitsList button[data-unit-id]');
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
            const formContainer = document.getElementById('rofoFormContainer');
            if (!formContainer || !unitId) {
                return;
            }

            formContainer.innerHTML = '<div class="flex items-center justify-center h-full text-sm text-gray-500">Loading RofO form...</div>';

            try {
                const response = await fetch(`/programmes/generate_rofo/${unitId}?modal=1`, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                });

                if (!response.ok) {
                    const errorPayload = await response.json().catch(() => null);
                    throw new Error(errorPayload?.message || 'Unable to load the RofO form.');
                }

                const payload = await response.json();
                if (!payload.success) {
                    throw new Error(payload.message || 'Unable to load the RofO form.');
                }

                insertFormHtml(formContainer, payload.html || '');
                applyTermYearsDefault(formContainer, unitId);
                clearAlert();
                attachFormHandlers(formContainer, unitId);

                if (window.lucide?.createIcons) {
                    window.lucide.createIcons();
                }
            } catch (error) {
                console.error('Failed to load ROFO form', error);
                setAlert('error', error.message || 'Unable to load the RofO form.');
                formContainer.innerHTML = `<div class="text-sm text-red-600">${error.message || 'Unable to load the RofO form.'}</div>`;
            }
        }

        function insertFormHtml(container, html) {
            container.innerHTML = html || '<div class="text-sm text-gray-500">Form unavailable.</div>';

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

            if (typeof window.initializeRofoForm === 'function') {
                window.initializeRofoForm(container);
            }

            const dismissButton = container.querySelector('[data-rofo-dismiss]');
            if (dismissButton) {
                dismissButton.addEventListener('click', window.closeRofoDetailsModal);
            }
        }

        function applyTermYearsDefault(container, unitId) {
            if (!container || !unitId) {
                return;
            }

            const termInput = container.querySelector('input[name="term_years"]');
            if (!termInput) {
                return;
            }

            const existingValue = typeof termInput.value === 'string' ? termInput.value.trim() : '';
            if (existingValue !== '') {
                return;
            }

            const unit = state.units.find((item) => Number(item.id) === Number(unitId));
            const unitTerm = unit ? normalizeTermYears(unit.term_years) : null;
            const effectiveTerm = unitTerm !== null ? unitTerm : state.primaryTermYears;

            if (effectiveTerm === null) {
                return;
            }

            termInput.value = effectiveTerm;
            termInput.dispatchEvent(new Event('input', { bubbles: true }));
            termInput.dispatchEvent(new Event('change', { bubbles: true }));
        }

        function attachFormHandlers(container, unitId) {
            const form = container.querySelector('[data-rofo-form]');
            if (!form) {
                return;
            }

            if (form.dataset.rofoBound === '1') {
                return;
            }

            form.dataset.rofoBound = '1';

            const submitButton = form.querySelector('[data-rofo-submit]');
            if (submitButton && !submitButton.dataset.originalLabel) {
                submitButton.dataset.originalLabel = submitButton.textContent || 'Submit';
            }

            form.addEventListener('submit', (event) => {
                event.preventDefault();

                if (typeof window.initializeRofoForm === 'function') {
                    window.initializeRofoForm(container);
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
                        if (!response.ok) {
                            const errorPayload = await response.json().catch(() => null);
                            throw new Error(errorPayload?.message || 'Unable to save the RofO information.');
                        }

                        return response.json();
                    })
                    .then((payload) => {
                        if (!payload.success) {
                            throw new Error(payload.message || 'Unable to save the RofO information.');
                        }

                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                icon: 'success',
                                title: 'Success',
                                text: payload.message || 'RofO details saved successfully.',
                            });
                        }

                        window.refreshRofoUnits();
                        loadForm(unitId);
                    })
                    .catch((error) => {
                        console.error('Failed to save ROFO data', error);
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: error.message || 'Unable to save the RofO information.',
                            });
                        } else {
                            setAlert('error', error.message || 'Unable to save the RofO information.');
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
            const alertBox = document.getElementById('rofoModalAlert');
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
            const alertBox = document.getElementById('rofoModalAlert');
            if (!alertBox) {
                return;
            }

            alertBox.className = 'hidden mb-4 px-4 py-3 rounded-md text-sm font-medium';
            alertBox.textContent = '';
        }

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                const modal = document.getElementById('rofoDetailsModal');
                if (modal && !modal.classList.contains('hidden')) {
                    window.closeRofoDetailsModal();
                }
            }
        });

        document.addEventListener('click', (event) => {
            const modal = document.getElementById('rofoDetailsModal');
            if (!modal || modal.classList.contains('hidden')) {
                return;
            }

            if (event.target === modal) {
                window.closeRofoDetailsModal();
            }
        });
    })();
</script>