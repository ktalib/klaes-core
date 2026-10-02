(function () {
    'use strict';

    let tabButtons = [];
    let tabContents = [];

    const todayIso = new Date().toISOString().slice(0, 10);

    const endpoints = {
        periods: '/payroll/api/periods',
        attendance: '/payroll/api/attendance',
        dailyLogins: '/payroll/api/daily-logins',
        manualAttendance: '/manual-attendance/employees',
        salaries: '/payroll/api/salaries',
        adjustments: '/payroll/api/adjustments',
        employees: '/payroll/api/employees',
        rates: '/payroll/api/rates',
    };

    const payrollRoot = document.getElementById('payroll-app');
    const attendanceOnlyMode = payrollRoot && payrollRoot.dataset.attendanceOnly === '1';

    const state = {
        periods: [],
        selectedUnits: [],
        currentSelectedEmployee: null,
        currentSelectedRateId: null,
        rateDialogMode: 'view',
        activeTab: attendanceOnlyMode ? 'daily-logins' : null,
        filters: {
            attendancePeriod: null,
            salaryPeriod: null,
            payrollPeriod: null,
            departmentId: null,
            dailyLoginsDate: todayIso,
        },
        cache: {
            attendancePeriod: null,
            salaryPeriod: null,
            ratePeriod: null,
            ratesLoaded: false,
        },
        loading: {
            attendance: false,
            salaries: false,
            rates: false,
        },
        errors: {
            attendance: null,
            salaries: null,
            rates: null,
        },
    };

    const dataStore = {
        attendance: [],
        attendanceByUser: new Map(),
        salaries: [],
    };

    const rateStore = {
        raw: [],
        items: [],
    };

    const employeeStore = {
        items: [],
        loaded: false,
    };

    const dailyLoginsStore = {
        items: [],
        cacheKey: null,
        loading: false,
        error: null,
    };

    const manualAttendanceStore = {
        items: [],
        cacheKey: null,
        loading: false,
        error: null,
    };

    function resolvePeriodWindow(periodCode) {
        if (!periodCode || typeof periodCode !== 'string') {
            return null;
        }

        const [yearPart, monthPart] = periodCode.split('-');
        const year = Number(yearPart);
        const month = Number(monthPart);

        if (!Number.isFinite(year) || !Number.isFinite(month) || month < 1 || month > 12) {
            return null;
        }

        const start = new Date(year, month - 1, 1);
        const end = new Date(year, month, 0, 23, 59, 59, 999);

        return { start, end };
    }

    function filterSummariesByPeriod(summaries, periodWindow) {
        if (!Array.isArray(summaries) || summaries.length === 0) {
            return [];
        }

        if (!periodWindow) {
            return summaries;
        }

        const startTime = periodWindow.start.getTime();
        const endTime = periodWindow.end.getTime();

        return summaries.filter((entry) => {
            const dateValue = entry?.date;
            if (!dateValue || typeof dateValue !== 'string') {
                return true;
            }

            const parsed = new Date(dateValue);
            const entryTime = parsed.getTime();

            if (Number.isNaN(entryTime)) {
                return true;
            }

            return entryTime >= startTime && entryTime <= endTime;
        });
    }

    function rebuildCumulativeSummaries(summaries) {
        let runningTotal = 0;

        return summaries.map((entry) => {
            const hoursValue = Number(entry?.hours ?? 0);
            runningTotal = Number((runningTotal + hoursValue).toFixed(2));

            return {
                ...entry,
                hours: hoursValue,
                cumulativeHours: runningTotal,
                overtimeHours: Number(entry?.overtimeHours ?? 0),
                rawHours: Number(entry?.rawHours ?? hoursValue),
                truncated: Boolean(entry?.truncated),
            };
        });
    }

    function deriveSummariesFromBreakdown(breakdown, periodWindow) {
        if (!Array.isArray(breakdown) || breakdown.length === 0) {
            return [];
        }

        const startTime = periodWindow?.start instanceof Date ? periodWindow.start.getTime() : null;
        const endTime = periodWindow?.end instanceof Date ? periodWindow.end.getTime() : null;

        const minutesByDate = new Map();

        const addMinutes = (dateString, minutes) => {
            if (!Number.isFinite(minutes) || minutes <= 0) {
                return;
            }

            const key = dateString.slice(0, 10);
            if (!key) {
                return;
            }

            const existing = minutesByDate.get(key) || 0;
            minutesByDate.set(key, existing + minutes);
        };

        breakdown.forEach((entry) => {
            const manualDate = typeof entry?.date === 'string' ? entry.date : null;
            const manualHours = Number(entry?.hours ?? entry?.raw_hours ?? 0);

            if (manualDate && Number.isFinite(manualHours) && manualHours > 0) {
                const manualDateObj = new Date(manualDate);
                const manualTime = manualDateObj.getTime();

                if (!Number.isNaN(manualTime)) {
                    if ((startTime !== null && manualTime < startTime) || (endTime !== null && manualTime > endTime)) {
                        return;
                    }

                    addMinutes(manualDateObj.toISOString(), manualHours * 60);
                    return;
                }
            }

            const loginRaw = entry?.login_at ?? entry?.loginAt ?? entry?.login_time ?? entry?.loginTime ?? null;
            const logoutRaw = entry?.logout_at ?? entry?.logoutAt ?? entry?.logout_time ?? entry?.logoutTime ?? null;

            let loginTime = loginRaw ? new Date(loginRaw).getTime() : NaN;
            let logoutTime = logoutRaw ? new Date(logoutRaw).getTime() : NaN;

            if (Number.isNaN(loginTime) && manualDate) {
                const fallback = new Date(manualDate);
                const fallbackTime = fallback.getTime();
                if (!Number.isNaN(fallbackTime)) {
                    loginTime = fallbackTime;
                }
            }

            if (Number.isNaN(loginTime)) {
                return;
            }

            if (Number.isNaN(logoutTime)) {
                const minutesHint = Number(entry?.minutes ?? entry?.duration_minutes ?? entry?.raw_minutes ?? entry?.rawMinutes ?? 0);
                if (Number.isFinite(minutesHint) && minutesHint > 0) {
                    logoutTime = loginTime + minutesHint * 60 * 1000;
                } else {
                    logoutTime = loginTime;
                }
            }

            if (startTime !== null && loginTime < startTime) {
                loginTime = startTime;
            }

            if (endTime !== null && logoutTime > endTime) {
                logoutTime = endTime;
            }

            if (logoutTime <= loginTime) {
                return;
            }

            const windowMinutes = Math.max(0, Math.round((logoutTime - loginTime) / 60000));
            let remainingMinutes = windowMinutes;
            const minutesHint = Number(entry?.minutes ?? entry?.duration_minutes ?? entry?.raw_minutes ?? entry?.rawMinutes ?? 0);

            if (Number.isFinite(minutesHint) && minutesHint > 0 && minutesHint < remainingMinutes) {
                remainingMinutes = minutesHint;
            }

            if (remainingMinutes <= 0) {
                return;
            }

            let cursor = new Date(loginTime);
            const finalTime = logoutTime;

            while (remainingMinutes > 0 && cursor.getTime() < finalTime) {
                const dayStart = new Date(cursor.getFullYear(), cursor.getMonth(), cursor.getDate());
                const segmentStart = Math.max(cursor.getTime(), dayStart.getTime());
                const nextDay = new Date(dayStart.getTime() + 86400000);
                const segmentEnd = Math.min(finalTime, nextDay.getTime());

                let segmentMinutes = Math.max(0, Math.round((segmentEnd - segmentStart) / 60000));

                if (segmentMinutes > remainingMinutes) {
                    segmentMinutes = remainingMinutes;
                }

                if (segmentMinutes > 0) {
                    addMinutes(dayStart.toISOString(), segmentMinutes);
                    remainingMinutes -= segmentMinutes;
                }

                cursor = nextDay;
            }
        });

        return Array.from(minutesByDate.entries())
            .map(([date, minutes]) => {
                const hours = minutes / 60;
                return {
                    date,
                    hours,
                    cumulativeHours: hours,
                    overtimeHours: 0,
                    rawHours: hours,
                    truncated: false,
                };
            })
            .sort((left, right) => left.date.localeCompare(right.date));
    }

    function showActiveTab(tabId) {
        tabButtons.forEach((button) => {
            const isActive = button.getAttribute('data-tab') === tabId;
            button.classList.toggle('tab-active', isActive);
            button.classList.toggle('tab-inactive', !isActive);
        });

        tabContents.forEach((content) => {
            const isActive = content.id === `${tabId}-tab`;
            content.classList.toggle('hidden', !isActive);
        });

        if (attendanceOnlyMode) {
            state.activeTab = tabId;
        }

        updateDashboardMetrics();
    }

    function bindSelectTriggers() {
        document.querySelectorAll('.select-trigger').forEach((trigger) => {
            trigger.addEventListener('click', (event) => {
                event.stopPropagation();
                const dropdownId = `${trigger.dataset.type}-dropdown`;
                const dropdown = document.getElementById(dropdownId);
                if (!dropdown) {
                    return;
                }

                closeAllDropdowns(dropdown);
                dropdown.classList.toggle('hidden');
            });
        });
    }

    function bindSearchInputs() {
        const attendanceSearch = document.querySelector('.attendance-search-input');
        attendanceSearch?.addEventListener('input', () => {
            renderAttendanceViews();
        });

        const salarySearch = document.querySelector('.salary-search-input');
        salarySearch?.addEventListener('input', () => {
            renderSalarySection();
        });

        const ratesSearch = document.querySelector('.rates-search-input');
        ratesSearch?.addEventListener('input', () => {
            refreshRatesTab();
        });
    }

    function bindStaticButtons() {
        document.querySelector('.sync-attendance-users-btn')?.addEventListener('click', () => {
            handleSyncAttendanceUsers().catch((error) => console.error(error));
        });
        document.querySelector('.regenerate-attendance-btn')?.addEventListener('click', () => {
            handleRegenerateAttendance().catch((error) => console.error(error));
        });
        document.querySelector('.export-attendance-btn')?.addEventListener('click', handleExportAttendance);
        document.querySelector('.export-daily-logins-btn')?.addEventListener('click', handleExportDailyLogins);
        document.querySelector('.export-cumulative-hours-btn')?.addEventListener('click', handleExportCumulativeHours);
        document.querySelector('.export-dashboard-btn')?.addEventListener('click', handleExportDashboard);
        document.querySelector('.export-payroll-btn')?.addEventListener('click', handleExportPayroll);
        document.querySelector('.print-payroll-btn')?.addEventListener('click', handlePrintPayroll);
        document.querySelector('.add-rate-btn')?.addEventListener('click', () => {
            openRateDialog('create');
        });

        // Daily Logins date filter
        const dailyLoginsDateFilter = document.getElementById('daily-logins-date-filter');
        if (dailyLoginsDateFilter) {
            // Set initial value to today
            dailyLoginsDateFilter.value = state.filters.dailyLoginsDate;
            dailyLoginsDateFilter.addEventListener('change', (event) => {
                state.filters.dailyLoginsDate = event.target.value;
                Promise.all([
                    loadDailyLogins(true),
                    loadManualAttendance(true),
                ]).catch((error) => console.error(error));
            });
        }
    }

    function bindDialogButtons() {
        document.querySelector('.cancel-dialog-btn')?.addEventListener('click', () => {
            toggleDialog('bonus-dialog', false);
            resetBonusDialog();
        });

        const addPayrollButton = document.querySelector('.add-payroll-btn');
        addPayrollButton?.addEventListener('click', () => {
            handleBonusSubmit().catch((error) => console.error(error));
        });

        document.querySelector('.cancel-rate-dialog-btn')?.addEventListener('click', () => {
            toggleDialog('edit-rate-dialog', false);
            resetRateDialog();
        });

        const saveRateButton = document.querySelector('.save-rate-btn');
        saveRateButton?.addEventListener('click', () => {
            handleRateSave().catch((error) => console.error(error));
        });

        const rateUserSelect = document.getElementById('rate-user');
        rateUserSelect?.addEventListener('change', (event) => {
            const selected = Number.parseInt(event.target.value, 10);
            state.currentSelectedEmployee = Number.isFinite(selected) ? selected : null;
            prefillRateDialogFromExisting(state.currentSelectedEmployee);
        });

        document.querySelector('.close-attendance-breakdown-btn')?.addEventListener('click', () => {
            toggleDialog('attendance-breakdown-dialog', false);
            resetAttendanceBreakdown();
        });
    }

    function bindDocumentHandlers() {
        document.addEventListener('click', (event) => {
            handleDocumentClick(event).catch((error) => console.error(error));
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                toggleDialog('bonus-dialog', false);
                toggleDialog('edit-rate-dialog', false);
                closeAllDropdowns();
            }
        });
    }

    function initializeTabs() {
        tabButtons = Array.from(document.querySelectorAll('.tab-button'));
        tabContents = Array.from(document.querySelectorAll('.tab-content'));

        tabButtons.forEach((button) => {
            button.addEventListener('click', () => {
                const tabId = button.getAttribute('data-tab');
                if (tabId) {
                    showActiveTab(tabId);
                }
            });
        });

        const activeButton = tabButtons.find((button) => button.classList.contains('tab-active')) || tabButtons[0];
        if (activeButton) {
            const defaultTab = activeButton.getAttribute('data-tab');
            if (defaultTab) {
                showActiveTab(defaultTab);
            }
        }
    }

    async function initializePage() {
        initializeTabs();
        bindSelectTriggers();
        bindSearchInputs();
        bindStaticButtons();
        bindDialogButtons();
        bindDocumentHandlers();
        updateUnitLabel();
        renderSelectedUnits();
        updatePayrollDescription();

        try {
            await loadEmployees();
            await loadPeriods();
            renderPeriodDropdowns();
            updatePeriodLabels();

            await Promise.all([
                ensureAttendanceData(true),
                ensureSalaryData(true),
                loadRates(true),
            ]);

            await loadDailyLogins(true);
            await loadManualAttendance(true);
        } catch (error) {
            console.error('Failed to initialize payroll dashboard', error);
        } finally {
            renderAttendanceViews();
            renderSalarySection();
            renderPayrollSection();
            refreshRatesTab();
            updateDashboardMetrics();
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        initializePage().catch((error) => console.error(error));
    });

    async function handleDocumentClick(event) {
        const target = event.target;

        if (target.classList.contains('select-option')) {
            await handleSelectOption(target);
            return;
        }

        if (target.classList.contains('remove-unit-btn')) {
            const unit = target.getAttribute('data-unit');
            if (unit) {
                state.selectedUnits = state.selectedUnits.filter((item) => item !== unit);
                renderPayrollSection();
            }
            return;
        }

        const addBonusButton = target.closest('.add-bonus-btn');
        if (addBonusButton) {
            const employeeId = addBonusButton.getAttribute('data-employee-id');
            const employeeName = addBonusButton.getAttribute('data-employee-name');
            if (!employeeId || !employeeName) {
                return;
            }

            state.currentSelectedEmployee = Number.parseInt(employeeId, 10);
            const description = document.getElementById('dialog-description');
            if (description) {
                description.textContent = `Add bonus or extra hours for ${employeeName} (${employeeId})`;
            }
            prefillBonusDialog(state.currentSelectedEmployee);
            toggleDialog('bonus-dialog', true);
            return;
        }

        const editRateButton = target.closest('.edit-rate-btn');
        if (editRateButton) {
            const rateId = Number(editRateButton.getAttribute('data-rate-id'));
            const rate = rateStore.items.find((entry) => entry.id === rateId);
            if (!rate) {
                return;
            }
            openRateDialog('edit', rate);
            return;
        }

        const toggleRateButton = target.closest('.toggle-rate-btn');
        if (toggleRateButton) {
            const rateId = Number(toggleRateButton.getAttribute('data-rate-id'));
            const nextStatus = toggleRateButton.getAttribute('data-next-status');
            if (!Number.isFinite(rateId) || !nextStatus) {
                return;
            }

            const makeActive = nextStatus === 'active';
            const promptMessage = makeActive
                ? 'Activate this rate? It will deactivate other active rates for the employee.'
                : 'Deactivate this rate? The employee will use other effective rates if available.';

            if (!confirm(promptMessage)) {
                return;
            }

            toggleRateStatus(rateId, makeActive).catch((error) => alert(error.message || 'Failed to update the rate status.'));
            return;
        }

        const breakdownButton = target.closest('.attendance-breakdown-btn');
        if (breakdownButton) {
            const userKey = breakdownButton.getAttribute('data-user-id');
            if (userKey) {
                openAttendanceBreakdown(userKey);
            }
            return;
        }

        if (target.classList.contains('dialog-overlay')) {
            toggleDialog(target.id, false);
            if (target.id === 'bonus-dialog') {
                resetBonusDialog();
            }
            if (target.id === 'edit-rate-dialog') {
                resetRateDialog();
            }
            if (target.id === 'attendance-breakdown-dialog') {
                resetAttendanceBreakdown();
            }
        }

        if (!target.closest('.select-container')) {
            closeAllDropdowns();
        }
    }

    async function handleSelectOption(option) {
        const dropdown = option.closest('.select-dropdown');
        if (!dropdown) {
            return;
        }

        const dropdownId = dropdown.id;
        const value = option.getAttribute('data-value');
        dropdown.classList.add('hidden');

        if (!value) {
            return;
        }

        if (dropdownId === 'attendance-month-dropdown' || dropdownId === 'salary-month-dropdown' || dropdownId === 'payroll-month-dropdown') {
            setActivePeriod(value);
            updatePeriodLabels();
            await Promise.all([
                ensureAttendanceData(true),
                ensureSalaryData(true),
            ]);
            await Promise.all([
                loadRates(),
                loadDailyLogins(true),
                loadManualAttendance(true),
            ]);
            renderAttendanceViews();
            renderDailyLoginsSection();
            renderSalarySection();
            renderPayrollSection();
            updatePayrollDescription();
            updateDashboardMetrics();
            return;
        }

        if (dropdownId === 'payroll-unit-dropdown') {
            if (value === 'all') {
                state.selectedUnits = [];
            } else if (!state.selectedUnits.includes(value)) {
                state.selectedUnits.push(value);
            }
            renderPayrollSection();
        }
    }

    function setActivePeriod(periodCode) {
        state.filters.attendancePeriod = periodCode;
        state.filters.salaryPeriod = periodCode;
        state.filters.payrollPeriod = periodCode;
        state.cache.attendancePeriod = null;
        state.cache.salaryPeriod = null;
        state.cache.ratePeriod = null;
        state.cache.ratesLoaded = false;
        dailyLoginsStore.items = [];
        dailyLoginsStore.cacheKey = null;
        dailyLoginsStore.error = null;
    }

    async function loadPeriods() {
        try {
            const response = await fetchJson(`${endpoints.periods}?limit=36`);
            state.periods = response?.data?.periods || [];

            if (state.periods.length) {
                const today = new Date();
                const currentCode = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}`;
                const exactMatch = state.periods.find((period) => period.period_code === currentCode);
                const windowMatch = state.periods.find((period) => {
                    const start = new Date(period.start_date);
                    const end = new Date(period.end_date);
                    return start <= today && end >= today;
                });
                const defaultPeriod = exactMatch || windowMatch || state.periods[0];
                if (defaultPeriod) {
                    setActivePeriod(defaultPeriod.period_code);
                }
            }
        } catch (error) {
            console.error('Unable to load payroll periods', error);
        }
    }

    async function ensureAttendanceData(force = false) {
        const period = state.filters.attendancePeriod;
        if (!period) {
            dataStore.attendance = [];
            dataStore.attendanceByUser = new Map();
            renderAttendanceViews();
            updateDashboardMetrics();
            return;
        }

        if (!force && state.cache.attendancePeriod === period) {
            updateDashboardMetrics();
            return;
        }

        state.loading.attendance = true;
        state.errors.attendance = null;
        renderAttendanceViews();

        try {
            const query = new URLSearchParams({ period });
            if (state.filters.departmentId) {
                query.append('department_id', state.filters.departmentId);
            }

            const response = await fetchJson(`${endpoints.attendance}?${query.toString()}`);
            const records = response?.data?.attendance || [];

            dataStore.attendance = records.map(normalizeAttendanceRecord);
            dataStore.attendanceByUser = new Map(dataStore.attendance.map((record) => [String(record.userId), record]));
            state.cache.attendancePeriod = period;
        } catch (error) {
            state.errors.attendance = error.message || 'Unable to load attendance data.';
            dataStore.attendance = [];
            dataStore.attendanceByUser = new Map();
        } finally {
            state.loading.attendance = false;
            renderAttendanceViews();
            updateDashboardMetrics();
        }
    }

    async function loadDailyLogins(force = false) {
        const period = state.filters.attendancePeriod;
        const date = state.filters.dailyLoginsDate;

        if (!period || !date) {
            dailyLoginsStore.items = [];
            dailyLoginsStore.cacheKey = `${period || 'none'}|${date || 'none'}`;
            renderDailyLoginsSection();
            updateDashboardMetrics();
            return;
        }

        const cacheKey = `${period}|${date}`;
        if (!force && dailyLoginsStore.cacheKey === cacheKey && dailyLoginsStore.items.length) {
            updateDashboardMetrics();
            return;
        }

        dailyLoginsStore.loading = true;
        dailyLoginsStore.error = null;
        renderDailyLoginsSection();

        const query = new URLSearchParams({ period, date });

        try {
            const response = await fetch(`${endpoints.dailyLogins}?${query.toString()}`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
            });

            if (!response.ok) {
                throw new Error(`Unable to load daily login sessions (status ${response.status}).`);
            }

            const payload = await response.json();
            if (!payload.success) {
                throw new Error(payload.message || 'Unable to load daily login sessions.');
            }

            dailyLoginsStore.items = Array.isArray(payload.data?.items) ? payload.data.items : [];
            dailyLoginsStore.cacheKey = cacheKey;
        } catch (error) {
            dailyLoginsStore.error = error.message || 'Unable to load daily login sessions.';
            dailyLoginsStore.items = [];
            dailyLoginsStore.cacheKey = cacheKey;
            console.error(error);
        } finally {
            dailyLoginsStore.loading = false;
            renderDailyLoginsSection();
            updateDashboardMetrics();
        }
    }

    async function loadManualAttendance(force = false) {
        const date = state.filters.dailyLoginsDate;

        if (!date) {
            manualAttendanceStore.items = [];
            manualAttendanceStore.cacheKey = `none|${state.filters.departmentId ?? 'all'}`;
            renderManualAttendanceSection();
            updateDashboardMetrics();
            return;
        }

        const departmentKey = state.filters.departmentId ?? '';
        const cacheKey = `${date}|${departmentKey}`;

        if (!force && manualAttendanceStore.cacheKey === cacheKey) {
            updateDashboardMetrics();
            return;
        }

        manualAttendanceStore.loading = true;
        manualAttendanceStore.error = null;
        manualAttendanceStore.cacheKey = cacheKey;
        renderManualAttendanceSection();

        try {
            const query = new URLSearchParams({ date });
            if (departmentKey !== '' && departmentKey !== null) {
                query.append('department_id', departmentKey);
            }

            const response = await fetchJson(`${endpoints.manualAttendance}?${query.toString()}`);
            const payload = Array.isArray(response?.data) ? response.data : [];

            manualAttendanceStore.items = payload.map((item) => ({
                ...item,
                entry: item.entry || null,
            }));
        } catch (error) {
            manualAttendanceStore.error = error?.message || 'Unable to load manual attendance records.';
            manualAttendanceStore.items = [];
        } finally {
            manualAttendanceStore.loading = false;
            renderDailyLoginsSection();
            renderManualAttendanceSection();
            updateDashboardMetrics();
        }
    }

    async function ensureSalaryData(force = false) {
        const period = state.filters.salaryPeriod;
        if (!period) {
            dataStore.salaries = [];
            renderSalarySection();
            renderPayrollSection();
            updateDashboardMetrics();
            return;
        }

        if (!force && state.cache.salaryPeriod === period) {
            renderUnitDropdown();
            updateDashboardMetrics();
            return;
        }

        state.loading.salaries = true;
        state.errors.salaries = null;
        renderSalarySection();

        try {
            const query = new URLSearchParams({ period });
            if (state.filters.departmentId) {
                query.append('department_id', state.filters.departmentId);
            }

            const response = await fetchJson(`${endpoints.salaries}?${query.toString()}`);
            const records = response?.data?.salaries || [];

            dataStore.salaries = records.map((record) => normalizeSalaryRecord(record));
            if (Array.isArray(rateStore.raw) && rateStore.raw.length) {
                rateStore.items = rateStore.raw.map(mapRateRecord);
                refreshRatesTab();
            }
            state.cache.salaryPeriod = period;
            renderUnitDropdown();
        } catch (error) {
            state.errors.salaries = error.message || 'Unable to load salary data.';
            dataStore.salaries = [];
            if (Array.isArray(rateStore.raw) && rateStore.raw.length) {
                rateStore.items = rateStore.raw.map(mapRateRecord);
                refreshRatesTab();
            }
        } finally {
            state.loading.salaries = false;
            renderSalarySection();
            renderPayrollSection();
            updateDashboardMetrics();
        }
    }

    async function loadEmployees(force = false) {
        if (!force && employeeStore.loaded) {
            return;
        }

        try {
            const response = await fetchJson(endpoints.employees);
            const employees = response?.data?.employees || [];

            employeeStore.items = employees.map((emp) => ({
                id: emp.id,
                name: emp.name,
                workStation: emp.work_station,
                departmentId: emp.department_id,
                departmentName: emp.department_name,
                position: emp.position,
                shiftHours: emp.shift_hours,
            }));
            employeeStore.loaded = true;
        } catch (error) {
            console.error('Unable to load employees', error);
            employeeStore.items = [];
        }
    }

    async function loadRates(force = false) {
        if (state.loading.rates) {
            return;
        }

        if (force) {
            state.cache.ratesLoaded = false;
            state.cache.ratePeriod = null;
        }

        const period = state.filters.payrollPeriod
            || state.filters.salaryPeriod
            || state.filters.attendancePeriod
            || null;

        if (!force && state.cache.ratesLoaded && state.cache.ratePeriod === period) {
            refreshRatesTab();
            return;
        }

        state.loading.rates = true;
        state.errors.rates = null;
        refreshRatesTab();

        try {
            const params = new URLSearchParams({ limit: '200' });
            if (period) {
                params.append('period_code', period);
            }

            const response = await fetchJson(`${endpoints.rates}?${params.toString()}`);
            const rates = response?.data?.rates || [];

            rateStore.raw = rates;
            rateStore.items = rates.map(mapRateRecord);
            state.cache.ratesLoaded = true;
            state.cache.ratePeriod = period;
        } catch (error) {
            state.errors.rates = error.message || 'Unable to load payroll rates.';
            rateStore.raw = [];
            rateStore.items = [];
            state.cache.ratesLoaded = false;
            state.cache.ratePeriod = null;
        } finally {
            state.loading.rates = false;
            refreshRatesTab();
        }
    }

    function renderPeriodDropdowns() {
        const periodOptions = state.periods
            .map((period) => `<div class="px-3 py-2 cursor-pointer hover:bg-gray-100 select-option" data-value="${period.period_code}">${formatPeriodLabel(period.period_code)}</div>`)
            .join('');

        ['attendance-month-dropdown', 'salary-month-dropdown', 'payroll-month-dropdown'].forEach((id) => {
            const dropdown = document.getElementById(id);
            if (!dropdown) {
                return;
            }

            dropdown.innerHTML = `<div class="py-1">${periodOptions || '<div class="px-3 py-2 text-sm text-gray-500">No periods available</div>'}</div>`;
        });

        updatePeriodLabels();
    }

    function updatePeriodLabels() {
        updateTriggerLabel('attendance-month', state.filters.attendancePeriod);
        updateTriggerLabel('salary-month', state.filters.salaryPeriod);
        updateTriggerLabel('payroll-month', state.filters.payrollPeriod);
    }

    function updateTriggerLabel(type, period) {
        const trigger = document.querySelector(`.select-trigger[data-type="${type}"] span`);
        if (trigger) {
            trigger.textContent = period ? formatPeriodLabel(period) : 'Select period';
        }
    }

    function renderAttendanceViews() {
        renderAttendanceSection();
        renderDailyLoginsSection();
        renderManualAttendanceSection();
        renderCumulativeHoursSection();
    }

    function renderAttendanceSection() {
        updateTriggerLabel('attendance-month', state.filters.attendancePeriod);

        const tableBody = document.getElementById('attendance-table-body');
        if (!tableBody) {
            return;
        }

        tableBody.innerHTML = '';
        const colSpan = 13;

        if (state.loading.attendance) {
            renderMessageRow(tableBody, colSpan, 'Loading attendance records…');
            return;
        }

        if (state.errors.attendance) {
            renderMessageRow(tableBody, colSpan, state.errors.attendance, 'error');
            return;
        }

        const searchTerm = (document.querySelector('.attendance-search-input')?.value || '').trim().toLowerCase();
        const visibleRecords = dataStore.attendance.filter((record) => {
            if (!searchTerm) {
                return true;
            }

            return (
                String(record.userId).toLowerCase().includes(searchTerm) ||
                (record.userName || '').toLowerCase().includes(searchTerm)
            );
        }).sort((left, right) => {
            if (right.loginDays !== left.loginDays) {
                return right.loginDays - left.loginDays;
            }

            if (right.hoursWorked !== left.hoursWorked) {
                return right.hoursWorked - left.hoursWorked;
            }

            return (left.userName || '').localeCompare(right.userName || '');
        });

        if (!visibleRecords.length) {
            renderMessageRow(tableBody, colSpan, 'No attendance records for this period.');
            return;
        }

        visibleRecords.forEach((record, index) => {
            const row = document.createElement('tr');
            row.className = 'border-t hover:bg-gray-50';

            const badge = getAttendanceBadge(record.attendancePercentage);
            const shiftBadge = record.shiftHours && record.shiftHours >= 8 ? 'badge-8hrs' : 'badge-4hrs';
            const shiftText = record.shiftHours ? `${record.shiftHours.toFixed(1)}h` : '-';
            const unitLabel = record.workStation || '—';
            const overtime = record.overtimeAuthorized
                ? (record.overtimeHours > 0
                    ? `<span class="overtime-badge">+${record.overtimeHours.toFixed(1)}h</span>`
                    : '<span class="text-gray-400">0h</span>')
                : '<span class="text-xs text-gray-500">Not authorized</span>';
            const serial = index + 1;
            const progressPercent = record.expectedHours > 0
                ? Math.min(100, Math.round((record.cumulativeHours / record.expectedHours) * 100))
                : null;
            const progressBar = progressPercent !== null
                ? `<div class="mt-1 h-1.5 rounded-full bg-gray-200"><div class="h-1.5 rounded-full bg-blue-500" style="width:${progressPercent}%"></div></div>`
                : '';
            const progressText = record.expectedHours > 0
                ? `<div class="mt-1 text-xs text-gray-500">${record.cumulativeHours.toFixed(1)}h of ${record.expectedHours.toFixed(1)}h</div>`
                : '';
            const breakdownButton = record.dailySummaries.length
                ? `<button class="attendance-breakdown-btn rounded-md border border-gray-300 px-3 py-1 text-sm text-blue-600 hover:bg-blue-50" data-user-id="${record.userId}">View</button>`
                : '<span class="text-xs text-gray-400">No sessions</span>';
            const expectedDaysLabel = record.expectedLoginDays > 0
                ? `<span class="ml-1 text-xs text-gray-500">/ ${record.expectedLoginDays}</span>`
                : '';
            const overtimeCapLabel = record.overtimeAuthorized && record.overtimeCapHours !== null
                ? `<div class="text-xs text-gray-500">Cap: ${record.overtimeCapHours.toFixed(1)}h</div>`
                : '';
            const attendancePercentDisplay = Number.isFinite(record.attendancePercentage)
                ? record.attendancePercentage.toFixed(1)
                : record.attendancePercentage;

            row.innerHTML = [
                `<td class="p-4 text-center font-medium">${serial}</td>`,
                `<td class="p-4 font-medium">${record.userId}</td>`,
                `<td class="p-4">${record.userName || 'Unknown User'}</td>`,
                `<td class="p-4">${record.departmentName || '—'}</td>`,
                `<td class="p-4">${unitLabel}</td>`,
                `<td class="p-4 text-center"><span class="${shiftBadge}">${shiftText}</span></td>`,
                `<td class="p-4 text-center">${breakdownButton}</td>`,
                `<td class="p-4 text-center font-semibold">${record.hoursWorked.toFixed(1)}h${progressBar}${progressText}</td>`,
                `<td class="p-4 text-center"><span class="font-semibold">${record.loginDays.toFixed(1)}</span><span class="ml-1 text-xs text-gray-500">days${expectedDaysLabel}</span></td>`,
                `<td class="p-4 text-center">${overtime}${overtimeCapLabel}</td>`,
                `<td class="p-4 text-center"><span class="font-semibold">${attendancePercentDisplay}%</span></td>`,
                `<td class="p-4 text-center"><span class="${badge.className}">${badge.label}</span></td>`,
            ].join('');

            tableBody.appendChild(row);
        });
    }

    function getSortedDailyLoginEntries() {
        const entries = Array.isArray(dailyLoginsStore.items)
            ? dailyLoginsStore.items.map((item) => ({ ...item, __isManual: Boolean(item.__isManual) }))
            : [];

        const manualRecords = Array.isArray(manualAttendanceStore.items)
            ? manualAttendanceStore.items.filter((record) => {
                if (!record || !record.entry) {
                    return false;
                }

                const entry = record.entry;
                const hasAttendance = Boolean(entry.check_in_time || entry.check_out_time || entry.is_present);
                if (hasAttendance) {
                    return true;
                }

                return entry.status && entry.status !== 'draft';
            })
            : [];

        const existingUserIds = new Set(entries.map((entry) => String(entry.user_id ?? entry.userId ?? '')));

        manualRecords.forEach((record) => {
            const entry = record.entry;
            if (!entry) {
                return;
            }

            const userId = String(record.id ?? entry.user_id ?? '');
            if (!userId) {
                return;
            }

            const shiftHint = Number(record.shift_hours ?? record.shiftHours ?? entry.shift_type ?? 0);
            const shiftHours = Number.isFinite(shiftHint) && shiftHint > 0 ? shiftHint : null;
            const manualCheckout = entry.check_out_time || entry.checkOutTime || null;
            const workedHoursRaw = Number(
                entry.hours_worked ??
                entry.hoursWorked ??
                entry.login_hours ??
                entry.loginHours ??
                (shiftHours && entry.is_present ? shiftHours : 0)
            );
            const workedHours = Number.isFinite(workedHoursRaw) && workedHoursRaw >= 0 ? workedHoursRaw : 0;

            if (existingUserIds.has(userId)) {
                const existingIndex = entries.findIndex((item) => String(item.user_id ?? item.userId ?? '') === userId);
                if (existingIndex !== -1) {
                    const existingEntry = entries[existingIndex];
                    const currentMinutes = Number(existingEntry.total_minutes ?? 0);
                    const manualMinutes = workedHours * 60;
                    const preferManualMetrics = !Number.isFinite(currentMinutes) || currentMinutes === 0 || manualMinutes > currentMinutes;
                    const latenessData = preferManualMetrics
                        ? computeManualLatenessData(entry.check_in_time, entry.shift_type || record.shift_code || existingEntry.shift_code || 'full_day')
                        : { lateLogin: existingEntry.late_login, latenessMinutes: existingEntry.lateness_minutes, latenessReference: existingEntry.lateness_reference, shiftStart: existingEntry.shift_start };
                    const manualLogout = manualCheckout || existingEntry.last_logout || existingEntry.last_logout_fallback || null;
                    const manualIsOnline = !manualCheckout;

                    entries[existingIndex] = {
                        ...existingEntry,
                        department_name: existingEntry.department_name || record.department_name || null,
                        work_station: existingEntry.work_station || record.work_station || null,
                        shift_hours: preferManualMetrics ? shiftHours : (existingEntry.shift_hours ?? shiftHours),
                        shift_code: preferManualMetrics ? (entry.shift_type || record.shift_code || existingEntry.shift_code || 'full_day') : existingEntry.shift_code,
                        shift_start: latenessData.shiftStart,
                        first_login: preferManualMetrics ? (entry.check_in_time || existingEntry.first_login || null) : (existingEntry.first_login || entry.check_in_time || null),
                        last_logout: preferManualMetrics ? manualLogout : (existingEntry.last_logout ?? manualCheckout ?? null),
                        last_logout_fallback: preferManualMetrics ? (manualLogout ?? existingEntry.last_logout_fallback ?? null) : (existingEntry.last_logout_fallback ?? manualCheckout ?? existingEntry.last_logout ?? null),
                        total_hours: preferManualMetrics ? workedHours : (existingEntry.total_hours ?? workedHours),
                        total_minutes: preferManualMetrics ? manualMinutes : (existingEntry.total_minutes ?? manualMinutes),
                        attendance_status: entry.is_present ? 'present' : (existingEntry.attendance_status || 'absent'),
                        late_login: latenessData.lateLogin,
                        lateness_minutes: latenessData.latenessMinutes,
                        lateness_reference: latenessData.latenessReference,
                        is_online: preferManualMetrics ? manualIsOnline : (existingEntry.is_online ?? manualIsOnline),
                        manual_entry_id: entry.id ?? existingEntry.manual_entry_id ?? null,
                        __isManual: true,
                    };
                }
                return;
            }

            const latenessData = computeManualLatenessData(entry.check_in_time, entry.shift_type || record.shift_code || 'full_day');

            entries.push({
                user_id: record.id,
                user_name: record.name,
                department_name: record.department_name || null,
                work_station: record.work_station || null,
                shift_hours: shiftHours,
                shift_code: entry.shift_type || record.shift_code || 'full_day',
                shift_start: latenessData.shiftStart,
                first_login: entry.check_in_time || null,
                last_logout: manualCheckout || null,
                last_logout_fallback: manualCheckout || null,
                total_hours: workedHours,
                total_minutes: workedHours * 60,
                attendance_status: entry.is_present ? 'present' : 'absent',
                late_login: latenessData.lateLogin,
                lateness_minutes: latenessData.latenessMinutes,
                lateness_reference: latenessData.latenessReference,
                is_online: !manualCheckout,
                manual_entry_id: entry.id ?? null,
                __isManual: true,
            });

            existingUserIds.add(userId);
        });

        entries.sort((left, right) => {
            const leftManual = Boolean(left.__isManual);
            const rightManual = Boolean(right.__isManual);

            if (leftManual && !rightManual) {
                return 1;
            }
            if (!leftManual && rightManual) {
                return -1;
            }

            const leftStart = resolveSortTimestamp(left.first_login);
            const rightStart = resolveSortTimestamp(right.first_login);

            if (leftStart !== rightStart) {
                return leftStart - rightStart;
            }

            return (left.user_name || '').localeCompare(right.user_name || '');
        });

        return entries;
    }

    function computeDailyLoginStatus(entry) {
        const statusRaw = (entry.attendance_status || '').toString().trim().toLowerCase();
        const totalMinutesValue = Number(entry.total_minutes || 0);

        let presenceKey = 'unknown';
        let presenceLabel = '—';

        if (statusRaw === 'present') {
            presenceKey = 'present';
            presenceLabel = 'Present';
        } else if (statusRaw === 'absent') {
            presenceKey = 'absent';
            presenceLabel = 'Absent';
        } else if (statusRaw) {
            presenceKey = 'other';
            presenceLabel = statusRaw.replace(/\b\w/g, (char) => char.toUpperCase());
        } else if (totalMinutesValue > 0) {
            presenceKey = 'present';
            presenceLabel = 'Present';
        } else if (Number.isFinite(totalMinutesValue) && totalMinutesValue === 0) {
            presenceKey = 'absent';
            presenceLabel = 'Absent';
        }

        const lateLoginValue = entry.late_login;
        const hasLateInfo = lateLoginValue === true || lateLoginValue === false;
        const latenessMinutes = Number(entry.lateness_minutes);
        const latenessHours = Number.isFinite(latenessMinutes) ? Math.max(latenessMinutes / 60, 0) : null;
        const shiftStartLabel = entry.shift_start ? formatTime(entry.shift_start) : null;

        let latenessLabel = 'No data';
        let latenessKey = 'unknown';

        if ((presenceKey === 'absent' || totalMinutesValue === 0) && !hasLateInfo) {
            latenessLabel = 'No login recorded';
            latenessKey = 'none';
        } else if (hasLateInfo) {
            if (lateLoginValue) {
                latenessKey = 'late';
                latenessLabel = Number.isFinite(latenessHours) && latenessHours > 0
                    ? `Late by ${latenessHours.toFixed(2)}h`
                    : 'Late login';
            } else {
                latenessKey = 'on_time';
                latenessLabel = Number.isFinite(latenessHours) && latenessHours > 0
                    ? `On time (+${latenessHours.toFixed(2)}h)`
                    : 'On time';
            }
        } else if (shiftStartLabel) {
            latenessKey = 'reference';
            latenessLabel = `Shift ${shiftStartLabel}`;
        }

        return {
            presenceKey,
            presenceLabel,
            latenessKey,
            latenessLabel,
            shiftStartLabel,
            latenessHours,
            hasLateInfo,
            totalMinutesValue,
            hasSnapshot: statusRaw !== '',
        };
    }

    function renderDailyLoginsSection() {
        const tableBody = document.getElementById('daily-logins-table-body');
        if (!tableBody) {
            return;
        }

        tableBody.innerHTML = '';
        const colSpan = 13;

        if (!state.filters.attendancePeriod) {
            renderMessageRow(tableBody, colSpan, 'Pick a payroll period to view login sessions.');
            return;
        }

        if (!state.filters.dailyLoginsDate) {
            renderMessageRow(tableBody, colSpan, 'Pick a date to view login sessions.');
            return;
        }

        if (dailyLoginsStore.loading) {
            renderMessageRow(tableBody, colSpan, 'Loading daily login sessions…');
            return;
        }

        if (dailyLoginsStore.error) {
            renderMessageRow(tableBody, colSpan, dailyLoginsStore.error, 'error');
            return;
        }

        const sortedEntries = getSortedDailyLoginEntries();

        if (!sortedEntries.length) {
            renderMessageRow(tableBody, colSpan, `No attendance records captured for ${state.filters.dailyLoginsDate}.`);
            return;
        }

        sortedEntries.forEach((entry, index) => {
            const row = document.createElement('tr');
            row.className = 'border-t hover:bg-gray-50';

            const shiftHours = Number(entry.shift_hours ?? 0);
            const totalHours = resolveDailyLoginTotalHours(entry);
            const logoutDisplay = entry.is_online
                ? 'Awaiting logout'
                : (entry.last_logout ? formatDateTimeWithTime(entry.last_logout) : '—');
            const baseBadgeClass = 'inline-flex items-center justify-center rounded-full px-2 py-0.5 text-xs font-medium ';
            const statusInfo = computeDailyLoginStatus(entry);
            const departmentName = entry.department_name || '—';
            const workStationLabel = entry.work_station || '—';
            const isManual = Boolean(entry.__isManual);

            let presenceClass = `${baseBadgeClass}bg-gray-100 text-gray-600`;
            if (statusInfo.presenceKey === 'present') {
                presenceClass = `${baseBadgeClass}bg-emerald-100 text-emerald-700`;
            } else if (statusInfo.presenceKey === 'absent') {
                presenceClass = `${baseBadgeClass}bg-rose-100 text-rose-700`;
            }

            let latenessClass = `${baseBadgeClass}bg-gray-100 text-gray-600`;
            if (statusInfo.latenessKey === 'late') {
                latenessClass = `${baseBadgeClass}bg-amber-100 text-amber-700`;
            } else if (statusInfo.latenessKey === 'on_time') {
                latenessClass = `${baseBadgeClass}bg-blue-100 text-blue-700`;
            }

            const statusSegments = [];

            if (isManual) {
                statusSegments.push('<span class="text-xs text-blue-500">Manual attendance</span>');
            }

            if (statusInfo.presenceKey === 'unknown') {
                statusSegments.push('<span class="text-xs text-gray-400">No snapshot</span>');
            } else {
                statusSegments.push(`<span class="${presenceClass}">${statusInfo.presenceLabel}</span>`);
            }

            if (statusInfo.presenceKey !== 'unknown' || statusInfo.latenessKey !== 'unknown') {
                statusSegments.push(`<span class="${latenessClass}">${statusInfo.latenessLabel}</span>`);
                if (statusInfo.shiftStartLabel) {
                    statusSegments.push(`<span class="text-xs text-gray-500">Shift start ${statusInfo.shiftStartLabel}</span>`);
                }
            }

            const statusContent = `<div class="flex flex-col gap-1">${statusSegments.join('')}</div>`;

            row.innerHTML = [
                `<td class="p-4 text-center font-medium">${index + 1}</td>`,
                `<td class="p-4 font-medium">${entry.user_id}</td>`,
                `<td class="p-4">${entry.user_name}</td>`,
                `<td class="p-4">${departmentName}</td>`,
                `<td class="p-4">${workStationLabel}</td>`,
                `<td class="p-4">${statusContent}</td>`,
                `<td class="p-4">${formatDateTimeWithTime(entry.first_login)}</td>`,
                `<td class="p-4">${logoutDisplay}</td>`,
                `<td class="p-4 text-center">${shiftHours > 0 ? formatHours(shiftHours) : '—'}</td>`,
                `<td class="p-4 text-center font-semibold">${formatHours(totalHours)}</td>`,
            ].join('');
            tableBody.appendChild(row);
        });
    }

    function renderManualAttendanceSection() {
        const tableBody = document.getElementById('manual-attendance-table-body');
        if (!tableBody) {
            return;
        }

        tableBody.innerHTML = '';
        const colSpan = 9;
        const dateLabel = state.filters.dailyLoginsDate ? formatDisplayDate(state.filters.dailyLoginsDate) : null;

        if (!state.filters.dailyLoginsDate) {
            renderMessageRow(tableBody, colSpan, 'Pick a date to view manual attendance marks.');
            return;
        }

        if (manualAttendanceStore.loading) {
            renderMessageRow(tableBody, colSpan, 'Loading manual attendance records…');
            return;
        }

        if (manualAttendanceStore.error) {
            renderMessageRow(tableBody, colSpan, manualAttendanceStore.error, 'error');
            return;
        }

        const records = Array.isArray(manualAttendanceStore.items)
            ? manualAttendanceStore.items.filter((item) => item && item.entry)
            : [];

        if (!records.length) {
            const message = dateLabel
                ? `No manual attendance marks recorded for ${dateLabel}.`
                : 'No manual attendance marks recorded for the selected date.';
            renderMessageRow(tableBody, colSpan, message);
            return;
        }

        records.forEach((record, index) => {
            const entry = record.entry;
            const markLabel = entry.is_present ? 'Present' : 'Absent';
            const markClass = entry.is_present
                ? 'inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700'
                : 'inline-flex items-center rounded-full bg-rose-100 px-2 py-0.5 text-xs font-medium text-rose-700';
            const notes = entry.notes ? entry.notes : '—';
            const departmentName = record.department_name || '—';
            const workStationLabel = record.work_station || '—';

            const row = document.createElement('tr');
            row.className = 'border-t hover:bg-gray-50';
            row.innerHTML = [
                `<td class="p-4 text-center font-medium">${index + 1}</td>`,
                `<td class="p-4 font-medium">${record.id}</td>`,
                `<td class="p-4">${record.name}</td>`,
                `<td class="p-4">${departmentName}</td>`,
                `<td class="p-4">${workStationLabel}</td>`,
                `<td class="p-4 text-center"><span class="${markClass}">${markLabel}</span></td>`,
                `<td class="p-4">${entry.check_in_time ? formatDateTimeWithTime(entry.check_in_time) : '—'}</td>`,
                `<td class="p-4">${notes}</td>`,
            ].join('');

            tableBody.appendChild(row);
        });
    }

    function computeCumulativeHoursEntries() {
        const combined = new Map();

        if (employeeStore.items && employeeStore.items.length) {
            employeeStore.items.forEach((employee) => {
                const key = String(employee.id);
                const attendance = dataStore.attendanceByUser.get(key);
                const shiftHours = Number(attendance?.shiftHours ?? employee.shift_hours ?? employee.shiftHours ?? 0) || 0;
                const totalHours = Number(attendance?.cumulativeHours ?? attendance?.hoursWorked ?? 0) || 0;
                const loginDays = Number(attendance?.loginDays ?? 0) || 0;
                const attendancePercentage = Number(attendance?.attendancePercentage ?? 0) || 0;
                const workStationName = employee.workStation || employee.work_station || '—';

                combined.set(key, {
                    userId: employee.id,
                    userName: employee.name,
                    departmentName: employee.department_name || employee.departmentName || '—',
                    workStationName,
                    shiftHours,
                    totalHours,
                    loginDays,
                    attendancePercentage,
                });
            });
        }

        dataStore.attendance.forEach((record) => {
            const key = String(record.userId);
            const existing = combined.get(key) || {};

            const shiftHours = Number(record.shiftHours ?? existing.shiftHours ?? 0) || 0;
            const totalHours = Number(record.cumulativeHours ?? record.hoursWorked ?? existing.totalHours ?? 0) || 0;
            const loginDays = Number(record.loginDays ?? existing.loginDays ?? 0) || 0;
            const attendancePercentage = Number(record.attendancePercentage ?? existing.attendancePercentage ?? 0) || 0;
            const userName = record.userName || existing.userName || 'Unknown User';
            const departmentName = record.departmentName || existing.departmentName || '—';
            const workStationName = record.workStation || existing.workStationName || '—';

            combined.set(key, {
                userId: record.userId,
                userName,
                departmentName,
                workStationName,
                shiftHours,
                totalHours,
                loginDays,
                attendancePercentage,
            });
        });

        const entries = Array.from(combined.values());

        entries.sort((a, b) => {
            if (b.totalHours !== a.totalHours) {
                return b.totalHours - a.totalHours;
            }
            return b.loginDays - a.loginDays;
        });

        const totalHours = entries.reduce((sum, entry) => sum + entry.totalHours, 0);

        return {
            entries,
            totalHours,
        };
    }

    function renderCumulativeHoursSection() {
        const tableBody = document.getElementById('cumulative-hours-table-body');
        if (!tableBody) {
            return;
        }

        tableBody.innerHTML = '';
        const totalLabel = document.getElementById('cumulative-hours-total');
        const colSpan = 8;

        const updateTotalLabel = (value) => {
            if (totalLabel) {
                totalLabel.textContent = formatHours(value || 0);
            }
        };

        if (!state.filters.attendancePeriod) {
            updateTotalLabel(0);
            renderMessageRow(tableBody, colSpan, 'Pick a payroll period to view cumulative hours.');
            return;
        }

        if (state.loading.attendance) {
            updateTotalLabel(0);
            renderMessageRow(tableBody, colSpan, 'Loading cumulative hours…');
            return;
        }

        if (state.errors.attendance) {
            updateTotalLabel(0);
            renderMessageRow(tableBody, colSpan, state.errors.attendance, 'error');
            return;
        }

        const { entries, totalHours } = computeCumulativeHoursEntries();

        if (!entries.length) {
            updateTotalLabel(0);
            renderMessageRow(tableBody, colSpan, 'No MDC attendance records for this period.');
            return;
        }

        updateTotalLabel(totalHours);

        entries.forEach((entry, index) => {
            const shiftLabel = entry.shiftHours > 0 ? `${entry.shiftHours.toFixed(1)}h` : '—';
            const unitLabel = entry.workStationName || '—';
            const row = document.createElement('tr');
            row.className = `border-t hover:bg-gray-50${index === 0 ? ' bg-amber-50' : ''}`;
            row.innerHTML = [
                `<td class="p-4 text-center font-medium">${index + 1}</td>`,
                `<td class="p-4 font-medium">${entry.userId}</td>`,
                `<td class="p-4">${entry.userName}</td>`,
                `<td class="p-4">${entry.departmentName}</td>`,
                `<td class="p-4">${unitLabel}</td>`,
                `<td class="p-4 text-center">${shiftLabel}</td>`,
                `<td class="p-4 text-center font-semibold">${formatHours(entry.totalHours)}</td>`,
                `<td class="p-4 text-center">${entry.loginDays.toFixed(1)}</td>`,
                `<td class="p-4 text-center">${entry.attendancePercentage.toFixed(1)}%</td>`,
            ].join('');
            tableBody.appendChild(row);
        });
    }

    function openAttendanceBreakdown(userId) {
        const record = dataStore.attendanceByUser.get(userId);
        if (!record) {
            alert('Unable to locate attendance breakdown for the selected employee.');
            return;
        }

        const title = document.getElementById('attendance-breakdown-title');
        const subtitle = document.getElementById('attendance-breakdown-subtitle');
        const tableBody = document.getElementById('attendance-breakdown-table');
        const summary = document.getElementById('attendance-breakdown-summary');

        if (title) {
            const displayName = record.userName || 'Unknown User';
            title.textContent = `Daily Hours Breakdown · ${displayName} (${record.userId})`;
        }

        if (subtitle) {
            const periodLabel = state.filters.attendancePeriod ? formatPeriodLabel(state.filters.attendancePeriod) : '';
            const overtimeNote = record.overtimeAuthorized ? 'Overtime permitted' : 'Overtime restricted to shift hours';
            const autoLogoutNote = record.autoLogoutSessions > 0
                ? `${record.autoLogoutSessions} auto-logout event${record.autoLogoutSessions === 1 ? '' : 's'}`
                : 'No auto-logout events';
            subtitle.textContent = [periodLabel, overtimeNote, autoLogoutNote].filter(Boolean).join(' • ');
        }

        if (tableBody) {
            tableBody.innerHTML = '';

            if (!record.dailySummaries.length) {
                const emptyRow = document.createElement('tr');
                emptyRow.innerHTML = '<td colspan="5" class="px-4 py-4 text-center text-sm text-gray-500">No session data captured for this period.</td>';
                tableBody.appendChild(emptyRow);
            } else {
                record.dailySummaries.forEach((entry) => {
                    const row = document.createElement('tr');
                    row.innerHTML = [
                        `<td class="px-4 py-2 text-sm text-gray-700">${formatDisplayDate(entry.date)}</td>`,
                        `<td class="px-4 py-2 text-right text-sm font-medium text-gray-900">${entry.hours.toFixed(2)}h</td>`,
                        `<td class="px-4 py-2 text-right text-sm text-gray-600">${entry.cumulativeHours.toFixed(2)}h</td>`,
                        `<td class="px-4 py-2 text-right text-sm ${entry.overtimeHours > 0 ? 'text-blue-600 font-semibold' : 'text-gray-500'}">${entry.overtimeHours > 0 ? `+${entry.overtimeHours.toFixed(2)}h` : '0h'}</td>`,
                        `<td class="px-4 py-2 text-sm text-gray-600">${entry.truncated ? 'Auto-logout applied' : (entry.overtimeHours > 0 ? 'Authorized overtime' : 'Within shift')}</td>`,
                    ].join('');
                    tableBody.appendChild(row);
                });
            }
        }

        if (summary) {
            const lines = [];
            lines.push(`Total logged: <strong>${record.cumulativeHours.toFixed(2)}h</strong>`);
            if (record.expectedHours > 0) {
                const progressPercent = Math.min(100, Math.round((record.cumulativeHours / record.expectedHours) * 100));
                lines.push(`Progress: ${progressPercent}% of ${record.expectedHours.toFixed(2)}h target`);
            }
            lines.push(`Attendance days: ${record.loginDays.toFixed(1)}${record.expectedLoginDays ? ` / ${record.expectedLoginDays}` : ''}`);
            const overtimeSummary = record.overtimeAuthorized
                ? `Overtime enabled${record.overtimeCapHours !== null ? ` (cap ${record.overtimeCapHours.toFixed(1)}h)` : ''}`
                : 'Overtime disabled';
            lines.push(overtimeSummary);
            summary.innerHTML = lines.join(' • ');
        }

        toggleDialog('attendance-breakdown-dialog', true);
    }

    function resetAttendanceBreakdown() {
        const tableBody = document.getElementById('attendance-breakdown-table');
        const subtitle = document.getElementById('attendance-breakdown-subtitle');
        const summary = document.getElementById('attendance-breakdown-summary');

        if (tableBody) {
            tableBody.innerHTML = '';
        }
        if (subtitle) {
            subtitle.textContent = '';
        }
        if (summary) {
            summary.innerHTML = '';
        }
    }

    function renderSalarySection() {
        updateTriggerLabel('salary-month', state.filters.salaryPeriod);

        const tableBody = document.getElementById('salary-table-body');
        if (!tableBody) {
            return;
        }

        tableBody.innerHTML = '';
        const colSpan = 11;

        if (state.loading.salaries) {
            renderMessageRow(tableBody, colSpan, 'Loading salary data…');
            return;
        }

        if (state.errors.salaries) {
            renderMessageRow(tableBody, colSpan, state.errors.salaries, 'error');
            resetSalaryTotals();
            return;
        }

        const searchTerm = (document.querySelector('.salary-search-input')?.value || '').trim().toLowerCase();
        const visibleRecords = dataStore.salaries.filter((record) => {
            if (!searchTerm) {
                return true;
            }

            return (
                String(record.userId).toLowerCase().includes(searchTerm) ||
                (record.userName || '').toLowerCase().includes(searchTerm)
            );
        });

        if (!visibleRecords.length) {
            renderMessageRow(tableBody, colSpan, 'No salary data for this period.');
            resetSalaryTotals();
            return;
        }

        let totalBase = 0;
        let totalBonus = 0;
        let totalExtra = 0;
        let totalNet = 0;

        visibleRecords.forEach((record, index) => {
            const row = document.createElement('tr');
            row.className = 'border-t hover:bg-gray-50';

            const shiftBadge = record.shiftHours && record.shiftHours >= 8 ? 'badge-8hrs' : 'badge-4hrs';
            const shiftText = record.shiftHours ? `${record.shiftHours.toFixed(1)}h` : '-';
            const metrics = resolveSalaryMetrics(record);
            const rateSourceNote = metrics.note;
            const displayDailyRate = metrics.dailyRate;
            const displayBaseSalary = metrics.base;
            const serial = index + 1;

            row.innerHTML = [
                `<td class="p-4 text-center font-medium">${serial}</td>`,
                `<td class="p-4"><div class="font-medium">${record.userName || 'Unknown User'}</div><div class="text-sm text-gray-500">${record.userId}</div></td>`,
                `<td class="p-4 text-center"><span class="${shiftBadge}">${shiftText}</span></td>`,
                `<td class="p-4 text-right">${formatCurrency(displayDailyRate)}<br>${rateSourceNote}</td>`,
                `<td class="p-4 text-center">${record.loginDays.toFixed(1)}</td>`,
                `<td class="p-4 text-right font-medium">${formatCurrency(displayBaseSalary)}</td>`,
                `<td class="p-4 text-right text-green-600">+${formatCurrency(record.bonuses)}</td>`,
                `<td class="p-4 text-right text-blue-600">+${formatCurrency(record.extraHours)}</td>`,
                `<td class="p-4 text-right font-bold">${formatCurrency(record.netSalary)}</td>`,
                `<td class="p-4 text-center"><button class="add-bonus-btn rounded-md border border-gray-300 px-3 py-1 hover:bg-gray-50" data-employee-id="${record.userId}" data-employee-name="${record.userName || 'Unknown User'}"><i class="fas fa-plus mr-1"></i>Add</button></td>`,
            ].join('');

            tableBody.appendChild(row);

            totalBase += displayBaseSalary;
            totalBonus += record.bonuses;
            totalExtra += record.extraHours;
            totalNet += record.netSalary;
        });

        updateSalaryTotals(totalBase, totalBonus, totalExtra, totalNet);
    }

    function renderPayrollSection() {
        updateTriggerLabel('payroll-month', state.filters.payrollPeriod);
        updateUnitLabel();
        renderSelectedUnits();

        const tableBody = document.getElementById('payroll-table-body');
        if (!tableBody) {
            return;
        }

        tableBody.innerHTML = '';
        const colSpan = 13;

        if (state.loading.salaries) {
            renderMessageRow(tableBody, colSpan, 'Loading payroll data…');
            updatePayrollTotals(0, 0, 0, 0, 0);
            return;
        }

        if (state.errors.salaries) {
            renderMessageRow(tableBody, colSpan, state.errors.salaries, 'error');
            updatePayrollTotals(0, 0, 0, 0, 0);
            return;
        }

        const unitsFilter = state.selectedUnits;
        const visibleRecords = unitsFilter.length
            ? dataStore.salaries.filter((record) => {
                const unitName = (record.workStation || '—').toString();
                return unitsFilter.includes(unitName);
            })
            : dataStore.salaries;

        if (!visibleRecords.length) {
            renderMessageRow(tableBody, colSpan, 'No payroll records for the selected filters.');
            updatePayrollTotals(0, 0, 0, 0, 0);
            return;
        }

        let totalBase = 0;
        let totalBonus = 0;
        let totalExtra = 0;
        let totalNet = 0;

        visibleRecords.forEach((record, index) => {
            const row = document.createElement('tr');
            row.className = 'border-t hover:bg-gray-50';

            const shiftBadge = record.shiftHours && record.shiftHours >= 8 ? 'badge-8hrs' : 'badge-4hrs';
            const shiftText = record.shiftHours ? `${record.shiftHours.toFixed(1)}h` : '-';
            const metrics = resolveSalaryMetrics(record);
            const rateSourceNote = metrics.note;
            const displayDailyRate = metrics.dailyRate;
            const displayBaseSalary = metrics.base;
            const serial = index + 1;

            row.innerHTML = [
                `<td class="sticky left-0 z-10 border-r bg-white p-4 text-center font-medium w-16">${serial}</td>`,
                `<td class="sticky left-16 z-10 border-r bg-white p-4 text-center font-medium w-24">${record.userId}</td>`,
                `<td class="sticky left-40 z-10 border-r bg-white p-4">${record.userName || 'Unknown User'}</td>`,
                `<td class="p-4">${record.departmentName || '—'}</td>`,
                `<td class="p-4">${record.workStation || '—'}</td>`,
                `<td class="p-4 text-center"><span class="${shiftBadge}">${shiftText}</span></td>`,
                `<td class="p-4 text-right">${formatCurrency(displayDailyRate)}<br>${rateSourceNote}</td>`,
                `<td class="p-4 text-center font-semibold">${record.hoursWorked.toFixed(1)}h</td>`,
                `<td class="p-4 bg-blue-50 text-center font-bold">${record.loginDays.toFixed(1)}</td>`,
                `<td class="p-4 text-right">${formatCurrency(displayBaseSalary)}</td>`,
                `<td class="p-4 text-right text-green-600">${formatCurrency(record.bonuses)}</td>`,
                `<td class="p-4 text-right text-blue-600">${formatCurrency(record.extraHours)}</td>`,
                `<td class="p-4 text-right font-bold">${formatCurrency(record.netSalary)}</td>`,
            ].join('');

            tableBody.appendChild(row);

            totalBase += displayBaseSalary;
            totalBonus += record.bonuses;
            totalExtra += record.extraHours;
            totalNet += record.netSalary;
        });

        updatePayrollTotals(visibleRecords.length, totalBase, totalBonus, totalExtra, totalNet);
        updatePayrollSummaryCards(totalBase, totalBonus, totalExtra, totalNet);
    }

    function refreshRatesTab() {
        const searchValue = (document.querySelector('.rates-search-input')?.value || '').trim().toLowerCase();
        const tableBody = document.getElementById('rates-table-body');
        if (!tableBody) {
            return;
        }

        tableBody.innerHTML = '';
        const colSpan = 12;

        if (state.loading.rates) {
            renderMessageRow(tableBody, colSpan, 'Loading payroll rates…');
            return;
        }

        if (state.errors.rates) {
            renderMessageRow(tableBody, colSpan, state.errors.rates, 'error');
            return;
        }

        const filtered = rateStore.items.filter((record) => {
            if (!searchValue) {
                return true;
            }

            return (
                String(record.userId).toLowerCase().includes(searchValue) ||
                (record.userName || '').toLowerCase().includes(searchValue)
            );
        });

        if (!filtered.length) {
            renderMessageRow(tableBody, colSpan, 'No payroll rates found.');
            return;
        }

        filtered.forEach((record, index) => {
            const row = document.createElement('tr');
            row.className = 'border-t hover:bg-gray-50';

            const statusClass = record.isActive ? 'badge-active' : 'badge-inactive';
            const shiftBadge = record.shiftHours && record.shiftHours >= 8 ? 'badge-8hrs' : 'badge-4hrs';
            const shiftText = record.shiftHours ? `${record.shiftHours}h` : '-';
            const toggleLabel = record.isActive ? 'Deactivate' : 'Activate';
            const toggleStatus = record.isActive ? 'inactive' : 'active';
            const toggleClass = record.isActive
                ? 'toggle-rate-btn inline-flex items-center gap-1 rounded-md border border-red-200 px-3 py-1 text-sm text-red-600 hover:bg-red-50'
                : 'toggle-rate-btn inline-flex items-center gap-1 rounded-md border border-green-200 px-3 py-1 text-sm text-green-600 hover:bg-green-50';
            const rateSourceNote = {
                rate: '<span class="text-xs text-gray-400">(configured rate)</span>',
                workstation: '<span class="text-xs text-gray-400">(work station base ÷ month days)</span>',
                base: '<span class="text-xs text-gray-400">(work station base ÷ month days)</span>',
                derived: '<span class="text-xs text-gray-400">(derived)</span>',
                none: '<span class="text-xs text-gray-400">(not set)</span>',
            }[record.dailyRateSource || 'none'];
            const baseSalaryDisplay = record.baseSalary > 0 ? formatCurrency(record.baseSalary) : '—';
            const overtimeContent = record.allowOvertime
                ? `<span class="badge-good">Enabled</span>${record.overtimeCapHours !== null ? `<div class="text-xs text-gray-500">Cap: ${record.overtimeCapHours.toFixed(1)}h</div>` : ''}`
                : '<span class="badge-poor">Disabled</span>';
            const serial = index + 1;

            row.innerHTML = [
                `<td class="p-4 text-center font-medium">${serial}</td>`,
                `<td class="p-4 font-medium">${record.userId}</td>`,
                `<td class="p-4">${record.userName || 'Unknown User'}</td>`,
                `<td class="p-4">${record.departmentName || '—'}</td>`,
                `<td class="p-4">${record.workStation || '—'}</td>`,
                `<td class="p-4">${record.position || '—'}</td>`,
                `<td class="p-4 text-right font-semibold">${formatCurrency(record.dailyRate)}<br>${rateSourceNote}</td>`,
                `<td class="p-4 text-right">${baseSalaryDisplay}</td>`,
                `<td class="p-4 text-center"><span class="${shiftBadge}">${shiftText}</span></td>`,
                `<td class="p-4 text-center space-y-1">${overtimeContent}</td>`,
                `<td class="p-4 text-center"><span class="${statusClass}">${record.statusLabel}</span></td>`,
                `<td class="p-4 text-center"><div class="flex items-center justify-center gap-2">
                    <button class="edit-rate-btn rounded-md border border-gray-300 px-3 py-1 text-sm hover:bg-gray-50" data-rate-id="${record.id}"><i class="fas fa-edit mr-1"></i>Edit</button>
                    <button class="${toggleClass}" data-rate-id="${record.id}" data-next-status="${toggleStatus}">${toggleLabel}</button>
                </div></td>`,
            ].join('');

            tableBody.appendChild(row);
        });
    }

    function renderUnitDropdown() {
        const dropdown = document.getElementById('payroll-unit-dropdown');
        if (!dropdown) {
            return;
        }

        const uniqueUnits = Array.from(new Set(
            dataStore.salaries
                .map((record) => (record.workStation || '—').toString())
                .filter((value) => value && value.trim().length)
        )).sort((a, b) => a.localeCompare(b));

        const options = [
            '<div class="px-3 py-2 cursor-pointer hover:bg-gray-100 select-option" data-value="all">All Units</div>',
            uniqueUnits.length
                ? uniqueUnits
                    .map((unit) => `<div class="px-3 py-2 cursor-pointer hover:bg-gray-100 select-option" data-value="${unit}">${unit}</div>`)
                    .join('')
                : '<div class="px-3 py-2 text-sm text-gray-500">No units available</div>',
        ].join('');

        dropdown.innerHTML = `<div class="py-1">${options}</div>`;
    }

    function renderMessageRow(tableBody, colSpan, message, tone = 'info') {
        const row = document.createElement('tr');
        const cell = document.createElement('td');
        cell.colSpan = colSpan;
        cell.className = 'p-4 text-center text-sm';

        if (tone === 'error') {
            cell.classList.add('text-red-600');
        } else {
            cell.classList.add('text-gray-500');
        }

        cell.textContent = message;
        row.appendChild(cell);
        tableBody.appendChild(row);
    }

    function updateSalaryTotals(base, bonus, extra, net) {
        const totalBaseCell = document.getElementById('total-base-salary');
        const totalBonusCell = document.getElementById('total-bonuses');
        const totalExtraCell = document.getElementById('total-extra-hours');
        const totalSalaryCell = document.getElementById('total-salary');

        if (totalBaseCell) {
            totalBaseCell.textContent = formatCurrency(base);
        }
        if (totalBonusCell) {
            totalBonusCell.textContent = `+${formatCurrency(bonus)}`;
        }
        if (totalExtraCell) {
            totalExtraCell.textContent = `+${formatCurrency(extra)}`;
        }
        if (totalSalaryCell) {
            totalSalaryCell.textContent = formatCurrency(net);
        }
    }

    function resetSalaryTotals() {
        updateSalaryTotals(0, 0, 0, 0);
    }

    function updatePayrollTotals(employeeCount, base, bonus, extra, net) {
        const employeeCountCell = document.getElementById('employee-count');
        const totalBaseCell = document.getElementById('payroll-total-base');
        const totalBonusCell = document.getElementById('payroll-total-bonuses');
        const totalExtraCell = document.getElementById('payroll-total-extra');
        const totalNetCell = document.getElementById('payroll-grand-total');

        if (employeeCountCell) {
            employeeCountCell.textContent = employeeCount.toString();
        }
        if (totalBaseCell) {
            totalBaseCell.textContent = formatCurrency(base);
        }
        if (totalBonusCell) {
            totalBonusCell.textContent = formatCurrency(bonus);
        }
        if (totalExtraCell) {
            totalExtraCell.textContent = formatCurrency(extra);
        }
        if (totalNetCell) {
            totalNetCell.textContent = formatCurrency(net);
        }
    }

    function updatePayrollSummaryCards(base, bonuses, extra, total) {
        const container = document.getElementById('payroll-summary-cards');
        if (!container) {
            return;
        }

        container.innerHTML = [
            summaryCardTemplate('Base Salaries', formatCurrency(base), 'Total base pay'),
            summaryCardTemplate('Bonuses', formatCurrency(bonuses), 'Performance bonuses', 'text-green-600'),
            summaryCardTemplate('Extra Hours', formatCurrency(extra), 'Overtime pay', 'text-blue-600'),
            summaryCardTemplate('Grand Total', formatCurrency(total), 'Total payroll', 'text-purple-600'),
        ].join('');
    }

    function summaryCardTemplate(label, value, note, valueClass = '') {
        return [
            '<div class="bg-white rounded-lg border card-shadow p-6">',
            `<div class="pb-3"><h3 class="text-sm font-medium">${label}</h3></div>`,
            `<div><div class="text-2xl font-bold ${valueClass}">${value}</div>`,
            `<p class="mt-1 text-xs text-gray-500">${note}</p></div>`,
            '</div>',
        ].join('');
    }

    function resolveDashboardSummary() {
        const uniqueEmployeeIds = new Set();

        dataStore.salaries.forEach((record) => {
            if (record.userId !== undefined && record.userId !== null) {
                uniqueEmployeeIds.add(String(record.userId));
            }
        });

        if (!uniqueEmployeeIds.size && employeeStore.items.length) {
            employeeStore.items.forEach((employee) => {
                if (employee.id !== undefined && employee.id !== null) {
                    uniqueEmployeeIds.add(String(employee.id));
                }
            });
        }

        if (!uniqueEmployeeIds.size && dataStore.attendance.length) {
            dataStore.attendance.forEach((record) => {
                if (record.userId !== undefined && record.userId !== null) {
                    uniqueEmployeeIds.add(String(record.userId));
                }
            });
        }

        const attendanceValues = dataStore.attendance
            .map((record) => Number(record.attendancePercentage))
            .filter((value) => Number.isFinite(value));

        const averageAttendance = attendanceValues.length
            ? attendanceValues.reduce((sum, value) => sum + value, 0) / attendanceValues.length
            : 0;

        const presentCount = dataStore.attendance.filter((record) => Number(record.loginDays || record.login_days || 0) > 0).length;

        const totalValue = attendanceOnlyMode
            ? dataStore.attendance.reduce((sum, record) => {
                const hours = Number(record.cumulativeHours ?? record.hoursWorked ?? 0);
                return sum + (Number.isFinite(hours) ? hours : 0);
            }, 0)
            : dataStore.salaries.reduce((sum, record) => sum + Number(record.netSalary || 0), 0);

        return {
            totalEmployees: uniqueEmployeeIds.size,
            presentCount,
            averageAttendance,
            totalValue,
        };
    }

    function getDashboardMetricsForActiveTab() {
        if (attendanceOnlyMode) {
            const activeKey = state.activeTab || 'daily-logins';

            if (activeKey === 'cumulative-hours') {
                return {
                    context: 'cumulative-hours',
                    cards: buildCumulativeHoursMetrics(),
                };
            }

            if (activeKey === 'attendance') {
                return {
                    context: 'attendance',
                    cards: buildAttendanceTrackingMetrics(),
                };
            }

            return {
                context: 'daily-logins',
                cards: buildDailyLoginsMetrics(),
            };
        }

        return {
            context: 'payroll',
            cards: buildPayrollOverviewMetrics(),
        };
    }

    function buildDailyLoginsMetrics() {
        const periodSelected = Boolean(state.filters.attendancePeriod);
        const dateSelected = Boolean(state.filters.dailyLoginsDate);
        const dateLabel = state.filters.dailyLoginsDate ? formatDisplayDate(state.filters.dailyLoginsDate) : 'No date selected';

        if (!periodSelected) {
            return createPlaceholderCards('Sessions Recorded', 'Pick a payroll period to view daily logins.');
        }

        if (!dateSelected) {
            return createPlaceholderCards('Sessions Recorded', 'Pick a date to view daily logins.');
        }

        if (dailyLoginsStore.loading) {
            return createPlaceholderCards('Sessions Recorded', 'Loading daily login sessions…');
        }

        if (dailyLoginsStore.error) {
            return createPlaceholderCards('Sessions Recorded', dailyLoginsStore.error);
        }

        const entries = getSortedDailyLoginEntries();
        let digitalPresent = 0;
        let manualPresent = 0;
        let lateCount = 0;
        let totalHours = 0;

        const sessionsRecorded = entries.length;
        if (!sessionsRecorded) {
            return createPlaceholderCards('Sessions Recorded', `No attendance records captured for ${dateLabel}.`);
        }

        entries.forEach((entry) => {
            const statusInfo = computeDailyLoginStatus(entry);
            if (statusInfo.presenceKey === 'present') {
                if (entry.__isManual) {
                    manualPresent += 1;
                } else {
                    digitalPresent += 1;
                }
            }
            if (statusInfo.latenessKey === 'late') {
                lateCount += 1;
            }
            totalHours += resolveDailyLoginTotalHours(entry);
        });
        const averageHours = sessionsRecorded ? totalHours / sessionsRecorded : 0;
        const combinedActive = digitalPresent + manualPresent;

        return [
            {
                title: 'Sessions Recorded',
                value: sessionsRecorded.toString(),
                note: `For ${dateLabel}`,
                exportValue: sessionsRecorded,
            },
            {
                title: 'Active Staff',
                value: combinedActive.toString(),
                note: `Digital ${digitalPresent} • Manual ${manualPresent}`,
                exportValue: combinedActive,
            },
            {
                title: 'Late Logins',
                value: lateCount.toString(),
                note: 'Flagged against shift start',
                exportValue: lateCount,
            },
            {
                title: 'Avg Session Length',
                value: formatHours(averageHours),
                note: 'Average hours per employee',
                exportValue: Number.isFinite(averageHours) ? averageHours.toFixed(2) : '0.00',
            },
        ];
    }

    function buildCumulativeHoursMetrics() {
        if (!state.filters.attendancePeriod) {
            return createPlaceholderCards('Tracked Employees', 'Pick a payroll period to view cumulative hours.');
        }

        if (state.loading.attendance) {
            return createPlaceholderCards('Tracked Employees', 'Loading cumulative hours…');
        }

        if (state.errors.attendance) {
            return createPlaceholderCards('Tracked Employees', state.errors.attendance);
        }

        const { entries, totalHours } = computeCumulativeHoursEntries();
        if (!entries.length) {
            return createPlaceholderCards('Tracked Employees', 'No MDC attendance records for this period.');
        }

        const employeeCount = entries.length;
        const averageHours = employeeCount ? totalHours / employeeCount : 0;
        const averageAttendance = employeeCount
            ? entries.reduce((sum, entry) => sum + (Number.isFinite(entry.attendancePercentage) ? entry.attendancePercentage : 0), 0) / employeeCount
            : 0;

        return [
            {
                title: 'Tracked Employees',
                value: employeeCount.toString(),
                note: 'With attendance records',
                exportValue: employeeCount,
            },
            {
                title: 'Total Hours Logged',
                value: formatHours(totalHours),
                note: formatPeriodLabel(state.filters.attendancePeriod),
                exportValue: Number.isFinite(totalHours) ? totalHours.toFixed(2) : '0.00',
            },
            {
                title: 'Avg Hours per Employee',
                value: formatHours(averageHours),
                note: 'Across tracked staff',
                exportValue: Number.isFinite(averageHours) ? averageHours.toFixed(2) : '0.00',
            },
            {
                title: 'Avg Attendance %',
                value: `${Number.isFinite(averageAttendance) ? averageAttendance.toFixed(1) : '0.0'}%`,
                note: 'Mean attendance rate',
                exportValue: Number.isFinite(averageAttendance) ? averageAttendance.toFixed(1) : '0.0',
            },
        ];
    }

    function buildAttendanceTrackingMetrics() {
        if (!state.filters.attendancePeriod) {
            return createPlaceholderCards('Eligible Staff', 'Pick a payroll period to view attendance tracking.');
        }

        const summary = resolveDashboardSummary();
        const totalValue = Number.isFinite(summary.totalValue) ? summary.totalValue : 0;
        const averageAttendance = Number.isFinite(summary.averageAttendance) ? summary.averageAttendance : 0;

        return [
            {
                title: 'Eligible Staff',
                value: summary.totalEmployees.toString(),
                note: 'Active staff members',
                exportValue: summary.totalEmployees,
            },
            {
                title: 'Present Staff',
                value: summary.presentCount.toString(),
                note: 'Logged attendance this period',
                exportValue: summary.presentCount,
            },
            {
                title: 'Avg Attendance',
                value: `${averageAttendance.toFixed(1)}%`,
                note: 'Average for current period',
                exportValue: averageAttendance.toFixed(1),
            },
            {
                title: 'Total Hours Logged',
                value: formatHours(totalValue),
                note: 'Cumulative hours this period',
                exportValue: totalValue.toFixed(2),
            },
        ];
    }

    function buildPayrollOverviewMetrics() {
        const summary = resolveDashboardSummary();
        const totalValue = Number.isFinite(summary.totalValue) ? summary.totalValue : 0;
        const averageAttendance = Number.isFinite(summary.averageAttendance) ? summary.averageAttendance : 0;

        return [
            {
                title: 'Total Employees',
                value: summary.totalEmployees.toString(),
                note: 'Active staff members',
                exportValue: summary.totalEmployees,
            },
            {
                title: 'Present Today',
                value: summary.presentCount.toString(),
                note: 'Logged in today',
                exportValue: summary.presentCount,
            },
            {
                title: 'Avg Attendance',
                value: `${averageAttendance.toFixed(1)}%`,
                note: 'Average for current period',
                exportValue: averageAttendance.toFixed(1),
            },
            {
                title: 'Total Payroll',
                value: formatCurrency(totalValue),
                note: 'Net salaries this period',
                exportValue: totalValue.toFixed(2),
            },
        ];
    }

    function createPlaceholderCards(primaryTitle, helperMessage) {
        const primaryNote = helperMessage || 'Update filters to view metrics.';
        const waitingNote = 'Awaiting data';
        return [
            {
                title: primaryTitle,
                value: '—',
                note: primaryNote,
                exportValue: primaryNote,
            },
            {
                title: 'Status',
                value: '—',
                note: waitingNote,
                exportValue: waitingNote,
            },
            {
                title: 'Details',
                value: '—',
                note: waitingNote,
                exportValue: waitingNote,
            },
            {
                title: 'Summary',
                value: '—',
                note: waitingNote,
                exportValue: waitingNote,
            },
        ];
    }

    function updateDashboardMetrics() {
        const metrics = getDashboardMetricsForActiveTab();
        if (!metrics || !Array.isArray(metrics.cards)) {
            return;
        }

        const valueIds = ['total-employees', 'present-today', 'avg-attendance', 'total-payroll'];
        const titleIds = ['metric-card-1-title', 'metric-card-2-title', 'metric-card-3-title', 'metric-card-4-title'];
        const noteIds = ['metric-card-1-note', 'metric-card-2-note', 'metric-card-3-note', 'metric-card-4-note'];

        metrics.cards.forEach((card, index) => {
            const valueEl = document.getElementById(valueIds[index]);
            if (valueEl) {
                valueEl.textContent = card?.value ?? '—';
            }

            const titleEl = document.getElementById(titleIds[index]);
            if (titleEl && card?.title) {
                titleEl.textContent = card.title;
            }

            const noteEl = document.getElementById(noteIds[index]);
            if (noteEl) {
                noteEl.textContent = card?.note ?? '';
            }
        });
    }

    async function handleBonusSubmit() {
        if (!Number.isFinite(state.currentSelectedEmployee) || !state.filters.payrollPeriod) {
            return;
        }

        const baseField = document.getElementById('base-salary-amount');
        const bonusField = document.getElementById('bonus-amount');
        const extraField = document.getElementById('extra-hours-amount');
        const noteField = document.getElementById('reason');

        const baseAmount = parseFloat(baseField?.value ?? '');
        const bonusAmount = parseFloat(bonusField?.value ?? '');
        const extraAmount = parseFloat(extraField?.value ?? '');
        const note = noteField?.value?.trim();

        const salaryRecord = dataStore.salaries.find((record) => record.userId === state.currentSelectedEmployee);
        if (!salaryRecord) {
            alert('Unable to locate salary details for the selected employee.');
            return;
        }

        const safeBase = Number.isFinite(baseAmount) ? baseAmount : salaryRecord.baseSalary;
        const safeBonus = Number.isFinite(bonusAmount) ? bonusAmount : salaryRecord.bonuses;
        const safeExtra = Number.isFinite(extraAmount) ? extraAmount : salaryRecord.extraHours;

        const payload = {
            period_code: state.filters.payrollPeriod,
            user_id: Number(state.currentSelectedEmployee),
            base_salary: roundCurrency(safeBase),
            bonuses: roundCurrency(safeBonus),
            extra_hours_value: roundCurrency(safeExtra),
        };

        if (note) {
            payload.note = note;
        }

        if (salaryRecord.loginDays > 0) {
            const calculatedDailyRate = roundCurrency(safeBase / salaryRecord.loginDays);
            salaryRecord.derivedDailyRate = calculatedDailyRate;
            if (salaryRecord.dailyRateSource !== 'rate') {
                salaryRecord.dailyRate = calculatedDailyRate;
                salaryRecord.dailyRateSource = 'derived';
            }
        }

        try {
            await submitAdjustment(payload);
            await ensureSalaryData(true);
            renderSalarySection();
            renderPayrollSection();
            updateDashboardMetrics();
            toggleDialog('bonus-dialog', false);
            resetBonusDialog();
        } catch (error) {
            console.error(error);
            alert(error.message || 'Failed to save the adjustment.');
        }
    }

    async function handleRateSave() {
        const newRateField = document.getElementById('new-rate');
        const shiftHoursField = document.getElementById('shift-hours');
        const effectiveDateField = document.getElementById('effective-date');
        const reasonField = document.getElementById('rate-reason');
        const userSelect = document.getElementById('rate-user');
        const allowOvertimeField = document.getElementById('allow-overtime');
        const overtimeCapField = document.getElementById('overtime-cap');
        const overtimeNotesField = document.getElementById('overtime-notes');

        const newRateValue = parseFloat(newRateField?.value || '0');
        const newShiftHours = parseInt(shiftHoursField?.value || '0', 10);
        const effectiveDateValue = effectiveDateField?.value?.trim();
        const rateReason = reasonField?.value?.trim();
        const allowOvertime = Boolean(allowOvertimeField?.checked);
        const overtimeCapRaw = parseFloat(overtimeCapField?.value || '');
        const hasOvertimeCap = Number.isFinite(overtimeCapRaw) && overtimeCapRaw >= 0;
        const overtimeNotes = overtimeNotesField?.value?.trim() ?? '';

        if (!Number.isFinite(newRateValue) || newRateValue <= 0 || !Number.isFinite(newShiftHours) || newShiftHours <= 0) {
            alert('Please enter valid rate and shift hour values.');
            return;
        }

        try {
            if (state.rateDialogMode === 'create') {
                const selectedUserId = Number.parseInt(userSelect?.value || '', 10);
                if (!Number.isFinite(selectedUserId)) {
                    alert('Select an employee to assign the rate.');
                    return;
                }

                const createPayload = {
                    user_id: selectedUserId,
                    daily_rate: roundCurrency(newRateValue),
                    shift_hours: newShiftHours,
                    effective_date: effectiveDateValue || new Date().toISOString().slice(0, 10),
                    deactivate_existing: true,
                    allow_overtime: allowOvertime,
                };

                if (state.filters.payrollPeriod) {
                    createPayload.period_code = state.filters.payrollPeriod;
                }

                if (rateReason) {
                    createPayload.note = rateReason;
                }

                if (hasOvertimeCap) {
                    createPayload.overtime_hours_cap = roundCurrency(overtimeCapRaw);
                }

                createPayload.overtime_notes = overtimeNotes || null;

                const response = await submitRateCreate(createPayload);
                const createdRate = response?.data?.rate;
                if (createdRate) {
                    rateStore.raw = [createdRate, ...rateStore.raw.filter((entry) => entry.id !== createdRate.id)];
                    rateStore.items = rateStore.raw.map(mapRateRecord);
                } else {
                    await loadRates(true);
                }
            } else {
                if (!state.currentSelectedRateId) {
                    alert('No rate selected to update.');
                    return;
                }

                const updatePayload = {
                    daily_rate: roundCurrency(newRateValue),
                    shift_hours: newShiftHours,
                    allow_overtime: allowOvertime,
                    overtime_hours_cap: hasOvertimeCap ? roundCurrency(overtimeCapRaw) : null,
                    overtime_notes: overtimeNotes,
                };

                if (state.filters.payrollPeriod) {
                    updatePayload.period_code = state.filters.payrollPeriod;
                }

                if (rateReason) {
                    updatePayload.note = rateReason;
                }

                if (effectiveDateValue) {
                    updatePayload.effective_date = effectiveDateValue;
                }

                const response = await submitRateUpdate(state.currentSelectedRateId, updatePayload);

                const updatedRate = response?.data?.rate;
                if (updatedRate) {
                    rateStore.raw = rateStore.raw.map((entry) => (entry.id === updatedRate.id ? updatedRate : entry));
                    if (!rateStore.raw.find((entry) => entry.id === updatedRate.id)) {
                        rateStore.raw.push(updatedRate);
                    }
                    rateStore.items = rateStore.raw.map(mapRateRecord);
                } else {
                    await loadRates(true);
                }
            }

            await ensureSalaryData(true);
            renderSalarySection();
            renderPayrollSection();
            refreshRatesTab();
            updateDashboardMetrics();
            toggleDialog('edit-rate-dialog', false);
            resetRateDialog();
        } catch (error) {
            console.error(error);
            alert(error.message || 'Failed to update the payroll rate.');
        }
    }

    function openRateDialog(mode, rate = null) {
        state.rateDialogMode = mode;
        state.currentSelectedRateId = mode === 'edit' ? rate?.id ?? null : null;
        state.currentSelectedEmployee = mode === 'edit' ? rate?.userId ?? null : null;

        const title = document.getElementById('edit-rate-dialog-title');
        if (title) {
            title.textContent = mode === 'create' ? 'Add Daily Rate & Shift' : 'Edit Daily Rate & Shift';
        }

        const description = document.getElementById('rate-dialog-description');
        if (description) {
            if (mode === 'edit' && rate) {
                description.textContent = `Update daily rate and shift for ${rate.userName} (${rate.userId})`;
            } else {
                description.textContent = 'Assign a daily rate and shift hours to an employee.';
            }
        }

        populateRateEmployeeSelect(rate?.userId ?? null, mode === 'edit');
        prefillRateDialog(rate, mode);

        toggleDialog('edit-rate-dialog', true);
    }

    function populateRateEmployeeSelect(selectedUserId = null, disableSelect = false) {
        const select = document.getElementById('rate-user');
        if (!select) {
            return;
        }

        const existingValue = Number.parseInt(select.value || '', 10);
        const targetValue = Number.isFinite(existingValue) ? existingValue : selectedUserId;

        const employees = buildKnownEmployees();
        select.innerHTML = ['<option value="">Select employee</option>',
            ...employees.map((employee) => {
                const isSelected = targetValue === employee.id;
                const label = employee.department
                    ? `${employee.name} (${employee.department})`
                    : employee.name;
                return `<option value="${employee.id}"${isSelected ? ' selected' : ''}>${label}</option>`;
            }),
        ].join('');

        select.disabled = disableSelect;
        if (!disableSelect) {
            select.classList.remove('bg-gray-100');
        } else {
            select.classList.add('bg-gray-100');
        }

        if (!disableSelect) {
            const inferred = Number.parseInt(selectedUserId ?? '', 10);
            state.currentSelectedEmployee = Number.isFinite(inferred) ? inferred : null;
        }
    }

    function prefillRateDialog(rate, mode) {
        const currentRate = document.getElementById('current-rate');
        const newRate = document.getElementById('new-rate');
        const shiftHours = document.getElementById('shift-hours');
        const effectiveDate = document.getElementById('effective-date');
        const reasonField = document.getElementById('rate-reason');
        const allowOvertimeField = document.getElementById('allow-overtime');
        const overtimeCapField = document.getElementById('overtime-cap');
        const overtimeNotesField = document.getElementById('overtime-notes');

        if (mode === 'edit' && rate) {
            if (currentRate) {
                currentRate.value = formatCurrency(rate.dailyRate);
            }
            if (newRate) {
                newRate.value = rate.backendDailyRate || rate.configuredDailyRate || rate.dailyRate || '';
            }
            if (shiftHours) {
                shiftHours.value = rate.shiftHours || '';
            }
            if (effectiveDate) {
                effectiveDate.value = rate.effectiveDate || '';
            }
            if (reasonField) {
                reasonField.value = '';
            }
            if (allowOvertimeField) {
                allowOvertimeField.checked = Boolean(rate.allowOvertime);
            }
            if (overtimeCapField) {
                overtimeCapField.value = rate.overtimeCapHours !== null && rate.overtimeCapHours !== undefined
                    ? String(rate.overtimeCapHours)
                    : '';
            }
            if (overtimeNotesField) {
                overtimeNotesField.value = rate.overtimeNotes || '';
            }
        } else {
            if (currentRate) {
                currentRate.value = '';
            }
            if (newRate) {
                newRate.value = '';
            }
            if (shiftHours) {
                shiftHours.value = '';
            }
            if (effectiveDate) {
                effectiveDate.value = new Date().toISOString().slice(0, 10);
            }
            if (reasonField) {
                reasonField.value = '';
            }
            if (allowOvertimeField) {
                allowOvertimeField.checked = false;
            }
            if (overtimeCapField) {
                overtimeCapField.value = '';
            }
            if (overtimeNotesField) {
                overtimeNotesField.value = '';
            }

            prefillRateDialogFromExisting(state.currentSelectedEmployee);
        }
    }

    function prefillRateDialogFromExisting(userId) {
        const currentRate = document.getElementById('current-rate');
        const newRate = document.getElementById('new-rate');
        const shiftHours = document.getElementById('shift-hours');
        const allowOvertimeField = document.getElementById('allow-overtime');
        const overtimeCapField = document.getElementById('overtime-cap');
        const overtimeNotesField = document.getElementById('overtime-notes');

        if (!Number.isFinite(userId)) {
            if (currentRate) {
                currentRate.value = '';
            }
            if (newRate) {
                newRate.value = '';
            }
            if (shiftHours) {
                shiftHours.value = '8';
            }
            if (allowOvertimeField) {
                allowOvertimeField.checked = false;
            }
            if (overtimeCapField) {
                overtimeCapField.value = '';
            }
            if (overtimeNotesField) {
                overtimeNotesField.value = '';
            }
            return;
        }

        const existing = getExistingRateForUser(userId);
        const salary = dataStore.salaries.find((entry) => entry.userId === userId) || null;

        const periodDays = Number(existing?.periodDays ?? salary?.periodDays ?? 0);
        const baseSalary = Number(existing?.baseSalary ?? salary?.baseSalary ?? 0);
        const workStationBase = Number(existing?.workStationBaseSalary ?? salary?.workStationBaseSalary ?? 0);
        const referenceBase = baseSalary > 0 ? baseSalary : workStationBase;
        const derivedDailyRate = periodDays > 0 && referenceBase > 0
            ? roundCurrency(referenceBase / periodDays)
            : 0;

        const configuredDailyRate = Number(existing?.backendDailyRate ?? existing?.configuredDailyRate ?? salary?.backendDailyRate ?? 0);
        const fallbackDailyRate = Number(existing?.dailyRate ?? salary?.dailyRate ?? salary?.derivedDailyRate ?? salary?.backendDailyRate ?? 0);
        const resolvedDailyRate = derivedDailyRate > 0
            ? derivedDailyRate
            : (fallbackDailyRate > 0 ? roundCurrency(fallbackDailyRate) : 0);

        if (currentRate) {
            currentRate.value = resolvedDailyRate > 0 ? formatCurrency(resolvedDailyRate) : '';
        }
        if (newRate) {
            newRate.value = configuredDailyRate > 0
                ? roundCurrency(configuredDailyRate)
                : (resolvedDailyRate > 0 ? resolvedDailyRate : '');
        }

        const inferredShiftHours = Number(existing?.shiftHours ?? salary?.shiftHours ?? salary?.man_hours_per_day ?? 8);
        if (shiftHours) {
            shiftHours.value = Number.isFinite(inferredShiftHours) && inferredShiftHours > 0
                ? inferredShiftHours
                : '8';
        }
        if (allowOvertimeField) {
            allowOvertimeField.checked = Boolean(existing?.allowOvertime ?? false);
        }
        if (overtimeCapField) {
            const capValue = existing?.overtimeCapHours;
            overtimeCapField.value = capValue !== null && capValue !== undefined ? String(capValue) : '';
        }
        if (overtimeNotesField) {
            overtimeNotesField.value = existing?.overtimeNotes || '';
        }
    }

    function getExistingRateForUser(userId) {
        if (!Number.isFinite(userId)) {
            return null;
        }

        const active = rateStore.items.find((entry) => entry.userId === userId && entry.isActive);
        if (active) {
            return active;
        }

        return rateStore.items.find((entry) => entry.userId === userId) || null;
    }

    function buildKnownEmployees() {
        const map = new Map();

        const buildLabel = (department, unit) => {
            const parts = [];
            const normalizedDepartment = (department || '').toString().trim();
            const normalizedUnit = (unit || '').toString().trim();

            if (normalizedDepartment) {
                parts.push(normalizedDepartment);
            }
            if (normalizedUnit) {
                parts.push(normalizedUnit);
            }

            return parts.length ? parts.join(' • ') : '—';
        };

        const register = (userId, name, department, unit) => {
            const numericId = Number.parseInt(userId, 10);
            if (!Number.isFinite(numericId) || !numericId) {
                return;
            }

            const normalizedName = String(name || 'Unknown User').trim();
            if (!map.has(numericId)) {
                map.set(numericId, {
                    id: numericId,
                    name: normalizedName,
                    department: buildLabel(department, unit),
                });
            }
        };

        // Primary source: MDC employees with work_station loaded from API
        employeeStore.items.forEach((emp) => {
            register(emp.id, emp.name, emp.departmentName, emp.workStation);
        });

        // Fallback sources for employees that may not match MDC criteria but already have payroll data
        rateStore.items.forEach((record) => {
            register(record.userId, record.userName, record.departmentName, record.workStation);
        });

        dataStore.salaries.forEach((record) => {
            register(record.userId, record.userName, record.departmentName, record.workStation);
        });

        dataStore.attendance.forEach((record) => {
            register(record.userId, record.userName, record.departmentName, record.workStation);
        });

        return Array.from(map.values()).sort((a, b) => a.name.localeCompare(b.name));
    }

    async function toggleRateStatus(rateId, makeActive) {
        const payload = {
            is_active: makeActive,
            deactivate_existing: makeActive,
        };

        if (state.filters.payrollPeriod) {
            payload.period_code = state.filters.payrollPeriod;
        }

        if (!makeActive) {
            payload.expires_at = new Date().toISOString().slice(0, 10);
        }

        const response = await submitRateUpdate(rateId, payload);
        const updatedRate = response?.data?.rate;

        if (updatedRate) {
            rateStore.raw = rateStore.raw.map((entry) => (entry.id === updatedRate.id ? updatedRate : entry));
            if (!rateStore.raw.find((entry) => entry.id === updatedRate.id)) {
                rateStore.raw.push(updatedRate);
            }
            rateStore.items = rateStore.raw.map(mapRateRecord);
        } else {
            await loadRates(true);
        }

        refreshRatesTab();
    }

    function updateUnitLabel() {
        const unitLabel = document.querySelector('.select-trigger[data-type="payroll-unit"] span');
        if (unitLabel) {
            unitLabel.textContent = state.selectedUnits.length ? `${state.selectedUnits.length} units selected` : 'All Units';
        }
    }

    function renderSelectedUnits() {
        const container = document.getElementById('selected-units-container');
        if (!container) {
            return;
        }

        if (!state.selectedUnits.length) {
            container.innerHTML = '';
            return;
        }

        container.innerHTML = state.selectedUnits
            .map((unit) => `<span class="flex items-center gap-1 rounded-md bg-gray-100 px-2 py-1 text-sm text-gray-800">${unit}<i class="fas fa-times cursor-pointer text-xs remove-unit-btn" data-unit="${unit}"></i></span>`)
            .join('');
    }

    function updatePayrollDescription() {
        const description = document.getElementById('payroll-details-description');
        if (description) {
            description.textContent = state.filters.payrollPeriod
                ? `Individual salary breakdown for ${formatPeriodLabel(state.filters.payrollPeriod)} (based on shift hours)`
                : 'Select a payroll period to view details.';
        }
    }

    function toggleDialog(dialogId, show) {
        const dialog = document.getElementById(dialogId);
        if (!dialog) {
            return;
        }

        dialog.classList.toggle('hidden', !show);
    }

    function resetBonusDialog() {
        const baseAmount = document.getElementById('base-salary-amount');
        const bonusAmount = document.getElementById('bonus-amount');
        const extraHours = document.getElementById('extra-hours-amount');
        const reason = document.getElementById('reason');

        if (baseAmount) {
            baseAmount.value = '';
        }
        if (bonusAmount) {
            bonusAmount.value = '';
        }
        if (extraHours) {
            extraHours.value = '';
        }
        if (reason) {
            reason.value = '';
        }

        state.currentSelectedEmployee = null;
        state.currentSelectedRateId = null;
    }

    function prefillBonusDialog(userId) {
        const baseField = document.getElementById('base-salary-amount');
        const bonusField = document.getElementById('bonus-amount');
        const extraField = document.getElementById('extra-hours-amount');
        const noteField = document.getElementById('reason');

        if (!Number.isFinite(userId)) {
            if (baseField) {
                baseField.value = '';
            }
            if (bonusField) {
                bonusField.value = '';
            }
            if (extraField) {
                extraField.value = '';
            }
            if (noteField) {
                noteField.value = '';
            }
            return;
        }

        const salaryRecord = dataStore.salaries.find((record) => record.userId === userId);

        if (baseField) {
            baseField.value = String(roundCurrency(salaryRecord.baseSalary));
        }
        if (bonusField) {
            bonusField.value = String(roundCurrency(salaryRecord.bonuses));
        }
        if (extraField) {
            extraField.value = String(roundCurrency(salaryRecord.extraHours));
        }
        if (noteField) {
            noteField.value = salaryRecord.note || '';
        }
    }

    function resetRateDialog() {
        const newRate = document.getElementById('new-rate');
        const effectiveDate = document.getElementById('effective-date');
        const reason = document.getElementById('rate-reason');
        const currentRate = document.getElementById('current-rate');
        const shiftHours = document.getElementById('shift-hours');
        const userSelect = document.getElementById('rate-user');
        const allowOvertime = document.getElementById('allow-overtime');
        const overtimeCap = document.getElementById('overtime-cap');
        const overtimeNotes = document.getElementById('overtime-notes');

        if (newRate) {
            newRate.value = '';
        }
        if (effectiveDate) {
            effectiveDate.value = '';
        }
        if (reason) {
            reason.value = '';
        }
        if (currentRate) {
            currentRate.value = '';
        }
        if (shiftHours) {
            shiftHours.value = '';
        }
        if (userSelect) {
            userSelect.value = '';
            userSelect.disabled = false;
            userSelect.classList.remove('bg-gray-100');
        }
        if (allowOvertime) {
            allowOvertime.checked = false;
        }
        if (overtimeCap) {
            overtimeCap.value = '';
        }
        if (overtimeNotes) {
            overtimeNotes.value = '';
        }

        state.currentSelectedEmployee = null;
        state.currentSelectedRateId = null;
        state.rateDialogMode = 'view';
    }

    async function handleSyncAttendanceUsers() {
        const period = state.filters.attendancePeriod;
        if (!period) {
            alert('Select a period before syncing attendance users.');
            return;
        }

        const label = formatPeriodLabel(period);
        const confirmSync = confirm(`Sync eligible MDC staff into attendance for ${label}?`);
        if (!confirmSync) {
            return;
        }

        const payload = {};
        if (state.filters.departmentId !== null && state.filters.departmentId !== undefined && state.filters.departmentId !== '') {
            payload.department_id = state.filters.departmentId;
        }

        try {
            const response = await submitAttendanceUserSync(period, payload);
            await ensureAttendanceData(true);
            renderAttendanceViews();
            updateDashboardMetrics();

            const stats = response?.data?.stats;
            if (stats) {
                alert(`Attendance users synchronized. Added ${stats.created} new record(s), updated ${stats.updated}.`);
            } else {
                alert('Attendance users synchronized.');
            }
        } catch (error) {
            console.error(error);
            alert(error.message || 'Failed to synchronize attendance users.');
        }
    }

    async function handleRegenerateAttendance() {
        const period = state.filters.attendancePeriod;
        if (!period) {
            alert('Select a period before regenerating attendance.');
            return;
        }

        const label = formatPeriodLabel(period);
        const confirmation = confirm(`Regenerate attendance records for ${label}? This will recalculate login days and hours worked.`);
        if (!confirmation) {
            return;
        }

        try {
            await submitAttendanceRegeneration(period, {
                wipe_existing: true,
                rebuild_snapshots: true,
                snapshot_days: 7,
            });
            await ensureAttendanceData(true);
            await ensureSalaryData(true);
            renderPayrollSection();
            updateDashboardMetrics();
            alert(`Attendance for ${label} has been regenerated.`);
        } catch (error) {
            console.error(error);
            alert(error.message || 'Failed to regenerate attendance records.');
        }
    }

    function handleExportAttendance() {
        const rows = [
            ['Employee ID', 'Name', 'Department', 'Unit', 'Shift Hours', 'Hours Worked', 'Expected Hours', 'Login Days', 'Expected Days', 'Overtime Hours', 'Attendance %', 'Auto Logout Events', 'Overtime Authorized'],
            ...dataStore.attendance.map((record) => [
                record.userId,
                record.userName,
                record.departmentName,
                record.workStation || '',
                record.shiftHours?.toFixed(1) || '-',
                record.hoursWorked.toFixed(1),
                record.expectedHours !== undefined && record.expectedHours !== null ? record.expectedHours.toFixed(1) : '-',
                record.loginDays.toFixed(1),
                record.expectedLoginDays !== undefined && record.expectedLoginDays !== null ? record.expectedLoginDays : '-',
                record.overtimeHours.toFixed(1),
                record.attendancePercentage,
                record.autoLogoutSessions || 0,
                record.overtimeAuthorized ? 'Yes' : 'No',
            ]),
        ];

        downloadCsv(rows, `attendance_${state.filters.attendancePeriod || 'period'}.csv`);
    }

    function handleExportDailyLogins() {
        if (!state.filters.attendancePeriod) {
            alert('Select a payroll period before exporting daily login sessions.');
            return;
        }

        if (!state.filters.dailyLoginsDate) {
            alert('Pick a date before exporting daily login sessions.');
            return;
        }

        if (dailyLoginsStore.loading) {
            alert('Daily login sessions are still loading. Please wait.');
            return;
        }

        if (dailyLoginsStore.error) {
            alert(dailyLoginsStore.error);
            return;
        }

        const entries = getSortedDailyLoginEntries();
        if (!entries.length) {
            alert(`No login sessions found for ${state.filters.dailyLoginsDate}.`);
            return;
        }

        const rows = [
            ['#', 'Employee ID', 'Name', 'Department', 'Unit', 'Presence', 'Lateness', 'Shift Start', 'First Login', 'Last Logout', 'Total Hours', 'Lateness (h)', 'Snapshot Captured', 'Attendance Flag'],
            ...entries.map((entry, index) => {
                const statusInfo = computeDailyLoginStatus(entry);
                const totalHours = resolveDailyLoginTotalHours(entry);
                const logoutDisplay = entry.is_online
                    ? 'Awaiting logout'
                    : (entry.last_logout ? formatDateTimeWithTime(entry.last_logout) : '—');

                return [
                    index + 1,
                    entry.user_id,
                    entry.user_name,
                    entry.department_name || '—',
                    entry.work_station || '—',
                    statusInfo.presenceLabel,
                    statusInfo.latenessLabel,
                    statusInfo.shiftStartLabel || '',
                    formatDateTimeWithTime(entry.first_login),
                    logoutDisplay,
                    totalHours.toFixed(2),
                    Number.isFinite(statusInfo.latenessHours) ? statusInfo.latenessHours.toFixed(2) : '',
                    statusInfo.hasSnapshot ? 'Yes' : 'No',
                    (entry.attendance_status || '').toString(),
                ];
            }),
        ];

        const fileLabel = state.filters.dailyLoginsDate ? state.filters.dailyLoginsDate.replace(/-/g, '') : 'date';
        downloadCsv(rows, `daily_logins_${fileLabel}.csv`);
    }

    function handleExportCumulativeHours() {
        if (!state.filters.attendancePeriod) {
            alert('Select a payroll period before exporting cumulative hours.');
            return;
        }

        if (state.loading.attendance) {
            alert('Attendance data is still loading. Please wait.');
            return;
        }

        if (state.errors.attendance) {
            alert(state.errors.attendance);
            return;
        }

        const { entries } = computeCumulativeHoursEntries();
        if (!entries.length) {
            alert('No attendance records available to export.');
            return;
        }

        const rows = [
            ['Rank', 'Employee ID', 'Name', 'Unit', 'Shift Hours', 'Total Hours', 'Login Days', 'Attendance %'],
            ...entries.map((entry, index) => [
                index + 1,
                entry.userId,
                entry.userName,
                entry.departmentName,
                Number.isFinite(entry.shiftHours) && entry.shiftHours > 0 ? entry.shiftHours.toFixed(1) : '',
                Number.isFinite(entry.totalHours) ? entry.totalHours.toFixed(2) : '0.00',
                Number.isFinite(entry.loginDays) ? entry.loginDays.toFixed(1) : '0.0',
                Number.isFinite(entry.attendancePercentage) ? entry.attendancePercentage.toFixed(1) : '0.0',
            ]),
        ];

        downloadCsv(rows, `cumulative_hours_${state.filters.attendancePeriod || 'period'}.csv`);
    }

    function handleExportDashboard() {
        const metrics = getDashboardMetricsForActiveTab();
        const periodCode = state.filters.attendancePeriod || state.filters.payrollPeriod || null;
        const periodLabel = periodCode ? formatPeriodLabel(periodCode) : 'Not selected';
        const rows = [['Metric', 'Value']];

        if (metrics?.context === 'daily-logins') {
            rows.push(['Date', state.filters.dailyLoginsDate ? formatDisplayDate(state.filters.dailyLoginsDate) : 'Not selected']);
        }

        rows.push(['Period', periodLabel]);

        if (metrics && Array.isArray(metrics.cards)) {
            metrics.cards.forEach((card) => {
                rows.push([
                    card?.title || 'Metric',
                    card?.exportValue ?? card?.value ?? '—',
                ]);
            });
        }

        const fileKey = metrics?.context || (attendanceOnlyMode ? 'attendance' : 'payroll');
        downloadCsv(rows, `dashboard_summary_${fileKey}.csv`);
    }

    function handleExportPayroll() {
        const unitsFilter = state.selectedUnits;
        const records = unitsFilter.length
            ? dataStore.salaries.filter((record) => {
                const unitName = (record.workStation || '—').toString();
                return unitsFilter.includes(unitName);
            })
            : dataStore.salaries;

        const rows = [
            ['Employee ID', 'Name', 'Department', 'Unit', 'Shift Hours', 'Daily Rate', 'Hours Worked', 'Login Days', 'Base Salary', 'Bonuses', 'Extra Hours', 'Net Salary'],
            ...records.map((record) => [
                record.userId,
                record.userName,
                record.departmentName,
                record.workStation || '—',
                record.shiftHours?.toFixed(1) || '-',
                record.dailyRate,
                record.hoursWorked.toFixed(1),
                record.loginDays.toFixed(1),
                record.baseSalary,
                record.bonuses,
                record.extraHours,
                record.netSalary,
            ]),
        ];

        downloadCsv(rows, `payroll_${state.filters.payrollPeriod || 'period'}.csv`);
    }

    function handlePrintPayroll() {
        const payrollSection = document.getElementById('payroll-tab');
        if (!payrollSection) {
            return;
        }

        const printWindow = window.open('', '', 'width=1200,height=800');
        if (!printWindow) {
            return;
        }

        const stylesheet = document.querySelector('link[href*="css/payroll.css"]');

        printWindow.document.write('<!DOCTYPE html><html><head><title>Payroll Report</title>');
        printWindow.document.write('<script src="https://cdn.tailwindcss.com"><\/script>');
        if (stylesheet) {
            printWindow.document.write(`<link rel="stylesheet" href="${stylesheet.href}">`);
        }
        printWindow.document.write('</head><body class="bg-gray-100">');
        printWindow.document.write(payrollSection.innerHTML);
        printWindow.document.write('</body></html>');
        printWindow.document.close();
        printWindow.focus();
        printWindow.print();
        printWindow.close();
    }

    function downloadCsv(rows, filename) {
        const csvContent = rows
            .map((row) => row.map((value) => `"${String(value ?? '').replace(/"/g, '""')}"`).join(','))
            .join('\n');

        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);
        link.href = url;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
    }

    function closeAllDropdowns(except) {
        document.querySelectorAll('.select-dropdown').forEach((dropdown) => {
            if (dropdown !== except) {
                dropdown.classList.add('hidden');
            }
        });
    }

    function normalizeAttendanceRecord(record) {
        const userIdentifier = record.user_id ?? record.userId ?? '';
        const userId = userIdentifier !== null && userIdentifier !== undefined
            ? String(userIdentifier)
            : '';

        const loginDaysRaw = Number(record.login_days || record.loginDays || 0) || 0;
        const hoursWorkedRaw = Number(record.hours_worked || record.hoursWorked || 0) || 0;
        const overtimeHours = Number(record.overtime_hours || record.overtimeHours || 0) || 0;
        const shiftHours = Number(record.shift_hours || 8) || 0;
        const expectedLoginDays = Number(record.expected_login_days || record.expectedLoginDays || 0) || 0;
        const expectedHours = Number(record.expected_hours || record.expectedHours || (expectedLoginDays * shiftHours)) || 0;
        const cumulativeHoursRaw = Number(record.cumulative_hours || record.cumulativeHours || hoursWorkedRaw) || 0;
        const attendancePercentageRaw = record.attendance_percentage ?? record.attendancePercentage;
        const baselineAttendancePercentage = attendancePercentageRaw !== undefined && attendancePercentageRaw !== null
            ? Number(attendancePercentageRaw)
            : null;

        const dailySummaryRaw = Array.isArray(record.daily_summary || record.dailySummary)
            ? (record.daily_summary || record.dailySummary)
            : [];

        let dailySummaries = dailySummaryRaw.map((entry) => ({
            date: entry.date,
            hours: Number(entry.hours ?? 0) || 0,
            cumulativeHours: Number(entry.cumulative_hours ?? entry.cumulativeHours ?? entry.hours ?? 0) || 0,
            overtimeHours: Number(entry.overtime_hours ?? entry.overtimeHours ?? 0) || 0,
            rawHours: Number(entry.raw_hours ?? entry.rawHours ?? entry.hours ?? 0) || 0,
            truncated: Boolean(entry.truncated),
        }));

        const periodWindow = resolvePeriodWindow(state.filters.attendancePeriod);
        dailySummaries = filterSummariesByPeriod(dailySummaries, periodWindow);

        let normalizedSummaries = rebuildCumulativeSummaries(dailySummaries);

        if (!normalizedSummaries.length) {
            const derivedFromBreakdown = deriveSummariesFromBreakdown(record.session_breakdown || [], periodWindow);
            normalizedSummaries = rebuildCumulativeSummaries(derivedFromBreakdown);
        }

        const summariesHoursTotal = normalizedSummaries.reduce((sum, entry) => sum + entry.hours, 0);
        const hasSummaries = normalizedSummaries.length > 0;
        const hoursWorkedValue = hasSummaries ? Number(summariesHoursTotal.toFixed(2)) : hoursWorkedRaw;
        const loginDaysValue = hasSummaries ? normalizedSummaries.length : loginDaysRaw;
        const cumulativeHoursValue = hasSummaries
            ? Number(normalizedSummaries[normalizedSummaries.length - 1].cumulativeHours)
            : (Number.isFinite(cumulativeHoursRaw) ? Number(cumulativeHoursRaw) : hoursWorkedValue);

        const computedAttendancePercentage = expectedLoginDays > 0
            ? Math.max(0, Math.min(100, (loginDaysValue / expectedLoginDays) * 100))
            : null;
        const attendancePercentageValue = computedAttendancePercentage !== null
            ? computedAttendancePercentage
            : (Number.isFinite(baselineAttendancePercentage) ? baselineAttendancePercentage : 0);

        const autoLogoutSessions = Number(record.auto_logout_sessions || record.autoLogoutSessions || 0) || 0;
        const overtimeAuthorized = Boolean(record.overtime_authorized ?? record.overtimeAuthorized ?? false);
        const overtimeCapHours = record.overtime_cap_hours !== undefined && record.overtime_cap_hours !== null
            ? Number(record.overtime_cap_hours)
            : (record.overtimeCapHours !== undefined && record.overtimeCapHours !== null
                ? Number(record.overtimeCapHours)
                : null);

        const hoursWorkedResolved = Number.isFinite(hoursWorkedValue) ? Number(hoursWorkedValue.toFixed(2)) : 0;
        const cumulativeHoursResolved = Number.isFinite(cumulativeHoursValue)
            ? Number(cumulativeHoursValue.toFixed(2))
            : hoursWorkedResolved;
        const attendancePercentageResolved = Number.isFinite(attendancePercentageValue)
            ? Number(attendancePercentageValue.toFixed(1))
            : 0;
        const loginDaysResolved = Number.isFinite(loginDaysValue) ? Number(loginDaysValue) : 0;

        return {
            userId,
            userName: record.user_name || record.userName,
            departmentId: record.department_id || record.departmentId,
            departmentName: record.department_name || record.departmentName,
            workStation: record.work_station || record.workStation || null,
            loginDays: loginDaysResolved,
            hoursWorked: hoursWorkedResolved,
            overtimeHours,
            shiftHours,
            attendancePercentage: attendancePercentageResolved,
            sourceReference: record.source_reference,
            sessionBreakdown: record.session_breakdown || [],
            expectedLoginDays,
            expectedHours,
            cumulativeHours: cumulativeHoursResolved,
            dailySummaries: normalizedSummaries,
            autoLogoutSessions,
            overtimeAuthorized,
            overtimeCapHours,
        };
    }

    function normalizeSalaryRecord(record) {
        const attendanceKey = record.user_id !== undefined && record.user_id !== null
            ? String(record.user_id)
            : (record.userId !== undefined && record.userId !== null ? String(record.userId) : null);
        const attendance = attendanceKey ? dataStore.attendanceByUser.get(attendanceKey) : undefined;
        const loginDays = attendance ? attendance.loginDays : Number(record.login_days || 0);
        const hoursWorked = attendance ? attendance.hoursWorked : Number(record.hours_worked || 0);
        const overtimeHours = attendance ? attendance.overtimeHours : Number(record.overtime_hours || 0);
        const recordShiftHours = record.shift_hours !== undefined ? Number(record.shift_hours) : null;
        const periodDaysRaw = Number(record.period_days || record.periodDays || 0);
        const periodDays = Number.isFinite(periodDaysRaw) && periodDaysRaw > 0 ? periodDaysRaw : null;
        const workStationBase = Number(record.work_station_base_salary || record.workStationBaseSalary || 0);
        const shiftHours = Number.isFinite(recordShiftHours) && recordShiftHours > 0
            ? recordShiftHours
            : attendance?.shiftHours ?? (loginDays > 0 ? hoursWorked / Math.max(loginDays, 1) : null);
        const baseSalaryRaw = Number(record.base_salary || 0);
        const bonusesRaw = Number(record.bonuses || 0);
        const extraHoursRaw = Number(record.extra_hours_value || record.extra_hours || 0);
        const deductionsRaw = Number(record.deductions || 0);
        const backendDailyRate = Number(record.daily_rate || record.dailyRate || 0);
        const derivedDailyRate = periodDays
            ? (baseSalaryRaw > 0 ? baseSalaryRaw / periodDays : (workStationBase > 0 ? workStationBase / periodDays : 0))
            : (loginDays > 0 ? baseSalaryRaw / loginDays : 0);
        const dailyRateSource = (record.daily_rate_source || '').toString().toLowerCase()
            || (workStationBase > 0 ? 'workstation' : (backendDailyRate > 0 ? 'rate' : 'derived'));
        const dailyRate = dailyRateSource === 'rate' && backendDailyRate > 0 ? backendDailyRate : derivedDailyRate;
        const attendanceBasePay = roundCurrency(Math.max(loginDays, 0) * Math.max(dailyRate, 0));
        const computedNetSalary = roundCurrency(attendanceBasePay + bonusesRaw + extraHoursRaw);
        const fallbackNetSalary = Number(record.net_salary || baseSalaryRaw + bonusesRaw + extraHoursRaw - deductionsRaw);
        const netSalary = Number.isFinite(computedNetSalary) ? computedNetSalary : roundCurrency(fallbackNetSalary);
        const attendanceDeduction = roundCurrency(Math.max((periodDays || 0) - loginDays, 0) * Math.max(dailyRate, 0));
        const rateEffectiveDate = record.rate_effective_date || record.rateEffectiveDate || null;
        const normalizedShiftHours = Number.isFinite(shiftHours) ? Number(shiftHours) : null;
        const baseSalary = roundCurrency(baseSalaryRaw);
        const bonuses = roundCurrency(bonusesRaw);
        const extraHours = roundCurrency(extraHoursRaw);
        const deductions = roundCurrency(deductionsRaw);

        return {
            userId: Number(record.user_id),
            userName: record.user_name,
            departmentId: record.department_id,
            departmentName: record.department_name,
            workStation: record.work_station || record.workStation || attendance?.workStation || null,
            loginDays,
            hoursWorked,
            overtimeHours,
            shiftHours: normalizedShiftHours,
            dailyRate: roundCurrency(dailyRate),
            backendDailyRate: roundCurrency(backendDailyRate),
            derivedDailyRate: roundCurrency(derivedDailyRate),
            dailyRateSource,
            rateEffectiveDate,
            periodDays,
            workStationBaseSalary: roundCurrency(workStationBase),
            baseSalary,
            bonuses,
            extraHours,
            deductions,
            attendanceBasePay,
            attendanceDeduction,
            netSalary,
            originalNetSalary: roundCurrency(fallbackNetSalary),
            status: record.calculation_status || record.status,
            note: record.note || '',
        };
    }

    function getCsrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        if (meta) {
            return meta.getAttribute('content');
        }
        if (window.Laravel && window.Laravel.csrfToken) {
            return window.Laravel.csrfToken;
        }
        return null;
    }

    async function submitAdjustment(payload) {
        const csrfToken = getCsrfToken();
        const headers = csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {};

        return fetchJson(endpoints.adjustments, {
            method: 'POST',
            headers,
            body: JSON.stringify(payload),
        });
    }

    async function submitAttendanceUserSync(period, payload = {}) {
        const csrfToken = getCsrfToken();
        const headers = csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {};

        return fetchJson(`${endpoints.periods}/${encodeURIComponent(period)}/attendance/sync-users`, {
            method: 'POST',
            headers,
            body: JSON.stringify({
                ...payload,
            }),
        });
    }

    async function submitAttendanceRegeneration(period, payload = {}) {
        const csrfToken = getCsrfToken();
        const headers = csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {};

        return fetchJson(`${endpoints.periods}/${encodeURIComponent(period)}/attendance/regenerate`, {
            method: 'POST',
            headers,
            body: JSON.stringify({
                wipe_existing: true,
                ...payload,
            }),
        });
    }

    async function submitRateCreate(payload) {
        const csrfToken = getCsrfToken();
        const headers = csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {};

        return fetchJson(endpoints.rates, {
            method: 'POST',
            headers,
            body: JSON.stringify(payload),
        });
    }

    async function submitRateUpdate(rateId, payload) {
        const csrfToken = getCsrfToken();
        const headers = csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {};

        return fetchJson(`${endpoints.rates}/${rateId}`, {
            method: 'PATCH',
            headers,
            body: JSON.stringify(payload),
        });
    }

    function mapRateRecord(rate) {
        const salary = dataStore.salaries.find((entry) => entry.userId === rate.user_id) || {};

        const configuredDailyRate = Number(rate.configured_daily_rate ?? 0);
        const apiDailyRate = Number(rate.daily_rate ?? 0);
        const backendDailyRate = configuredDailyRate > 0 ? configuredDailyRate : apiDailyRate;
        const salaryDailyRate = Number(salary.backendDailyRate ?? salary.dailyRate ?? 0);
        const rawConfiguredBase = Number(rate.base_salary ?? salary.baseSalary ?? 0);
        const workStationBase = Number(rate.work_station_base_salary ?? salary.workStationBaseSalary ?? 0);
        let baseSalary = workStationBase > 0 ? workStationBase : rawConfiguredBase;
        if (!Number.isFinite(baseSalary) || baseSalary < 0) {
            baseSalary = 0;
        }
        const baseSalarySource = baseSalary > 0
            ? (workStationBase > 0 ? 'workstation' : 'configured')
            : 'none';
        const periodDaysRaw = Number(rate.period_days ?? rate.periodDays ?? salary.periodDays ?? 0);
        const periodDays = Number.isFinite(periodDaysRaw) && periodDaysRaw > 0 ? periodDaysRaw : null;
        const derivedFromBase = periodDays && baseSalary > 0 ? roundCurrency(baseSalary / periodDays) : 0;
        const derivedFromWorkStation = periodDays && workStationBase > 0 ? roundCurrency(workStationBase / periodDays) : 0;
        const fallbackDerived = Number(salary.derivedDailyRate ?? 0);
        const preferredDerived = derivedFromBase || derivedFromWorkStation || fallbackDerived;
        let displayDailyRate = preferredDerived > 0
            ? roundCurrency(preferredDerived)
            : roundCurrency(apiDailyRate || salaryDailyRate || backendDailyRate || 0);
        if (!Number.isFinite(displayDailyRate) || displayDailyRate < 0) {
            displayDailyRate = 0;
        }

        const rawSource = (rate.daily_rate_source || rate.dailyRateSource || salary.dailyRateSource || '').toString().toLowerCase();
        let dailyRateSource = rawSource;
        if (preferredDerived > 0 || derivedFromBase > 0 || derivedFromWorkStation > 0) {
            dailyRateSource = 'workstation';
        } else if (!dailyRateSource && backendDailyRate > 0) {
            dailyRateSource = 'rate';
        } else if (!dailyRateSource && displayDailyRate > 0) {
            dailyRateSource = 'derived';
        } else if (!dailyRateSource) {
            dailyRateSource = 'none';
        }

        const shiftHoursRaw = Number(rate.shift_hours ?? salary.shiftHours ?? 0);
        const shiftHours = Number.isFinite(shiftHoursRaw) && shiftHoursRaw > 0 ? shiftHoursRaw : 0;

        return {
            id: rate.id,
            userId: Number.parseInt(rate.user_id, 10) || rate.user_id,
            userName: rate.user_name || salary.userName || 'Unknown User',
            departmentId: rate.department_id ?? salary.departmentId ?? null,
            departmentName: rate.department_name || salary.departmentName || '—',
            workStation: rate.work_station || salary.workStation || null,
            position: rate.position || '—',
            dailyRate: displayDailyRate,
            configuredDailyRate: roundCurrency(configuredDailyRate),
            backendDailyRate: roundCurrency(backendDailyRate),
            derivedDailyRate: roundCurrency(preferredDerived),
            salaryDerivedDailyRate: roundCurrency(salaryDailyRate),
            dailyRateSource,
            baseSalary: roundCurrency(baseSalary),
            configuredBaseSalary: roundCurrency(rawConfiguredBase),
            workStationBaseSalary: roundCurrency(workStationBase),
            baseSalarySource,
            periodDays,
            shiftHours,
            isActive: Boolean(rate.is_active),
            statusLabel: rate.status_label || (Boolean(rate.is_active) ? 'Active' : 'Inactive'),
            effectiveDate: rate.effective_date || null,
            expiresAt: rate.expires_at || null,
            allowOvertime: Boolean(rate.allow_overtime ?? rate.allowOvertime ?? false),
            overtimeCapHours: rate.overtime_hours_cap !== undefined && rate.overtime_hours_cap !== null
                ? Number(rate.overtime_hours_cap)
                : (rate.overtimeCapHours !== undefined ? Number(rate.overtimeCapHours) : null),
            overtimeNotes: rate.overtime_notes || rate.overtimeNotes || '',
        };
    }

    function getAttendanceBadge(percentage) {
        if (percentage >= 90) {
            return { label: 'Excellent', className: 'badge-excellent' };
        }
        if (percentage >= 70) {
            return { label: 'Good', className: 'badge-good' };
        }
        return { label: 'Needs Attention', className: 'badge-poor' };
    }

    async function fetchJson(url, options = {}) {
        const defaultHeaders = {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        };

        const config = {
            ...options,
            headers: {
                ...defaultHeaders,
                ...(options.headers || {}),
            },
        };

        if (config.body && !(config.body instanceof FormData)) {
            config.headers['Content-Type'] = config.headers['Content-Type'] || 'application/json';
        }

        if (!config.credentials) {
            config.credentials = 'same-origin';
        }

        const response = await fetch(url, config);
        const contentType = (response.headers.get('Content-Type') || '').toLowerCase();
        const expectsJson = contentType.includes('application/json');

        if (response.redirected && response.url.includes('/login')) {
            throw new Error('Your session has expired. Please refresh and sign in again.');
        }

        if (!response.ok) {
            if (expectsJson) {
                const payload = await response.json().catch(() => null);
                const message = parseErrorMessage(payload);
                throw new Error(message || `Request failed with status ${response.status}`);
            }

            if (response.status === 401 || response.status === 419) {
                throw new Error('Your session has expired. Please refresh and sign in again.');
            }

            if (response.status === 403) {
                throw new Error('You do not have permission to perform this action.');
            }

            if (contentType.includes('text/html')) {
                throw new Error(`Request failed with status ${response.status}`);
            }

            const fallback = await response.text();
            throw new Error(parseErrorMessage(fallback) || fallback || `Request failed with status ${response.status}`);
        }

        if (!expectsJson) {
            const text = await response.text();
            const message = parseErrorMessage(text);
            if (message) {
                throw new Error(message);
            }
            if (contentType.includes('text/html')) {
                throw new Error('Your session has expired. Please refresh and sign in again.');
            }
            throw new Error('Server responded with an unexpected format.');
        }

        return response.json();
    }

    function parseErrorMessage(payload) {
        if (!payload) {
            return null;
        }

        if (typeof payload === 'string') {
            try {
                const parsed = JSON.parse(payload);
                return parseErrorMessage(parsed);
            } catch (error) {
                return null;
            }
        }

        if (typeof payload !== 'object') {
            return null;
        }

        if (typeof payload.message === 'string' && payload.message.trim() !== '') {
            return payload.message.trim();
        }

        if (typeof payload.error === 'string' && payload.error.trim() !== '') {
            return payload.error.trim();
        }

        if (payload.errors && typeof payload.errors === 'object') {
            const firstKey = Object.keys(payload.errors)[0];
            if (firstKey) {
                const entry = payload.errors[firstKey];
                if (Array.isArray(entry) && entry.length) {
                    return String(entry[0]);
                }
                if (typeof entry === 'string' && entry.trim() !== '') {
                    return entry.trim();
                }
            }
        }

        return null;
    }

    function formatCurrency(amount) {
        return new Intl.NumberFormat('en-NG', {
            style: 'currency',
            currency: 'NGN',
            minimumFractionDigits: 2,
        }).format(Number(amount) || 0);
    }

    function roundCurrency(value) {
        return Math.round((Number(value) || 0) * 100) / 100;
    }

    function formatDisplayDate(value) {
        if (!value) {
            return '—';
        }

        const parsed = new Date(value);
        if (Number.isNaN(parsed.getTime())) {
            return value;
        }

        return parsed.toLocaleDateString('en-NG', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        });
    }

    function formatDateTimeWithTime(value) {
        if (!value) {
            return '—';
        }

        const parsed = new Date(value);
        if (Number.isNaN(parsed.getTime())) {
            return formatDisplayDate(value);
        }

        return parsed.toLocaleString('en-NG', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    }

    function formatTime(value) {
        if (!value) {
            return null;
        }

        const parsed = new Date(value);
        if (Number.isNaN(parsed.getTime())) {
            return value;
        }

        return parsed.toLocaleTimeString('en-NG', {
            hour: '2-digit',
            minute: '2-digit',
        });
    }

    function formatHours(value) {
        return `${Number(value || 0).toFixed(2)}h`;
    }

    function resolveSortTimestamp(value) {
        if (!value) {
            return 0;
        }

        const parsed = new Date(value);
        return Number.isNaN(parsed.getTime()) ? 0 : parsed.getTime();
    }

    function computeManualLatenessData(checkInTime, shiftCode) {
        if (!checkInTime) {
            return {
                lateLogin: null,
                latenessMinutes: null,
                latenessReference: null,
                shiftStart: null,
            };
        }

        const shifts = {
            'morning': { start: '09:00', overnight: false },
            'afternoon': { start: '13:00', overnight: false },
            'night': { start: '17:00', overnight: false },
            'overnight': { start: '21:00', overnight: true },
            'full_day': { start: '09:00', overnight: false },
        };

        const normalizedCode = String(shiftCode || 'full_day').toLowerCase();
        const shift = shifts[normalizedCode] || shifts['full_day'];

        const checkIn = new Date(checkInTime);
        if (Number.isNaN(checkIn.getTime())) {
            return {
                lateLogin: null,
                latenessMinutes: null,
                latenessReference: null,
                shiftStart: null,
            };
        }

        const checkInDate = checkIn.toISOString().split('T')[0];
        const [shiftHour, shiftMinute] = shift.start.split(':').map(Number);

        let shiftStart = new Date(`${checkInDate}T${shift.start}:00`);
        if (shift.overnight && checkIn.getHours() < 12) {
            const previousDay = new Date(checkIn);
            previousDay.setDate(previousDay.getDate() - 1);
            shiftStart = new Date(`${previousDay.toISOString().split('T')[0]}T${shift.start}:00`);
        }

        const lateToleranceMs = 0;
        const lateThreshold = new Date(shiftStart.getTime() + lateToleranceMs);
        const diffMs = checkIn.getTime() - lateThreshold.getTime();

        if (diffMs > 0) {
            return {
                lateLogin: true,
                latenessMinutes: Math.floor(diffMs / (1000 * 60)),
                latenessReference: lateThreshold.toISOString(),
                shiftStart: shiftStart.toISOString(),
            };
        } else {
            return {
                lateLogin: false,
                latenessMinutes: Math.abs(Math.floor(diffMs / (1000 * 60))),
                latenessReference: lateThreshold.toISOString(),
                shiftStart: shiftStart.toISOString(),
            };
        }
    }

    function resolveTotalDailyHours(record) {
        const breakdown = Array.isArray(record.sessionBreakdown) ? record.sessionBreakdown : [];
        let totalHours = 0;

        breakdown.forEach((session) => {
            const minutesRaw = session.minutes ?? session.duration_minutes ?? null;
            const minutes = Number(minutesRaw);
            let hours = Number.isFinite(minutes) && minutes > 0 ? minutes / 60 : NaN;

            if (!Number.isFinite(hours) || hours <= 0) {
                const fallback = Number(session.hours ?? session.raw_hours ?? session.total_hours ?? 0);
                hours = Number.isFinite(fallback) ? fallback : 0;
            }

            totalHours += hours;
        });

        return totalHours;
    }

    function calculateHoursFromTimes(loginAt, logoutAt) {
        if (!loginAt || !logoutAt) {
            return 0;
        }

        const loginTime = new Date(loginAt).getTime();
        const logoutTime = new Date(logoutAt).getTime();

        if (Number.isNaN(loginTime) || Number.isNaN(logoutTime)) {
            return 0;
        }

        const diffMs = logoutTime - loginTime;
        if (diffMs <= 0) {
            return 0;
        }

        return diffMs / (1000 * 60 * 60); // Convert milliseconds to hours
    }

    function resolveDailyLoginTotalHours(entry) {
        if (!entry) {
            return 0;
        }

        const directValue = Number(entry.total_hours);
        if (Number.isFinite(directValue) && directValue >= 0) {
            return directValue;
        }

        const fallbackLogout = entry.last_logout ?? entry.last_logout_fallback ?? null;
        const computed = calculateHoursFromTimes(entry.first_login, fallbackLogout);
        return Number.isFinite(computed) && computed > 0 ? computed : 0;
    }

    function resolveLongestSessionForDate(record, filterDate) {
        const breakdown = Array.isArray(record.sessionBreakdown) ? record.sessionBreakdown : [];
        let best = null;

        breakdown.forEach((session) => {
            const loginAt = session.login_at || session.loginAt || session.login_time || session.loginTime || session.date || null;
            const logoutAt = session.logout_at || session.logoutAt || session.logout_time || session.logoutTime || null;

            // Filter by date if provided
            if (filterDate && loginAt) {
                const sessionDate = new Date(loginAt).toISOString().split('T')[0];
                if (sessionDate !== filterDate) {
                    return; // Skip sessions not on the filter date
                }
            }

            const minutesRaw = session.minutes ?? session.duration_minutes ?? null;
            const minutes = Number(minutesRaw);
            let hours = Number.isFinite(minutes) && minutes > 0 ? minutes / 60 : NaN;

            if (!Number.isFinite(hours) || hours <= 0) {
                const fallback = Number(session.hours ?? session.raw_hours ?? session.total_hours ?? 0);
                hours = Number.isFinite(fallback) ? fallback : 0;
            }

            if (!best || hours > best.hours || (hours === best.hours && resolveSortTimestamp(logoutAt) > resolveSortTimestamp(best.logoutAt))) {
                best = {
                    loginAt,
                    logoutAt,
                    hours,
                };
            }
        });

        if (best) {
            return best;
        }

        // Fallback to daily summaries filtered by date
        const summaries = Array.isArray(record.dailySummaries) ? record.dailySummaries : [];
        if (summaries.length) {
            const filtered = filterDate 
                ? summaries.filter(s => {
                    const summaryDate = s.date ? new Date(s.date).toISOString().split('T')[0] : null;
                    return summaryDate === filterDate;
                })
                : summaries;

            const fallback = filtered.reduce((carry, entry) => {
                const currentHours = Number(entry.hours || 0);
                if (!carry || currentHours > Number(carry.hours || 0)) {
                    return entry;
                }
                return carry;
            }, null);

            if (fallback) {
                return {
                    loginAt: fallback.date || null,
                    logoutAt: null,
                    hours: Number(fallback.hours || 0),
                };
            }
        }

        return {
            loginAt: null,
            logoutAt: null,
            hours: 0,
        };
    }

    function resolveLongestSession(record) {
        const breakdown = Array.isArray(record.sessionBreakdown) ? record.sessionBreakdown : [];
        let best = null;

        breakdown.forEach((session) => {
            const loginAt = session.login_at || session.loginAt || session.login_time || session.loginTime || session.date || null;
            const logoutAt = session.logout_at || session.logoutAt || session.logout_time || session.logoutTime || null;
            const minutesRaw = session.minutes ?? session.duration_minutes ?? null;
            const minutes = Number(minutesRaw);
            let hours = Number.isFinite(minutes) && minutes > 0 ? minutes / 60 : NaN;

            if (!Number.isFinite(hours) || hours <= 0) {
                const fallback = Number(session.hours ?? session.raw_hours ?? session.total_hours ?? 0);
                hours = Number.isFinite(fallback) ? fallback : 0;
            }

            if (!best || hours > best.hours || (hours === best.hours && resolveSortTimestamp(logoutAt) > resolveSortTimestamp(best.logoutAt))) {
                best = {
                    loginAt,
                    logoutAt,
                    hours,
                };
            }
        });

        if (best) {
            return best;
        }

        const summaries = Array.isArray(record.dailySummaries) ? record.dailySummaries : [];
        if (summaries.length) {
            const fallback = summaries.reduce((carry, entry) => {
                const currentHours = Number(entry.hours || 0);
                if (!carry || currentHours > Number(carry.hours || 0)) {
                    return entry;
                }
                return carry;
            }, null);

            if (fallback) {
                return {
                    loginAt: fallback.date || null,
                    logoutAt: null,
                    hours: Number(fallback.hours || 0),
                };
            }
        }

        return {
            loginAt: null,
            logoutAt: null,
            hours: 0,
        };
    }

    function resolveSalaryMetrics(record) {
        const periodDays = Number(record.periodDays ?? 0);
        const loginDays = Number(record.loginDays ?? 0);
        const baseSalary = Number(record.baseSalary ?? 0);
        const workStationBase = Number(record.workStationBaseSalary ?? 0);
        const fallbackDailyRate = Number(record.dailyRate ?? 0);
        const baseSource = (record.baseSalarySource || '').toString().toLowerCase();

        let displayBase = baseSalary > 0 ? baseSalary : workStationBase;
        let dailyRate = 0;
        let noteType = 'none';

        if (displayBase > 0 && periodDays > 0) {
            dailyRate = displayBase / periodDays;
            noteType = 'period';
        } else if (displayBase > 0 && loginDays > 0) {
            dailyRate = displayBase / loginDays;
            noteType = 'attendance';
        } else if (fallbackDailyRate > 0) {
            dailyRate = fallbackDailyRate;
            const fallbackSource = (record.dailyRateSource || '').toString().toLowerCase();
            noteType = fallbackSource || 'derived';
        } else {
            dailyRate = 0;
            noteType = 'none';
        }

        displayBase = roundCurrency(displayBase);
        dailyRate = roundCurrency(dailyRate);

        let note;
        switch (noteType) {
            case 'period':
                note = baseSource === 'workstation'
                    ? `<span class="text-xs text-gray-400">(work station base ÷ ${periodDays} days)</span>`
                    : `<span class="text-xs text-gray-400">(base ÷ ${periodDays} days)</span>`;
                break;
            case 'attendance':
                note = baseSource === 'workstation'
                    ? `<span class="text-xs text-gray-400">(work station base ÷ ${loginDays} login days)</span>`
                    : `<span class="text-xs text-gray-400">(base ÷ ${loginDays} login days)</span>`;
                break;
            case 'rate':
                note = '<span class="text-xs text-gray-400">(configured rate)</span>';
                break;
            case 'workstation':
                note = '<span class="text-xs text-gray-400">(work station category)</span>';
                break;
            case 'derived':
                note = '<span class="text-xs text-gray-400">(derived)</span>';
                break;
            default:
                note = '<span class="text-xs text-gray-400">(not set)</span>';
                break;
        }

        return {
            base: displayBase,
            dailyRate,
            note,
        };
    }

    function formatPeriodLabel(periodCode) {
        if (!periodCode || typeof periodCode !== 'string') {
            return 'Select period';
        }

        const [year, month] = periodCode.split('-').map(Number);
        if (!year || !month) {
            return periodCode;
        }

        const formatter = new Intl.DateTimeFormat('en-US', { month: 'long', year: 'numeric' });
        return formatter.format(new Date(year, month - 1, 1));
    }
})();
