(function () {
    const root = document.getElementById('manual-attendance-app');
    if (!root) {
        return;
    }

    const embedMode = root.dataset.embedded === 'true';

    const endpoints = {
        employees: root.dataset.employeesUrl,
        datatable: root.dataset.datatableUrl,
        store: root.dataset.storeUrl,
    };

    const fallbackAvatarUrl = root.dataset.fallbackAvatar;

    const elements = {
        date: document.getElementById('attendance-date'),
        shift: document.getElementById('attendance-shift'),
        workStation: document.getElementById('attendance-work-station'),
        search: document.getElementById('attendance-search'),
        refresh: document.getElementById('manual-attendance-refresh'),
        count: document.getElementById('manual-attendance-count'),
        list: document.getElementById('manual-attendance-list'),
        grid: document.getElementById('manual-attendance-grid'),
        table: document.querySelector('.manual-attendance-table'),
        tableBody: document.getElementById('manual-attendance-table-body'),
        selectAll: document.getElementById('manual-attendance-select-all'),
        markSelected: document.getElementById('manual-attendance-mark-selected'),
        clearSelection: document.getElementById('manual-attendance-clear-selection'),
        selectedCount: document.getElementById('manual-attendance-selected-count'),
        viewButtons: Array.from(document.querySelectorAll('.manual-attendance-view-btn')),
    };

    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const state = {
        employees: [],
        gridEmployees: [],
        loading: false,
        gridLoading: false,
        saving: false,
        lastSearchTimer: null,
        selected: new Set(),
        currentView: embedMode ? 'grid' : 'list',
        embedMode,
        dataTable: null,
        totalCount: 0,
        filters: {
            search: '',
        },
    };

    state.filters.search = elements.search ? elements.search.value.trim() : '';

    function notify(message, type = 'success') {
        const banner = document.createElement('div');
        banner.className = `fixed top-4 right-4 z-50 rounded-md px-4 py-3 shadow-lg text-sm font-medium text-white manual-attendance-toast manual-attendance-toast-${type}`;
        banner.textContent = message;
        document.body.appendChild(banner);
        setTimeout(() => {
            banner.classList.add('manual-attendance-toast-hide');
            setTimeout(() => banner.remove(), 250);
        }, 2200);
    }

    function setGridLoading(isLoading) {
        state.gridLoading = isLoading;
        if (!elements.grid) {
            return;
        }
        if (isLoading) {
            elements.grid.innerHTML = '<div class="col-span-full text-center py-12 text-slate-500"><i class="fas fa-spinner fa-spin mr-2"></i>Loading staff...</div>';
        }
    }

    function setSaving(saving) {
        state.saving = saving;
        if (elements.refresh) {
            elements.refresh.disabled = saving;
            elements.refresh.classList.toggle('opacity-60', saving);
        }
        updateSelectionUi();
    }

    function updateSelectionUi() {
        if (state.embedMode) {
            return;
        }
        const selectedCount = state.selected.size;
        if (elements.selectedCount) {
            elements.selectedCount.textContent = `${selectedCount} selected`;
        }
        let selectableCount = 0;
        if (state.dataTable) {
            const rows = state.dataTable.rows({ page: 'current' }).data().toArray();
            selectableCount = rows.filter((employee) => !isEntryLocked(employee.entry || null)).length;
        } else if (Array.isArray(state.gridEmployees)) {
            selectableCount = state.gridEmployees.filter((employee) => !isEntryLocked(employee.entry || null)).length;
        }
        if (elements.selectAll) {
            const shouldDisable = selectableCount === 0 || state.saving;
            elements.selectAll.disabled = shouldDisable;
            elements.selectAll.classList.toggle('opacity-60', shouldDisable);
        }
        if (elements.markSelected) {
            elements.markSelected.disabled = selectedCount === 0 || state.saving;
        }
        if (elements.clearSelection) {
            elements.clearSelection.disabled = selectedCount === 0 || state.saving;
        }
    }

    function updateCounts(totalFromServer) {
        if (!elements.count) {
            return;
        }
        if (typeof totalFromServer === 'number' && !Number.isNaN(totalFromServer)) {
            state.totalCount = totalFromServer;
        } else if (state.dataTable) {
            const info = state.dataTable.page.info();
            if (info && Number.isFinite(info.recordsDisplay)) {
                state.totalCount = info.recordsDisplay;
            }
        } else if (Array.isArray(state.gridEmployees)) {
            state.totalCount = state.gridEmployees.length;
        } else {
            state.totalCount = 0;
        }

        elements.count.textContent = `${state.totalCount} staff`;
    }

    function normalizeEntry(entry) {
        if (!entry) {
            return null;
        }

        const hasCheckIn = Boolean(entry.check_in_time);
        const hasCheckOut = Boolean(entry.check_out_time);
        if (hasCheckIn || hasCheckOut) {
            return entry;
        }

        if (entry.is_present === true) {
            return entry;
        }

        if (entry.is_present === false && entry.status && entry.status !== 'draft') {
            return entry;
        }

        return null;
    }

    function formatDisplayTime(value, fallback = '—') {
        if (!value) {
            return fallback;
        }
        const date = new Date(value);
        if (Number.isNaN(date.getTime())) {
            return fallback;
        }
        return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }

    function formatHours(value) {
        if (typeof value !== 'number' || Number.isNaN(value) || value <= 0) {
            return '—';
        }
        return `${value.toFixed(2)} h`;
    }

    function formatDateTimeForInput(value) {
        if (!value) {
            return '';
        }
        const date = value instanceof Date ? value : new Date(value);
        if (Number.isNaN(date.getTime())) {
            return '';
        }
        const offset = date.getTimezoneOffset();
        const local = new Date(date.getTime() - offset * 60000);
        return local.toISOString().slice(0, 16);
    }

    function buildDefaultDateTime(attendanceDate) {
        const datePart = attendanceDate || (elements.date ? elements.date.value : '');
        const now = new Date();
        if (!datePart) {
            return formatDateTimeForInput(now);
        }
        const [year, month, day] = datePart.split('-').map((value) => Number(value));
        const local = new Date();
        if (Number.isFinite(year) && Number.isFinite(month) && Number.isFinite(day)) {
            local.setFullYear(year, month - 1, day);
        }
        return formatDateTimeForInput(local);
    }

    function getNotesValue(userId) {
        const notesElement = document.querySelector(`textarea[data-user-id="${userId}"]`);
        return notesElement ? notesElement.value : '';
    }

    function setActionLoading(userId, action, isLoading) {
        const buttons = document.querySelectorAll(`.attendance-action-btn[data-action="${action}"][data-user-id="${userId}"]`);
        if (!buttons.length) {
            return;
        }
        buttons.forEach((button) => {
            button.disabled = isLoading;
            button.classList.toggle('opacity-60', isLoading);
            button.classList.toggle('cursor-wait', isLoading);
        });
    }

    function handleCheckIn(userId) {
        const attendanceDate = elements.date ? elements.date.value : '';
        if (!attendanceDate) {
            notify('Please select an attendance date', 'error');
            return;
        }

        const input = document.querySelector(`.attendance-check-in-input[data-user-id="${userId}"]`);
        const timestamp = input && input.value ? input.value : buildDefaultDateTime(attendanceDate);
        if (!timestamp) {
            notify('Provide a valid check-in time before submitting.', 'error');
            return;
        }

        setActionLoading(userId, 'check-in', true);
        submitAttendance({
            action: 'check_in',
            user_id: userId,
            attendance_date: attendanceDate,
            timestamp,
            notes: getNotesValue(userId),
        }).finally(() => {
            setActionLoading(userId, 'check-in', false);
        });
    }

    function handleCheckOut(userId) {
        const attendanceDate = elements.date ? elements.date.value : '';
        if (!attendanceDate) {
            notify('Please select an attendance date', 'error');
            return;
        }

        const input = document.querySelector(`.attendance-check-out-input[data-user-id="${userId}"]`);
        const timestamp = input && input.value ? input.value : '';
        if (!timestamp) {
            notify('Enter the check-out time before submitting.', 'error');
            return;
        }

        setActionLoading(userId, 'check-out', true);
        submitAttendance({
            action: 'check_out',
            user_id: userId,
            attendance_date: attendanceDate,
            timestamp,
            notes: getNotesValue(userId),
        }).finally(() => {
            setActionLoading(userId, 'check-out', false);
        });
    }

    function handleUndo(userId) {
        const attendanceDate = elements.date ? elements.date.value : '';
        if (!attendanceDate) {
            notify('Please select an attendance date', 'error');
            return;
        }

        const notes = getNotesValue(userId) || '';

        setActionLoading(userId, 'undo', true);
        submitAttendance({
            action: 'undo',
            user_id: userId,
            attendance_date: attendanceDate,
            notes: notes.trim() ? notes : null,
        }).finally(() => {
            setActionLoading(userId, 'undo', false);
        });
    }

    function isEntryLocked(entry) {
        if (!entry || !entry.locked_until) {
            return false;
        }
        const lockDate = new Date(entry.locked_until);
        if (Number.isNaN(lockDate.getTime())) {
            return false;
        }
        return Date.now() >= lockDate.getTime();
    }

    function getPhotoUrl(employee) {
        if (employee.passport_photo_path) {
            return `/storage/${employee.passport_photo_path}`;
        }
        if (employee.profile_image) {
            return `/storage/${employee.profile_image}`;
        }
        if (employee.passport_photo_url) {
            return employee.passport_photo_url;
        }
        return fallbackAvatarUrl;
    }

    function setView(view) {
        const targetView = state.embedMode ? 'grid' : (view === 'grid' ? 'grid' : 'list');
        state.currentView = targetView;
        if (elements.list) {
            const hideList = state.embedMode || state.currentView !== 'list';
            elements.list.classList.toggle('hidden', hideList);
        }
        if (elements.grid) {
            elements.grid.classList.toggle('hidden', state.currentView !== 'grid');
        }
        if (Array.isArray(elements.viewButtons)) {
            elements.viewButtons.forEach((button) => {
                const isActive = button.dataset.view === state.currentView;
                button.classList.toggle('active', isActive);
                button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
            });
        }

        if (state.currentView === 'grid') {
            loadGridEmployees();
        }
    }

    function toggleSelection(userId, shouldSelect) {
        if (state.embedMode) {
            return;
        }
        const id = Number(userId);
        if (shouldSelect) {
            state.selected.add(id);
        } else {
            state.selected.delete(id);
        }
        updateSelectionUi();
    }

    function clearSelection() {
        if (state.embedMode) {
            return;
        }
        state.selected.clear();

        if (state.dataTable) {
            state.dataTable.rows({ page: 'current' }).every(function () {
                const checkbox = this.node().querySelector('.attendance-select');
                if (checkbox) {
                    checkbox.checked = false;
                }
            });
        }

        document.querySelectorAll('#manual-attendance-grid .attendance-select').forEach((input) => {
            input.checked = false;
        });

        updateSelectionUi();
    }

    function selectAllEmployees() {
        if (state.embedMode) {
            return;
        }
        state.selected.clear();

        if (state.dataTable) {
            const rows = state.dataTable.rows({ page: 'current' });
            rows.data().toArray().forEach((employee) => {
                const id = Number(employee.id);
                if (!Number.isFinite(id) || isEntryLocked(employee.entry || null)) {
                    state.selected.delete(id);
                    return;
                }
                state.selected.add(id);
            });

            rows.every(function () {
                const checkbox = this.node().querySelector('.attendance-select');
                if (checkbox) {
                    checkbox.checked = !checkbox.disabled;
                }
                return true;
            });
        }

        document.querySelectorAll('#manual-attendance-grid .attendance-select').forEach((input) => {
            if (!input.disabled) {
                input.checked = true;
            }
        });

        updateSelectionUi();
    }

    function applyEntryUpdates(entries) {
        if (!Array.isArray(entries)) {
            return;
        }

        entries.forEach((item) => {
            if (!item || !Object.prototype.hasOwnProperty.call(item, 'entry')) {
                return;
            }
            const payloadEntry = item.entry;
            const userId = Number(item.user_id ?? (payloadEntry ? payloadEntry.user_id : null));
            if (!userId) {
                return;
            }

            if (state.dataTable) {
                const rows = state.dataTable.rows({ page: 'current' });
                rows.every(function () {
                    const data = this.data();
                    if (Number(data?.id) === userId) {
                        this.data({ ...data, entry: payloadEntry });
                    }
                    return true;
                });
            }

            const gridIndex = Array.isArray(state.gridEmployees)
                ? state.gridEmployees.findIndex((emp) => Number(emp.id) === userId)
                : -1;
            if (gridIndex >= 0) {
                state.gridEmployees[gridIndex] = {
                    ...state.gridEmployees[gridIndex],
                    entry: payloadEntry,
                };
            }

            state.selected.delete(userId);
        });

        renderGrid();
        updateSelectionUi();
    }

    function submitAttendance(payload) {
        setSaving(true);

        const sanitizedPayload = { ...payload };
        if (sanitizedPayload.notes == null) {
            delete sanitizedPayload.notes;
        }

        return fetch(endpoints.store, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify(sanitizedPayload),
        })
            .then(async (response) => {
                let data = null;
                try {
                    data = await response.clone().json();
                } catch (error) {
                    data = null;
                }

                if (!response.ok) {
                    const message = data?.message || `HTTP ${response.status}`;
                    const error = new Error(message);
                    error.payload = data;
                    throw error;
                }

                if (!data?.success) {
                    throw new Error(data?.message || 'Failed to record attendance');
                }

                if (Array.isArray(data.data?.entries)) {
                    applyEntryUpdates(data.data.entries);
                }

                notify(data.message || 'Attendance recorded successfully.', 'success');
                return data;
            })
            .catch((error) => {
                console.error('Attendance error:', error);
                notify(error.message || 'Unable to record attendance.', 'error');
                throw error;
            })
            .finally(() => {
                setSaving(false);
            });
    }

    function handlePresenceChange(userId, isPresent) {
        const attendanceDate = elements.date.value;
        if (!attendanceDate) {
            notify('Please select an attendance date', 'error');
            const checkbox = document.querySelector(`.attendance-checkbox[data-user-id="${userId}"]`);
            if (checkbox) {
                checkbox.checked = !isPresent;
            }
            return;
        }

        const notesElement = document.querySelector(`textarea[data-user-id="${userId}"]`);
        const notes = notesElement ? notesElement.value : '';

        submitAttendance({
            user_id: userId,
            attendance_date: attendanceDate,
            is_present: isPresent,
            notes,
        }).catch(() => {
            const checkbox = document.querySelector(`.attendance-checkbox[data-user-id="${userId}"]`);
            if (checkbox) {
                checkbox.checked = !isPresent;
            }
        });
    }

    function computeDefaultCheckInValue(entry) {
        if (entry && entry.check_in_time) {
            return formatDateTimeForInput(entry.check_in_time);
        }

        const attendanceDate = elements.date ? elements.date.value : '';
        return buildDefaultDateTime(attendanceDate);
    }

    function computeDefaultCheckOutValue(entry, shiftHours) {
        if (entry && entry.check_out_time) {
            return formatDateTimeForInput(entry.check_out_time);
        }

        if (entry && entry.check_in_time) {
            const baseline = new Date(entry.check_in_time);
            if (!Number.isNaN(baseline.getTime())) {
                const hours = Number.isFinite(shiftHours) && shiftHours > 0 ? shiftHours : 0;
                if (hours > 0) {
                    baseline.setHours(baseline.getHours() + hours);
                }
                return formatDateTimeForInput(baseline);
            }
        }

        return '';
    }

    function locateActionInput(userId, action) {
        return document.querySelector(`.attendance-datetime[data-user-id="${userId}"][data-action="${action}"]`);
    }

    function handleActionButtonClick(event) {
        const button = event.currentTarget;
        const { userId: rawUserId, action } = button.dataset;
        const userId = Number(rawUserId);
        const attendanceDate = elements.date ? elements.date.value : '';

        if (!userId || !action) {
            return;
        }

        if (action === 'undo') {
            handleUndo(userId);
            return;
        }

        if (!attendanceDate) {
            notify('Please select an attendance date before recording time.', 'warning');
            return;
        }

        const input = locateActionInput(userId, action);
        const value = input ? input.value : '';

        if (!value) {
            notify('Select a date and time before continuing.', 'error');
            return;
        }

        const parsed = new Date(value);
        if (Number.isNaN(parsed.getTime())) {
            notify('Invalid date/time value supplied.', 'error');
            return;
        }

        const normalizedTimestamp = new Date(parsed.getTime() - parsed.getTimezoneOffset() * 60000)
            .toISOString()
            .slice(0, 19)
            .replace('T', ' ');

        const originalButtonDisabled = button.disabled;
        const originalInputDisabled = input ? input.disabled : false;

        button.disabled = true;
        if (input) {
            input.disabled = true;
        }

        submitAttendance({
            action,
            user_id: userId,
            attendance_date: attendanceDate,
            timestamp: normalizedTimestamp,
            notes: null,
        })
            .catch(() => {
                // Error handling already surfaced; keep UI responsive
            })
            .finally(() => {
                // Buttons are replaced during re-render on success; restore otherwise
                if (document.body.contains(button)) {
                    button.disabled = originalButtonDisabled;
                }
                if (input && document.body.contains(input)) {
                    input.disabled = originalInputDisabled;
                }
            });
    }

    function renderEmployeeRow(employee, index, targetRow) {
        const rawEntry = employee.entry || null;
        const entry = normalizeEntry(rawEntry);
        const isPresent = entry ? Boolean(entry.is_present) : false;
        const checkInDisplay = formatDisplayTime(entry ? entry.check_in_time : null);
        const checkOutDisplay = formatDisplayTime(entry ? entry.check_out_time : null);
        const hoursDisplay = entry ? formatHours(entry.hours_worked) : '—';
        const shiftHours = Number(employee.shift_hours) || 0;
        const checkInDefaultValue = computeDefaultCheckInValue(entry || rawEntry) || '';
        const checkOutDefaultValue = computeDefaultCheckOutValue(entry || rawEntry, shiftHours) || '';
        const photoUrl = getPhotoUrl(employee);
        const isLocked = isEntryLocked(rawEntry || entry);
        const isSelected = state.selected.has(Number(employee.id));
        if (isLocked) {
            state.selected.delete(Number(employee.id));
        }

        const hasCheckIn = Boolean(entry && entry.check_in_time);
        const hasCheckOut = Boolean(entry && entry.check_out_time);
        const checkInDisabledAttr = (isLocked || hasCheckIn) ? 'disabled' : '';
        const checkOutDisabledAttr = (isLocked || !hasCheckIn || hasCheckOut) ? 'disabled' : '';
        const showUndo = !isLocked && (hasCheckIn || hasCheckOut);
        const undoDisabledAttr = isLocked ? 'disabled' : '';

        const secondaryId = employee.employee_number || employee.staff_no || employee.staff_number || employee.staff_id || '';
        const statusKey = entry ? (isLocked ? 'approved' : 'review') : 'draft';
        const statusLabel = entry ? (isLocked ? 'Captured' : 'Saved') : 'Pending';

        const selectionCell = state.embedMode
            ? `<td class="p-4 text-center font-medium">${index + 1}</td>`
            : `<td>
                <label class="inline-flex items-center gap-2 text-xs font-semibold text-slate-600 ${isLocked ? 'opacity-60 cursor-not-allowed' : ''}">
                    <input type="checkbox"
                           class="attendance-select"
                           data-user-id="${employee.id}"
                           ${isSelected ? 'checked' : ''}
                           ${isLocked ? 'disabled' : ''}>
                    <span>Select</span>
                </label>
            </td>`;

        const row = targetRow instanceof HTMLElement ? targetRow : document.createElement('tr');
        row.dataset.userId = employee.id;
        row.classList.remove('manual-attendance-row-disabled');
        if (isLocked) {
            row.classList.add('manual-attendance-row-disabled');
        }

        row.innerHTML = [
            selectionCell,
            `
            <td>
                <div class="flex items-center gap-3">
                    <img src="${photoUrl}"
                         alt="${employee.name}"
                         class="manual-attendance-photo"
                         onerror="this.src='${fallbackAvatarUrl}'">
                    <div class="leading-tight">
                        <div class="font-semibold text-slate-900 text-sm">${employee.name}</div>
                        ${secondaryId ? `<div class="text-xs text-slate-500">${secondaryId}</div>` : ''}
                    </div>
                </div>
            </td>
            <td class="text-sm text-slate-600">${employee.work_station || employee.department_name || 'No work station'}</td>
            <td>
                <span class="manual-attendance-status" data-status="${statusKey}">${statusLabel}</span>
            </td>
            <td class="text-sm text-slate-600">${checkInDisplay}</td>
            <td class="text-sm text-slate-600">${checkOutDisplay}</td>
            <td class="text-sm text-slate-600">${hoursDisplay}</td>
            <td>
                <div class="attendance-action-group">
                    <div class="attendance-action-row">
                        <input type="datetime-local"
                               class="attendance-datetime"
                               data-action="check_in"
                               data-user-id="${employee.id}"
                               value="${checkInDefaultValue}"
                               ${checkInDisabledAttr}>
                        <button type="button"
                                class="attendance-action-btn attendance-check-in"
                                data-action="check_in"
                                data-user-id="${employee.id}"
                                ${checkInDisabledAttr}>
                            Check In
                        </button>
                    </div>
                    <div class="attendance-action-row">
                        <input type="datetime-local"
                               class="attendance-datetime"
                               data-action="check_out"
                               data-user-id="${employee.id}"
                               value="${checkOutDefaultValue}"
                               ${checkOutDisabledAttr}>
                        <button type="button"
                                class="attendance-action-btn attendance-check-out"
                                data-action="check_out"
                                data-user-id="${employee.id}"
                                ${checkOutDisabledAttr}>
                            Check Out
                        </button>
                    </div>
                    ${showUndo ? `
                    <div class="attendance-action-row">
                        <button type="button"
                                class="attendance-action-btn attendance-undo attendance-undo-btn"
                                data-action="undo"
                                data-user-id="${employee.id}"
                                ${undoDisabledAttr}>
                            Undo
                        </button>
                    </div>` : ''}
                    <label class="attendance-present-toggle ${isLocked ? 'attendance-present-toggle-disabled' : ''}">
                        <input type="checkbox"
                               class="attendance-checkbox"
                               data-user-id="${employee.id}"
                               ${isPresent ? 'checked' : ''}
                               ${isLocked ? 'disabled' : ''}>
                        <span>Present</span>
                    </label>
                </div>
            </td>
        `,
        ].join('');

        const selectCheckbox = row.querySelector('.attendance-select');
        if (selectCheckbox) {
            selectCheckbox.addEventListener('change', (event) => {
                toggleSelection(employee.id, event.target.checked);
            });
        }

        const presentCheckbox = row.querySelector('.attendance-checkbox');
        if (presentCheckbox) {
            presentCheckbox.addEventListener('change', (event) => {
                handlePresenceChange(employee.id, event.target.checked);
            });
        }

        row.querySelectorAll('.attendance-action-btn').forEach((button) => {
            button.addEventListener('click', handleActionButtonClick);
        });

        return row;
    }

    function renderEmployeeCard(employee, index) {
        const rawEntry = employee.entry || null;
        const entry = normalizeEntry(rawEntry);
        const isPresent = entry ? Boolean(entry.is_present) : false;
        const checkInDisplay = formatDisplayTime(entry ? entry.check_in_time : null);
        const checkOutDisplay = formatDisplayTime(entry ? entry.check_out_time : null);
        const hoursDisplay = entry ? formatHours(entry.hours_worked) : '—';
        const shiftHours = Number(employee.shift_hours) || 0;
        const checkInDefaultValue = computeDefaultCheckInValue(entry || rawEntry) || '';
        const checkOutDefaultValue = computeDefaultCheckOutValue(entry || rawEntry, shiftHours) || '';
        const photoUrl = getPhotoUrl(employee);
        const isLocked = isEntryLocked(rawEntry || entry);
        const isSelected = state.selected.has(Number(employee.id));
        if (isLocked) {
            state.selected.delete(Number(employee.id));
        }

        const hasCheckIn = Boolean(entry && entry.check_in_time);
        const hasCheckOut = Boolean(entry && entry.check_out_time);
        const checkInDisabledAttr = (isLocked || hasCheckIn) ? 'disabled' : '';
        const checkOutDisabledAttr = (isLocked || !hasCheckIn || hasCheckOut) ? 'disabled' : '';
        const showUndo = !isLocked && (hasCheckIn || hasCheckOut);
        const undoDisabledAttr = isLocked ? 'disabled' : '';

        const serial = Number.isFinite(index) ? index + 1 : 1;

        const headerControls = state.embedMode
            ? `<div class="flex w-full items-start justify-between mb-3">
                    <span class="text-xs font-semibold text-slate-500">#${serial}</span>
                    ${isLocked ? '<span class="attendance-card-disabled-banner">Captured</span>' : ''}
               </div>`
            : `<div class="flex w-full items-start justify-between mb-3">
                    <label class="flex items-center gap-2 text-xs font-semibold text-slate-600 ${isLocked ? 'opacity-60 cursor-not-allowed' : ''}">
                        <input type="checkbox"
                               class="attendance-select"
                               data-user-id="${employee.id}"
                               ${isSelected ? 'checked' : ''}
                               ${isLocked ? 'disabled' : ''}>
                        <span>Select</span>
                    </label>
                    ${isLocked ? '<span class="attendance-card-disabled-banner">Captured</span>' : ''}
               </div>`;

        const card = document.createElement('div');
        card.className = 'relative bg-slate-50 rounded-lg border border-slate-200 p-4 hover:border-blue-300 transition-colors';
        card.dataset.userId = employee.id;

        if (isLocked) {
            card.classList.add('attendance-card-disabled');
        }

        card.innerHTML = `
            ${headerControls}
            <div class="flex flex-col items-center text-center space-y-3">
                 <img src="${photoUrl}"
                     alt="${employee.name}"
                     class="attendance-card-photo"
                     onerror="this.src='${fallbackAvatarUrl}'">
                <div>
                    <h3 class="font-semibold text-slate-900 text-sm">${employee.name}</h3>
                    <p class="text-xs text-slate-500">${employee.work_station || employee.department_name || 'No work station'}</p>
                </div>
                <div class="attendance-card-summary">
                    <div><span class="attendance-card-summary-label">Check-in</span><span>${checkInDisplay}</span></div>
                    <div><span class="attendance-card-summary-label">Check-out</span><span>${checkOutDisplay}</span></div>
                    <div><span class="attendance-card-summary-label">Hours</span><span>${hoursDisplay}</span></div>
                </div>
                <div class="attendance-card-actions">
                    <div class="attendance-action-row">
                        <input type="datetime-local"
                               class="attendance-datetime"
                               data-action="check_in"
                               data-user-id="${employee.id}"
                               value="${checkInDefaultValue}"
                               ${checkInDisabledAttr}>
                        <button type="button"
                                class="attendance-action-btn attendance-check-in"
                                data-action="check_in"
                                data-user-id="${employee.id}"
                                ${checkInDisabledAttr}>
                            Check In
                        </button>
                    </div>
                    <div class="attendance-action-row">
                        <input type="datetime-local"
                               class="attendance-datetime"
                               data-action="check_out"
                               data-user-id="${employee.id}"
                               value="${checkOutDefaultValue}"
                               ${checkOutDisabledAttr}>
                        <button type="button"
                                class="attendance-action-btn attendance-check-out"
                                data-action="check_out"
                                data-user-id="${employee.id}"
                                ${checkOutDisabledAttr}>
                            Check Out
                        </button>
                    </div>
                    ${showUndo ? `
                    <div class="attendance-action-row">
                        <button type="button"
                                class="attendance-action-btn attendance-undo attendance-undo-btn"
                                data-action="undo"
                                data-user-id="${employee.id}"
                                ${undoDisabledAttr}>
                            Undo
                        </button>
                    </div>` : ''}
                </div>
                <label class="attendance-present-toggle ${isLocked ? 'attendance-present-toggle-disabled' : ''}">
                    <input type="checkbox"
                           class="attendance-checkbox"
                           data-user-id="${employee.id}"
                           ${isPresent ? 'checked' : ''}
                           ${isLocked ? 'disabled' : ''}>
                    <span>Present</span>
                </label>
            </div>
        `;

        const selectCheckbox = card.querySelector('.attendance-select');
        if (selectCheckbox) {
            selectCheckbox.addEventListener('change', (event) => {
                toggleSelection(employee.id, event.target.checked);
            });
        }

        const presentCheckbox = card.querySelector('.attendance-checkbox');
        if (presentCheckbox) {
            presentCheckbox.addEventListener('change', (event) => {
                handlePresenceChange(employee.id, event.target.checked);
            });
        }

        card.querySelectorAll('.attendance-action-btn').forEach((button) => {
            button.addEventListener('click', handleActionButtonClick);
        });

        if (isLocked) {
            const overlay = document.createElement('div');
            overlay.className = 'attendance-card-overlay';
            card.appendChild(overlay);
        }

        return card;
    }

    function renderGrid() {
        if (!elements.grid) {
            return;
        }
        if (!Array.isArray(state.gridEmployees) || state.gridEmployees.length === 0) {
            const message = state.gridLoading
                ? '<div class="col-span-full text-center py-12 text-slate-500"><i class="fas fa-spinner fa-spin mr-2"></i>Loading staff...</div>'
                : '<div class="col-span-full text-center py-12 text-slate-500"><i class="fas fa-inbox mr-2"></i>No staff found.</div>';
            elements.grid.innerHTML = message;
            updateSelectionUi();
            return;
        }

        const fragment = document.createDocumentFragment();
        state.gridEmployees.forEach((employee, index) => {
            fragment.appendChild(renderEmployeeCard(employee, index));
        });

        elements.grid.innerHTML = '';
        elements.grid.appendChild(fragment);
        updateSelectionUi();
    }

    function initializeDataTable() {
        if (state.embedMode) {
            return;
        }

        if (!endpoints.datatable || !elements.table || typeof window.jQuery === 'undefined' || !jQuery.fn.DataTable) {
            return;
        }

        if (state.dataTable) {
            return;
        }

        state.dataTable = jQuery(elements.table).DataTable({
            serverSide: true,
            processing: true,
            deferRender: true,
            searching: false,
            ordering: false,
            lengthMenu: [25, 50, 100],
            pageLength: 25,
            dom: 'Brtip',
            buttons: [
                { extend: 'excelHtml5', title: 'Manual Attendance' },
                { extend: 'csvHtml5', title: 'Manual Attendance' },
                { extend: 'pdfHtml5', title: 'Manual Attendance', orientation: 'landscape', pageSize: 'A4' },
            ],
            columns: Array.from({ length: 8 }, () => ({ data: null, defaultContent: '' })),
            ajax: {
                url: endpoints.datatable,
                type: 'GET',
                data: function (params) {
                    params.date = elements.date ? elements.date.value : '';
                    params.shift_code = elements.shift ? elements.shift.value : '';
                    params.work_station = elements.workStation ? elements.workStation.value : '';
                    params.search_term = state.filters.search;
                },
                beforeSend: function () {
                    if (!elements.date || !elements.date.value) {
                        return false;
                    }

                    return true;
                },
                error: function (xhr) {
                    const message = xhr?.responseJSON?.message || 'Failed to load staff data.';
                    notify(message, 'error');
                },
            },
            language: {
                emptyTable: 'Select filters to load staff.',
                processing: '<i class="fas fa-spinner fa-spin mr-2"></i>Loading staff...'
            },
            rowCallback: function (row, data, displayIndex) {
                const api = this.api();
                const info = api.page.info();
                const absoluteIndex = info ? info.start + displayIndex : displayIndex;
                renderEmployeeRow(data, absoluteIndex, row);
            },
            drawCallback: function (settings) {
                const json = settings.json || {};
                const rows = this.api().rows({ page: 'current' }).data().toArray();
                state.employees = rows;
                const total = typeof json.recordsFiltered === 'number'
                    ? json.recordsFiltered
                    : json.recordsTotal;
                if (Number.isFinite(total)) {
                    updateCounts(total);
                } else {
                    updateCounts();
                }
                updateSelectionUi();
            },
        });
    }

    function loadGridEmployees() {
        if (!elements.grid) {
            return;
        }

        const date = elements.date ? elements.date.value : '';
        if (!date) {
            setGridLoading(false);
            state.gridEmployees = [];
            renderGrid();
            return;
        }

        const params = new URLSearchParams({
            date,
            per_page: '120',
        });

        const shift = elements.shift ? elements.shift.value : '';
        if (shift) {
            params.append('shift_code', shift);
        }

        const workStation = elements.workStation ? elements.workStation.value : '';
        if (workStation) {
            params.append('work_station', workStation);
        }

        if (state.filters.search) {
            params.append('search', state.filters.search);
        }

        setGridLoading(true);
        state.gridEmployees = [];
        renderGrid();

        fetch(`${endpoints.employees}?${params.toString()}`, {
            headers: {
                'X-CSRF-TOKEN': csrfToken,
            },
        })
            .then((response) => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                return response.json();
            })
            .then((data) => {
                if (!data.success) {
                    throw new Error(data.message || 'Failed to load staff');
                }

                state.gridEmployees = Array.isArray(data.data) ? data.data : [];
                renderGrid();
                if (state.embedMode) {
                    updateCounts(state.gridEmployees.length);
                }
            })
            .catch((error) => {
                console.error('Grid load error:', error);
                notify(error.message || 'Error loading staff.', 'error');
                state.gridEmployees = [];
                renderGrid();
            })
            .finally(() => {
                setGridLoading(false);
            });
    }

    function loadEmployees(resetPage = true) {
        const date = elements.date ? elements.date.value : '';
        if (!date) {
            notify('Please select an attendance date', 'warning');
            return;
        }

        if (elements.search) {
            state.filters.search = elements.search.value.trim();
        }

        state.selected.clear();
        updateSelectionUi();

        if (state.embedMode) {
            loadGridEmployees();
            return;
        }

        initializeDataTable();
        if (state.dataTable) {
            state.dataTable.ajax.reload(null, resetPage);
        }

        if (state.currentView === 'grid') {
            loadGridEmployees();
        }
    }

    if (elements.date) {
        elements.date.addEventListener('change', () => loadEmployees(true));
    }
    if (elements.workStation) {
        elements.workStation.addEventListener('change', () => loadEmployees(true));
    }
    if (elements.shift) {
        elements.shift.addEventListener('change', () => loadEmployees(true));
    }
    if (elements.refresh) {
        elements.refresh.addEventListener('click', () => loadEmployees(true));
    }
    if (elements.search) {
        elements.search.addEventListener('input', () => {
            clearTimeout(state.lastSearchTimer);
            state.lastSearchTimer = setTimeout(() => {
                state.filters.search = elements.search.value.trim();
                loadEmployees(true);
            }, 300);
        });
    }
    if (elements.markSelected) {
        elements.markSelected.addEventListener('click', () => {
            if (state.selected.size === 0) {
                notify('Select at least one staff member to continue.', 'info');
                return;
            }

            const attendanceDate = elements.date.value;
            if (!attendanceDate) {
                notify('Please select an attendance date', 'error');
                return;
            }

            submitAttendance({
                user_ids: Array.from(state.selected.values()),
                attendance_date: attendanceDate,
                is_present: true,
                notes: null,
            });
        });
    }
    if (elements.clearSelection) {
        elements.clearSelection.addEventListener('click', clearSelection);
    }
    if (elements.selectAll) {
        elements.selectAll.addEventListener('click', selectAllEmployees);
    }
    if (Array.isArray(elements.viewButtons) && elements.viewButtons.length) {
        elements.viewButtons.forEach((button) => {
            button.addEventListener('click', () => {
                setView(button.dataset.view === 'grid' ? 'grid' : 'list');
            });
        });
    }

    const now = new Date();
    const localToday = new Date(now.getTime() - now.getTimezoneOffset() * 60000).toISOString().split('T')[0];
    if (elements.date) {
        elements.date.max = localToday;
        if (elements.date.value !== localToday) {
            elements.date.value = localToday;
        }
    }
    setView(state.currentView);
    loadEmployees();
})();
