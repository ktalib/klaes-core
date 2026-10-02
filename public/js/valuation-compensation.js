/**
 * VFC Valuation & Compensation Dashboard Logic
 * Handles modal management, auto-calculations, location building, and AJAX submissions.
 */

window.onerror = function (msg, url, lineNo, columnNo, error) {
    const errorMsg = 'VFC JS Error: ' + msg + '\nLine: ' + lineNo + '\nURL: ' + url;
    console.error(errorMsg);
    return false;
};
console.log('VFC: Script loaded at ' + new Date().toLocaleTimeString());

if (!window.VFC) window.VFC = {};
window.VFC = {
    initialized: false,
    config: window.VFC_CONFIG || {},
    allBanks: window.VFC_BANKS || [],
    records: window.VFC_RECORDS || {},
    projectsData: [],
    currentProjectSubTotal: 0,
    currentProjectApplyPercentage: 0,
    pendingSubProjectId: null,
    pendingWorkerId: null,
    batchRecords: [],

    init: function () {
        if (this.initialized) {
            console.log('VFC: Already initialized.');
            return;
        }
        console.log('VFC: Initializing system...');
        this.initialized = true;

        // Ensure config is fresh
        this.config = window.VFC_CONFIG || {};
        this.allBanks = window.VFC_BANKS || [];
        this.loadProjects();
        this.initEventListeners();
        this.initDataTable();
        this.fetchBanks();
        this.initCapitalization();
        this.initBatchWorkflow();

        if (window.lucide) {
            console.log('VFC: Lucide detected, creating icons...');
            window.lucide.createIcons();
        } else {
            console.warn('VFC: Lucide not found!');
        }

        // Update debug info if panel exists
        const $status = $('#debug-vfc-status');
        const $count = $('#debug-records-count');
        if ($status.length) {
            $status.text('LOADED').addClass('text-green-600');
            $count.text(Object.keys(this.records).length);
        }

        console.log('VFC: System initialized successfully.');
    },

    initBatchWorkflow: function () {
        const self = this;

        // Generate Batch Button Click
        $(document).on('click', '#generate-batch-btn', function () {
            console.log('VFC: Generate Batch button clicked. Opening modal...');
            self.openApplyPercentModal();
        });

        // Percentage Input Change
        $(document).on('input', '#apply-percentage-input', function () {
            self.updateFinalTotalInModal();
        });

        // Cancel Apply Percent
        $(document).on('click', '#cancel-apply-percent', function () {
            self.closeApplyPercentModal();
        });

        // Confirm Apply Percent
        $(document).on('click', '#confirm-apply-percent', function () {
            // Blank input means "no adjustment" (0), and an explicit 0 must stay 0 —
            // never fall back to a hard-coded default here.
            const rawPercent = parseFloat($('#apply-percentage-input').val());
            const percent = isNaN(rawPercent) ? 0 : rawPercent;
            self.currentProjectApplyPercentage = percent;

            console.log('VFC: Calculation confirmed. Applied percentage:', percent);

            // Switch buttons (affects both modals)
            $('#confirm-apply-percent').addClass('hidden');
            $('#generate-batch-btn').addClass('hidden'); // Hide generate button after use
            $('.print-batch-btn').removeClass('hidden').data('apply-percentage', percent);

            // Close the Apply Percent modal so user sees the Records Modal with the now-visible Print button
            self.closeApplyPercentModal();

            Swal.fire({
                title: 'Calculation Applied',
                text: `Valuation adjustment of ${percent}% has been applied. You can now print the Compensation Sheet.`,
                icon: 'success',
                timer: 3000,
                showConfirmButton: true,
                confirmButtonText: 'OK'
            });
        });

        // Print Batch Button Click
        $(document).on('click', '.print-batch-btn', function () {
            self.printBatch(this);
        });
    },

    printBatch: function (btn) {
        const $btn = $(btn);
        const projectId = $btn.data('project-id');
        const rawPercent = parseFloat($btn.data('apply-percentage'));
        const percent = isNaN(rawPercent) ? this.currentProjectApplyPercentage : rawPercent;
        console.log('VFC: printBatch called', { projectId, percent });

        if (!projectId) {
            Swal.fire('Error', 'Project ID missing. Please reload and try again.', 'error');
            return;
        }

        const routes = this.config.routes || {};
        const route = routes.projectPrint || (routes.store ? routes.store + '/project-print' : '/valuation-compensations/project-print');
        const url = `${route}/${projectId}?apply_percentage=${percent}`;
        console.log('VFC: Opening print URL:', url);
        window.open(url, '_blank');
    },

    initCapitalization: function () {
        // Auto-capitalize all text inputs in VFC forms
        $(document).on('input', '#valuation-form input[type="text"], #valuation-form textarea, #valuation-form input[type="search"], #project-form input[type="text"], #project-form textarea', function () {
            this.value = this.value.toUpperCase();
        });
    },

    fetchBanks: function () {
        console.log('VFC: Banks loaded from database:', this.allBanks.length);
    },

    loadProjects: async function () {
        try {
            console.log('VFC: Loading projects for selection...');
            const response = await fetch(this.config.routes.projectSelection);
            this.projectsData = await response.json();
            console.log('VFC: Projects loaded:', this.projectsData.length);
            const $projSelect = $('#vfc_project_id');
            $projSelect.html('<option value="">Select Project</option>');
            this.projectsData.forEach(p => {
                const displayCode = p.fileno || p.code;
                $projSelect.append(`<option value="${p.id}">${p.name} (${displayCode})</option>`);
            });
        } catch (error) {
            console.error('VFC: Error loading projects:', error);
        }
    },

    initEventListeners: function () {
        console.log('VFC: Binding event listeners...');
        const self = this;

        // Initialize Select2 globally for multi-selects with proper styling
        if (typeof $.fn.select2 !== 'undefined') {
            $('.vfc-select2').select2({
                dropdownParent: $('#valuation-modal'),
                width: '100%',
                placeholder: 'Select items',
                allowClear: true
            });
        }

        // Project Selection Change
        $('#vfc_project_id').on('change', function () {
            const projId = $(this).val();
            if (!projId) {
                $('#project-info').addClass('hidden');
                $('#vfc_worker_id').html('<option value="">Select Worker (Select Project First)</option>');
                $('#our_ref').val('');
                return;
            }

            const proj = self.projectsData.find(p => p.id == projId);
            if (proj) {
                $('#proj_id_summary').text('# ' + proj.id);
                $('#proj_fileno_summary').text(proj.fileno);
                $('#proj_code_summary').text(proj.code);
                $('#proj_workers_summary').text(proj.workers_count || 0);
                $('#proj_valuations_summary').text(proj.valuations_count || 0);
                $('#proj_subprojects_summary').text(proj.sub_projects ? proj.sub_projects.length : 0);

                $('#project-info').removeClass('hidden');
                if (window.lucide) window.lucide.createIcons();

                $('#our_ref').val(proj.fileno || proj.code);
                $('#project_code_display').val(proj.code);
                $('#hidden_project_fileno').val(proj.fileno || proj.code);
                $('#manual_our_ref').val(proj.our_reference || '');
                $('#your_ref').val(proj.your_reference || '');

                // Populate Sub-Projects
                const $subProjSection = $('#sub-project-section');
                const $subProjSelect = $('#vfc_sub_project_id');

                if (proj.sub_projects && proj.sub_projects.length > 0) {
                    $subProjSelect.html('<option value="">Select Sub-Project</option>');
                    proj.sub_projects.forEach(sp => {
                        $subProjSelect.append(`<option value="${sp.id}">${sp.name}</option>`);
                    });
                    $subProjSection.removeClass('hidden');
                    $subProjSelect.prop('required', false);
                } else {
                    $subProjSection.addClass('hidden');
                    $subProjSelect.prop('required', false).html('<option value="">No Sub-Projects</option>');
                }

                // If we have a pending sub-project ID (from Edit modal), apply it now
                if (self.pendingSubProjectId) {
                    console.log('VFC: Applying pending sub-project ID:', self.pendingSubProjectId);
                    $subProjSelect.val(self.pendingSubProjectId);
                    self.pendingSubProjectId = null;
                }

                // Backfill location scope
                const hasSelect2 = typeof $.fn.select2 !== 'undefined';

                if (proj.district) {
                    const districts = proj.district.split(',').map(d => d.trim());
                    $('#loc_district').val(districts);
                    if (hasSelect2) $('#loc_district').trigger('change.select2');
                } else {
                    $('#loc_district').val(null);
                    if (hasSelect2) $('#loc_district').trigger('change.select2');
                }

                if (proj.lga) {
                    const lgas = proj.lga.split(',').map(l => l.trim());
                    $('#loc_lga').val(lgas);
                    if (hasSelect2) $('#loc_lga').trigger('change.select2');
                } else {
                    $('#loc_lga').val(null);
                    if (hasSelect2) $('#loc_lga').trigger('change.select2');
                }

                // Re-build the full location string
                self.buildLocation();
            }

            // Fetch workers for this project
            $.get(`${self.config.routes.projectWorkers}/${projId}/workers`, function (workers) {
                const $workerSelect = $('#vfc_worker_id');
                $workerSelect.html('<option value="">Select Worker</option>');
                workers.forEach(w => {
                    $workerSelect.append(`<option value="${w.worker_code}">${w.user.first_name} ${w.user.last_name} (${w.worker_code})</option>`);
                });

                // If we have a pending worker ID (from Edit modal), apply it now
                if (self.pendingWorkerId) {
                    console.log('VFC: Applying pending worker ID:', self.pendingWorkerId);
                    $workerSelect.val(self.pendingWorkerId);
                    self.pendingWorkerId = null;
                }
            });
        });

        // Location Auto-builder (Using delegated events for robustness.)
        $(document).on('change input', '.loc-trigger, #loc_district, #loc_lga, #loc_state', function () {
            self.buildLocation();
        });

        // Auto-calculation & Row Calculations
        $(document).on('input', '.building-length, .building-breadth, .building-area, .building-rate', function () {
            const $row = $(this).closest('.building-type-row');
            self.calculateBuildingRow($row);
            self.calculateCompensation();
        });



        // Compensated Items logic
        $(document).on('change', '.item-checkbox', function () {
            const val = $(this).val();
            const isOther = val.toLowerCase().includes('other');
            const $wrapper = $(this).closest('.flex-col').find('.item-amount-wrapper');

            if ($(this).is(':checked')) {
                $wrapper.removeClass('hidden');
                if (isOther) $('#compensated_items_other').removeClass('hidden').focus();
            } else {
                $wrapper.addClass('hidden');
                if (isOther) $('#compensated_items_other').addClass('hidden');
            }
            self.updateCompensatedItemsValue();
            self.calculateCompensation();
        });

        $(document).on('input', '.item-amount-input', function () {
            self.updateCompensatedItemsValue();
            self.calculateCompensation();
        });

        $(document).on('change', '.structure-type-dropdown', function () {
            self.updateCompensatedItemsValue();
        });

        // Building Count & Dynamic Types
        $(document).on('input', '#building_count', function () {
            self.syncBuildingTypes();
            self.calculateCompensation();
        });

        $(document).on('change', '.building-type-select', function () {
            const $row = $(this).closest('.building-type-row');
            const val = $(this).val();
            if (val === 'Other' || val === 'Others') {
                $row.find('.building-type-other').removeClass('hidden').focus();
            } else {
                $row.find('.building-type-other').addClass('hidden').val('');
            }
            self.updateFinalBuildingType();
        });

        $(document).on('input', '.building-type-other', function () {
            self.updateFinalBuildingType();
        });

        $(document).on('change', '.building-stage-select', function () {
            const $row = $(this).closest('.building-type-row');
            const val = $(this).val();
            if (val === 'Other' || val === 'Others') {
                $row.find('.building-stage-other').removeClass('hidden').focus();
            } else {
                $row.find('.building-stage-other').addClass('hidden').val('');
            }
            self.updateFinalBuildingType();
        });

        $(document).on('input', '.building-stage-other', function () {
            self.updateFinalBuildingType();
        });

        $(document).on('input', '.building-floors', function () {
            const $row = $(this).closest('.building-type-row');
            self.calculateBuildingRow($row);
            self.calculateCompensation();
            self.updateFinalBuildingType();
        });

        $('#compensated_items_other').on('input', function () {
            self.updateCompensatedItemsValue();
        });

        // Sub-Item Calculations (Linear, Volume, Area/Shed)
        $(document).on('input', '.sub-calc-trigger', function () {
            const $container = $(this).closest('.item-container');
            // Formula parsing for Linear items (e.g. "10 + 20.5 + 5")
            const $formulaInput = $container.find('.sub-length-formula');
            let length = 0;

            if ($formulaInput.length > 0) {
                const formula = $formulaInput.val() || "";
                // Split by + and sum up segments
                const segments = formula.split('+');
                segments.forEach(s => {
                    const val = parseFloat(s.trim());
                    if (!isNaN(val)) length += val;
                });
                $container.find('.sub-total-length-display').text(length > 0 ? length.toFixed(2) + 'm' : '0.00m');
                $container.find('.sub-length').val(length); // Update hidden input for calc
            } else {
                length = parseFloat($container.find('.sub-length').val()) || 0;
            }

            const width = parseFloat($container.find('.sub-width').val()) || 0;
            const height = parseFloat($container.find('.sub-height').val()) || 0;
            const rate = parseFloat($container.find('.sub-rate').val()) || 0;

            let total = 0;

            if ($container.find('.sub-height').length > 0) {
                // Volume Group: L × W × H × Rate
                const vol = length * width * height;
                $container.find('.sub-total-volume-display').text(vol > 0 ? vol.toFixed(3) + ' m³' : '0.000 m³');
                $container.find('.sub-volume-val').val(vol);
                total = vol * rate;
            } else if ($container.find('.sub-width').length > 0) {
                // Volume Group (Now Area L×B): Total Area = (L×B) × Rate
                // OR Shed/Area Group: L × W × Rate
                const $volDisplay = $container.find('.sub-total-volume-display');
                if ($volDisplay.length > 0) {
                    const area = length * width;
                    $volDisplay.text(area > 0 ? area.toFixed(2) + 'm²' : '0.00m²');
                    $container.find('.sub-volume-val').val(area);
                    total = area * rate;
                } else {
                    total = length * width * rate;
                }
            } else if ($container.find('.sub-length').length > 0 || $formulaInput.length > 0) {
                // Linear Group: L × Rate
                total = length * rate;
            }

            $container.find('.item-amount-input').val(total > 0 ? total.toFixed(2) : '');
            self.updateCompensatedItemsValue();
        });

        // Bank Search logic
        const $bankSearch = $('#bank_search');
        const $bankDropdown = $('#bank_dropdown');
        const $bankNameHidden = $('#bank_name_val');
        const $bankLogo = $('#selected_bank_logo');

        $(document).on('focus', '#bank_search', function () {
            self.renderBanks($(this).val());
            $('#bank_dropdown').removeClass('hidden');
        });

        $(document).on('input', '#bank_search', function () {
            // Mirror whatever is typed as the bank name so an unlisted bank can
            // still be specified manually. Selecting a known bank (below) overrides this.
            $bankNameHidden.val($(this).val());
            $bankLogo.html('<i data-lucide="building-2" class="h-4 w-4 text-slate-400"></i>');
            if (window.lucide) lucide.createIcons();
            self.renderBanks($(this).val());
        });

        $(document).on('click', '.bank-option', function () {
            const name = $(this).data('name');
            const logo = $(this).data('logo');
            $bankSearch.val(name);
            $bankNameHidden.val(name);
            $bankLogo.html(`<img src="${logo}" alt="${name}" class="w-full h-full object-contain">`);
            $bankDropdown.addClass('hidden');
        });

        // Use the exact text the user typed as a custom (unlisted) bank name.
        $(document).on('click', '.bank-option-custom', function () {
            const name = String($(this).data('name'));
            $bankSearch.val(name);
            $bankNameHidden.val(name);
            $bankLogo.html('<i data-lucide="building-2" class="h-4 w-4 text-slate-400"></i>');
            if (window.lucide) lucide.createIcons();
            $bankDropdown.addClass('hidden');
        });

        // "Other" – clear the field so the user can type a bank name manually.
        $(document).on('click', '.bank-option-other', function () {
            $bankSearch.val('').attr('placeholder', 'Type bank name...').focus();
            $bankNameHidden.val('');
            $bankLogo.html('<i data-lucide="building-2" class="h-4 w-4 text-slate-400"></i>');
            if (window.lucide) lucide.createIcons();
            self.renderBanks('');
        });

        $(document).on('click', function (e) {
            if (!$(e.target).closest('.relative.group').length) {
                $bankDropdown.addClass('hidden');
            }
        });

        // Modal close buttons (Using delegated binding for reliability when moved to body)
        $(document).on('click', '.close-modal', function () {
            console.log('VFC: Close Modal clicked');
            self.closeModal();
        });

        $(document).on('click', '.close-records-modal, #records-modal-overlay', function () {
            console.log('VFC: Close Records Modal clicked');
            self.closeRecordsModal();
        });

        // Form submission (Using delegated binding for better reliability)
        $(document).on('submit', '#valuation-form', function (e) {
            e.preventDefault();
            self.saveRecord();
            return false;
        });



        // Open Records Modal (Delegated for DataTable compatibility)
        $(document).on('click', '.vfc-view-project', function (e) {
            e.preventDefault();
            const projectId = $(this).data('project-id');
            self.openRecordsModal(projectId);
        });

        // Open Records Modal (Legacy support for other triggers)
        $(document).on('click', '.vfc-open-records', function (e) {
            e.preventDefault();
            self.openRecordsModalFromData(this);
        });


        // Toggle 'Other' inputs
        $(document).on('change', '#building_type', function () {
            if ($(this).val() === 'Other' || $(this).val() === 'Others') {
                $('#building_type_other').removeClass('hidden').focus();
            } else {
                $('#building_type_other').addClass('hidden').val('');
            }
        });

        $(document).on('change', '#completion_stage_select', function () {
            if ($(this).val() === 'Other' || $(this).val() === 'Others') {
                $('#completion_stage_other').removeClass('hidden').focus();
            } else {
                $('#completion_stage_other').addClass('hidden').val('');
            }
        });

        $(document).on('click', '.vfc-create-trigger', function (e) {
            e.preventDefault();
            self.openCreateModal();
        });

        console.log('VFC: Event listeners bound.');
    },

    renderBanks: function (filter = '') {
        const term = (filter || '').trim();
        const filtered = this.allBanks.filter(b => b.title.toLowerCase().includes(filter.toLowerCase()));
        let html = '';
        filtered.forEach(bank => {
            html += `
                <div class="bank-option flex items-center gap-3 px-4 py-3 hover:bg-blue-50 cursor-pointer transition border-b border-slate-50 last:border-0" data-name="${bank.title}" data-logo="${bank.route}">
                    <div class="w-8 h-8 rounded-lg overflow-hidden border border-slate-100 bg-white flex-shrink-0 flex items-center justify-center p-1">
                        <img src="${bank.route}" alt="${bank.title}" class="w-full h-full object-contain" onerror="this.src='https://ui-avatars.com/api/?name=${encodeURIComponent(bank.title)}&background=f1f5f9&color=64748b'">
                    </div>
                    <span class="text-sm font-medium text-slate-700">${bank.title}</span>
                </div>
            `;
        });

        // If what the user typed isn't an exact known bank, let them use it verbatim.
        const exactMatch = term && this.allBanks.some(b => b.title.toLowerCase() === term.toLowerCase());
        if (term && !exactMatch) {
            const safe = term.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
            html += `
                <div class="bank-option-custom flex items-center gap-3 px-4 py-3 hover:bg-blue-50 cursor-pointer transition border-b border-slate-50" data-name="${safe}">
                    <div class="w-8 h-8 rounded-lg border border-blue-100 bg-blue-50 flex-shrink-0 flex items-center justify-center">
                        <i data-lucide="plus" class="h-4 w-4 text-blue-500"></i>
                    </div>
                    <span class="text-sm font-medium text-slate-700">Use &quot;<span class="font-bold">${safe}</span>&quot; as bank name</span>
                </div>
            `;
        }

        // Always offer an explicit "Other" option for manual entry.
        html += `
            <div class="bank-option-other flex items-center gap-3 px-4 py-3 hover:bg-blue-50 cursor-pointer transition">
                <div class="w-8 h-8 rounded-lg border border-slate-100 bg-slate-50 flex-shrink-0 flex items-center justify-center">
                    <i data-lucide="pencil" class="h-4 w-4 text-slate-500"></i>
                </div>
                <span class="text-sm font-medium text-slate-700">Other (specify manually)</span>
            </div>
        `;

        $('#bank_dropdown').html(html);
        if (window.lucide) lucide.createIcons();
    },

    initDataTable: function () {
        if ($.fn.DataTable.isDataTable('#valuationTable')) {
            $('#valuationTable').DataTable().destroy();
        }

        $('#valuationTable').DataTable({
            responsive: true,
            order: [[0, 'asc']], // Order by S/N
            pageLength: 10,
            dom: '<"flex flex-col md:flex-row justify-between gap-4 mb-4"fB>rt<"flex flex-col md:flex-row justify-between items-center gap-4 mt-4"ip>',
            buttons: [
                { extend: 'copy', className: 'px-4 py-2 bg-slate-100 text-slate-600 rounded-lg text-xs font-bold border-0 hover:bg-slate-200' },
                { extend: 'csv', className: 'px-4 py-2 bg-slate-100 text-slate-600 rounded-lg text-xs font-bold border-0 hover:bg-slate-200' },
                { extend: 'excel', className: 'px-4 py-2 bg-slate-100 text-slate-600 rounded-lg text-xs font-bold border-0 hover:bg-slate-200' },
                { extend: 'pdf', className: 'px-4 py-2 bg-slate-100 text-slate-600 rounded-lg text-xs font-bold border-0 hover:bg-slate-200' },
                { extend: 'print', className: 'px-4 py-2 bg-slate-100 text-slate-600 rounded-lg text-xs font-bold border-0 hover:bg-slate-200' }
            ],
            language: {
                search: "",
                searchPlaceholder: "Search projects..."
            }
        });
    },

    updateCompensatedItemsValue: function () {
        const data = {
            structure_type: '',
            buildings: [],
            items: []
        };

        // 1. Get structure type from categorized dropdown
        $('.structure-type-dropdown').each(function () {
            const val = $(this).val();
            if (val) data.structure_type = val;
        });

        // 2. Get per-building data from each building row
        $('.building-type-row').each(function () {
            let type = $(this).find('.building-type-select').val() || '';
            const typeOther = $(this).find('.building-type-other').val();
            if ((type === 'Other' || type === 'Others') && typeOther) type = typeOther;

            let stage = $(this).find('.building-stage-select').val() || '';
            const stageOther = $(this).find('.building-stage-other').val();
            if ((stage === 'Other' || stage === 'Others') && stageOther) stage = stageOther;

            data.buildings.push({
                building_type: type,
                stage: stage,
                floors: $(this).find('.building-floors').val() || '',
                length: parseFloat($(this).find('.building-length').val()) || 0,
                breadth: parseFloat($(this).find('.building-breadth').val()) || 0,
                area: parseFloat($(this).find('.building-area').val()) || 0,
                rate: parseFloat($(this).find('.building-rate').val()) || 0,
                amount: parseFloat($(this).find('.building-comp').val()) || 0
            });
        });

        // 3. Get selected compensated items, their measurements and amounts
        $('.item-checkbox:checked').each(function () {
            const itemName = $(this).val();
            const $container = $(this).closest('.item-container');
            const amount = parseFloat($container.find('.item-amount-wrapper .item-amount-input').val()) || 0;
            // Measurements used to derive the amount (kept so the sheet can show
            // the real area/volume instead of falling back to "Allow").
            const length  = parseFloat($container.find('.sub-length').val()) || 0;
            const width   = parseFloat($container.find('.sub-width').val()) || 0;
            const height  = parseFloat($container.find('.sub-height').val()) || 0;
            const measure = parseFloat($container.find('.sub-volume-val').val()) || 0; // area (m²) or volume (m³)
            const rate    = parseFloat($container.find('.sub-rate').val()) || 0;
            data.items.push({ name: itemName, amount, length, width, height, measure, rate });
        });

        $('#compensated_items_val').val(JSON.stringify(data));
    },

    buildLocation: function () {
        const plot = $('#plot_no').val();

        let district = $('#loc_district').val();
        if (Array.isArray(district)) district = district.join(', ');

        let lga = $('#loc_lga').val();
        if (Array.isArray(lga)) lga = lga.join(', ');

        const state = $('#loc_state').val();

        let loc = '';
        if (plot) loc += "PLOT " + plot;
        if (district) loc += (loc ? ', ' : '') + district;
        if (lga) loc += (loc ? ', ' : '') + lga;
        if (state) loc += (loc ? ', ' : '') + state + ' State';

        if (loc) {
            $('#location').val(loc);
        }
    },

    calculateBuildingRow: function ($row) {
        const length = parseFloat($row.find('.building-length').val()) || 0;
        const breadth = parseFloat($row.find('.building-breadth').val()) || 0;
        const floors = parseFloat($row.find('.building-floors').val()) || 1;

        // If L and B are provided, Area Covered = footprint (L×B) × floors (total built-up area)
        if (length > 0 && breadth > 0) {
            const areaVal = length * breadth * floors;
            $row.find('.building-area').val(areaVal.toFixed(2));
        }

        const area = parseFloat($row.find('.building-area').val()) || 0;
        const rate = parseFloat($row.find('.building-rate').val()) || 0;
        // Floors are already baked into Area Covered, so Amount = Area × Rate.
        const total = area * rate;
        $row.find('.building-comp').val(total.toFixed(2));
    },

    calculateCompensation: function () {
        let totalLength = 0;
        let totalBreadth = 0;
        let totalArea = 0;
        let totalComp = 0;
        let rateSum = 0;
        let rateCount = 0;
        let weightedRateSum = 0;

        $('.building-type-row').each(function () {
            const length = parseFloat($(this).find('.building-length').val()) || 0;
            const breadth = parseFloat($(this).find('.building-breadth').val()) || 0;
            const area = parseFloat($(this).find('.building-area').val()) || 0;
            const rate = parseFloat($(this).find('.building-rate').val()) || 0;
            const comp = parseFloat($(this).find('.building-comp').val()) || 0;

            totalLength += length;
            totalBreadth += breadth;
            totalArea += area;
            totalComp += comp;

            if (rate > 0) {
                rateSum += rate;
                rateCount++;
                weightedRateSum += area * rate;
            }
        });

        // Compensated items carry value too (e.g. fences/walls on an "Allow" building
        // with no area/rate). The record's compensation amount = buildings + items,
        // matching how the batch report derives the building portion (amount − items).
        let itemsTotal = 0;
        $('.item-checkbox:checked').each(function () {
            const $wrapper = $(this).closest('.item-container').find('.item-amount-wrapper');
            itemsTotal += parseFloat($wrapper.find('.item-amount-input').val()) || 0;
        });
        totalComp += itemsTotal;

        // Compute average rate
        let averageRate = 0;
        if (totalArea > 0 && weightedRateSum > 0) {
            averageRate = weightedRateSum / totalArea;
        } else if (rateCount > 0) {
            averageRate = rateSum / rateCount;
        }

        $('#length').val(totalLength > 0 ? totalLength.toFixed(2) : '');
        $('#breadth').val(totalBreadth > 0 ? totalBreadth.toFixed(2) : '');
        $('#area_covered').val(totalArea > 0 ? totalArea.toFixed(2) : '');
        $('#rate_of_cost').val(averageRate > 0 ? averageRate.toFixed(2) : '');
        $('#compensation_amount').val(totalComp > 0 ? totalComp.toFixed(2) : '');
    },

    saveRecord: function () {
        const id = $('#record_id').val();
        const url = id ? `${this.config.routes.store}/${id}` : this.config.routes.store;
        const method = 'POST';

        let formData = $('#valuation-form').serializeArray();
        if (id) {
            formData.push({ name: '_method', value: 'PUT' });
        }



        Swal.fire({
            title: 'Saving...',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });

        $.ajax({
            url: url,
            type: method,
            data: formData,
            headers: {
                'X-CSRF-TOKEN': this.config.csrf
            },
            success: function (response) {
                if (response.success) {
                    Swal.fire('Success!', response.message, 'success').then(() => {
                        window.location.reload();
                    });
                }
            },
            error: function (xhr) {
                let msg = 'Something went wrong.';
                if (xhr.status === 422) {
                    const errors = xhr.responseJSON.errors;
                    msg = Object.values(errors).flat().join('<br>');
                }
                Swal.fire('Error!', msg, 'error');
            }
        });
    },

    deleteRecord: function (id, name) {
        const self = this;
        Swal.fire({
            title: 'Are you sure?',
            text: `You are about to delete the valuation for ${name}. This action is reversible by administrators.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: `${self.config.routes.store}/${id}`,
                    type: 'DELETE',
                    data: {
                        _token: self.config.csrf
                    },
                    success: function (response) {
                        if (response.success) {
                            Swal.fire('Deleted!', response.message, 'success').then(() => {
                                window.location.reload();
                            });
                        }
                    },
                    error: function (xhr) {
                        Swal.fire('Error!', 'Something went wrong.', 'error');
                    }
                });
            }
        });
    },

    openCreateModal: function () {
        console.log('VFC: Opening Create Modal...');
        this.closeRecordsModal();
        const $modal = $('#valuation-modal');
        if ($modal.parent().is('body') === false) {
            $('body').append($modal);
        }
        $modal.css('z-index', '110');

        $('#modal-title').text('Valuation for Compensation Data Entry');
        $('#valuation-form')[0].reset();
        $('#record_id').val('');
        $('#our_ref').val('').attr('placeholder', 'Select project...');
        $('#project_code_display').val('').attr('placeholder', 'Select project...');
        $('#manual_our_ref').val('');
        $('#your_ref').val('');

        $('#project-selection-section').removeClass('hidden');
        $('#vfc_project_id').val('').trigger('change');
        $('#project-info').addClass('hidden');

        $('#selected_bank_logo').html('<i data-lucide="building-2" class="h-4 w-4 text-slate-400"></i>');
        if (window.lucide) window.lucide.createIcons();
        $('#compensated_items_other, #building_type_other, #completion_stage_other').addClass('hidden').val('');
        $('.item-checkbox').prop('checked', false);
        $('.structure-type-dropdown').val(''); // Clear new dropdowns
        $('.item-amount-wrapper').addClass('hidden');
        $('.item-amount-input, .sub-length, .sub-width, .sub-height, .sub-rate, .sub-length-formula, .sub-volume-val').val('');
        $('.sub-total-volume-display').text('0.000 m³');
        $('.sub-total-length-display').text('0.00m');

        this.clearBatch();
        this.syncBuildingTypes();

        $('#valuation-modal').removeClass('hidden').addClass('flex').attr('style', 'display: flex !important; z-index: 110;');
        setTimeout(() => {
            $('#modal-overlay').addClass('opacity-100');
            console.log('VFC: Modal displayed.');
        }, 10);
    },

    openEditModal: function (record) {
        // We don't necessarily close the records modal so user can go back
        const $modal = $('#valuation-modal');
        if ($modal.parent().is('body') === false) {
            $('body').append($modal);
        }
        // Set z-index higher than records modal (which is 99999)
        $modal.css('z-index', '110');

        const self = this;
        $('#modal-title').text('Edit Valuation Record');
        $('#valuation-form')[0].reset();
        $('#record_id').val(record.id);

        $('#project-selection-section').removeClass('hidden');

        // Track pending IDs to be applied once the dropdowns are populated
        this.pendingSubProjectId = record.sub_project_id;
        this.pendingWorkerId = record.worker_id;

        $('#vfc_project_id').val(record.project_id).trigger('change');

        $('#our_ref').val(record.project_fileno || record.our_ref);
        $('#project_code_display').val(record.project ? record.project.project_code : 'N/A');
        $('#manual_our_ref').val(record.our_ref);
        $('#your_ref').val(record.your_ref);
        $('#valuation_date').val(record.valuation_date.split('T')[0]);
        $('#owner_name').val(record.owner_name);

        const count = parseInt(record.building_count) || 1;
        $('#building_count').val(count);
        self.syncBuildingTypes();

        const types = record.building_type ? record.building_type.split(', ') : [];
        const stages = record.completion_stage ? record.completion_stage.split(', ') : [];
        const floors = record.number_of_floors ? record.number_of_floors.split(', ') : [];

        // Distribute totals per building
        const lenPerB = record.length ? (parseFloat(record.length) / count).toFixed(2) : '';
        const brdPerB = record.breadth ? (parseFloat(record.breadth) / count).toFixed(2) : '';
        const areaPerB = record.area_covered ? (parseFloat(record.area_covered) / count).toFixed(2) : '';
        const ratePerB = record.rate_of_cost ? parseFloat(record.rate_of_cost).toFixed(2) : '';
        const compPerB = record.compensation_amount ? (parseFloat(record.compensation_amount) / count).toFixed(2) : '';

        $('.building-type-row').each(function (idx) {
            const $row = $(this);

            // Populate Building Type
            const typeVal = types[idx] || '';
            const $typeSelect = $row.find('.building-type-select');
            if ($typeSelect.find(`option[value="${typeVal}"]`).length > 0) {
                $typeSelect.val(typeVal).trigger('change');
            } else if (typeVal) {
                $typeSelect.val('Other').trigger('change');
                $row.find('.building-type-other').val(typeVal).removeClass('hidden');
            }

            // Populate Completion Stage
            const stageVal = stages[idx] || '';
            const $stageSelect = $row.find('.building-stage-select');
            if ($stageSelect.find(`option[value="${stageVal}"]`).length > 0) {
                $stageSelect.val(stageVal).trigger('change');
            } else if (stageVal) {
                $stageSelect.val('Other').trigger('change');
                $row.find('.building-stage-other').val(stageVal).removeClass('hidden');
            }

            // Populate Number of Floors
            $row.find('.building-floors').val(floors[idx] || '');

            // Populate measurements
            $row.find('.building-length').val(lenPerB);
            $row.find('.building-breadth').val(brdPerB);
            $row.find('.building-area').val(areaPerB);
            $row.find('.building-rate').val(ratePerB);
            $row.find('.building-comp').val(compPerB);
        });

        // Set global readonly fields
        $('#length').val(record.length);
        $('#breadth').val(record.breadth);
        $('#area_covered').val(record.area_covered);
        $('#rate_of_cost').val(record.rate_of_cost);
        $('#compensation_amount').val(record.compensation_amount);

        $('#account_name').val(record.account_name);
        $('#account_number').val(record.account_number);
        $('#bank_name_val').val(record.bank_name);
        $('#bank_search').val(record.bank_name);

        const bank = this.allBanks.find(b => b.title === record.bank_name);
        if (bank) {
            $('#selected_bank_logo').html(`<img src="${bank.route}" alt="${bank.title}" class="w-full h-full object-contain">`);
        } else {
            $('#selected_bank_logo').html('<i data-lucide="building-2" class="h-4 w-4 text-slate-400"></i>');
            if (window.lucide) window.lucide.createIcons();
        }

        $('#phone_number').val(record.phone_number);
        $('#nin').val(record.nin);
        $('#remarks').val(record.remarks);

        if (record.compensated_items) {
            let parsed = null;
            try { parsed = JSON.parse(record.compensated_items); } catch (e) {}

            if (parsed && parsed.buildings) {
                // JSON format (new)
                if (parsed.structure_type) {
                    $('.structure-type-dropdown').val(parsed.structure_type);
                }

                const count = parsed.buildings.length || 1;
                $('#building_count').val(count);
                this.syncBuildingTypes();

                parsed.buildings.forEach((bldg, idx) => {
                    const $row = $('.building-type-row').eq(idx);
                    $row.find('.building-type-select').val(bldg.building_type || '');
                    $row.find('.building-stage-select').val(bldg.stage || '');
                    $row.find('.building-floors').val(bldg.floors || '');
                    $row.find('.building-length').val(bldg.length || '');
                    $row.find('.building-breadth').val(bldg.breadth || '');
                    $row.find('.building-area').val(bldg.area || '');
                    $row.find('.building-rate').val(bldg.rate || '');
                    $row.find('.building-comp').val(bldg.amount || '');
                });

                if (parsed.items) {
                    parsed.items.forEach(item => {
                        const $cb = $(`.item-checkbox[value="${item.name}"]`);
                        if ($cb.length > 0) {
                            $cb.prop('checked', true);
                            const $wrapper = $cb.closest('.flex-col').find('.item-amount-wrapper');
                            $wrapper.removeClass('hidden');
                            if (item.amount) $wrapper.find('.item-amount-input').val(item.amount);
                            if (item.name.toLowerCase().includes('other')) {
                                $('#compensated_items_other').removeClass('hidden');
                            }
                        }
                    });
                }
            } else {
                // Legacy string format fallback
                const rawItems = record.compensated_items.split(', ').map(i => i.trim());
                let otherItems = [];

                rawItems.forEach(itemStr => {
                    if (itemStr.startsWith('[') && itemStr.includes(': ') && itemStr.endsWith(']')) {
                        const content = itemStr.replace('[', '').replace(']', '');
                        const [label, val] = content.split(': ').map(s => s.trim());
                        $('.structure-type-dropdown').each(function () {
                            const dropdownLabel = $(this).prev('label').text().trim();
                            if (dropdownLabel === label) $(this).val(val);
                        });
                        return;
                    }
                    let itemName = itemStr;
                    let amount = '';
                    if (itemStr.includes(' (₦')) {
                        const parts = itemStr.split(' (₦');
                        itemName = parts[0].trim();
                        amount = parts[1].replace(')', '').replace(/,/g, '');
                    }
                    const $cb = $(`.item-checkbox[value="${itemName}"]`);
                    if ($cb.length > 0) {
                        $cb.prop('checked', true);
                        const $wrapper = $cb.closest('.flex-col').find('.item-amount-wrapper');
                        $wrapper.removeClass('hidden');
                        if (amount) $wrapper.find('.item-amount-input').val(amount);
                        if (itemName.toLowerCase().includes('other')) {
                            $('#compensated_items_other').removeClass('hidden');
                        }
                    } else {
                        otherItems.push(itemStr);
                    }
                });

                if (otherItems.length > 0 || record.compensated_items_other) {
                    const $otherCb = $('.item-checkbox').filter(function () {
                        return $(this).val().toLowerCase().includes('other');
                    });
                    if (otherItems.length > 0) {
                        $otherCb.prop('checked', true);
                        $otherCb.closest('.flex-col').find('.item-amount-wrapper').removeClass('hidden');
                        $('#compensated_items_other').val(record.compensated_items_other || otherItems.join(', ')).removeClass('hidden');
                    }
                }
            }
            $('#compensated_items_val').val(record.compensated_items);
        }

        $('#plot_no').val(record.plot_no);
        $('#street_name').val(record.street_name);
        $('#location').val(record.location);

        $('#valuation-modal').removeClass('hidden').addClass('flex').attr('style', 'display: flex !important; z-index: 110;');
        setTimeout(() => $('#modal-overlay').addClass('opacity-100'), 10);
        if (window.lucide) window.lucide.createIcons();
    },

    openEditModalFromBase64: function (base64) {
        try {
            const json = decodeURIComponent(escape(atob(base64)));
            const record = JSON.parse(json);
            this.openEditModal(record);
        } catch (e) {
            console.error('VFC: Failed to parse base64 record', e);
            Swal.fire('Error', 'Failed to load record for editing.', 'error');
        }
    },

    // The true compensation total for a record = sum of building amounts + sum of
    // compensated-item amounts, derived from the compensated_items JSON. This is
    // robust to a null/legacy compensation_amount (e.g. an "Allow" building whose
    // value sits entirely in the items), so totals always count. Falls back to the
    // stored compensation_amount when there is no parseable items JSON.
    computeRecordTotal: function (record) {
        if (record && record.compensated_items) {
            let parsed = null;
            try { parsed = JSON.parse(record.compensated_items); } catch (e) {}
            if (parsed && (parsed.buildings || parsed.items)) {
                let sum = 0;
                (parsed.buildings || []).forEach(b => { sum += parseFloat(b.amount) || 0; });
                (parsed.items || []).forEach(i => { sum += parseFloat(i.amount) || 0; });
                return sum;
            }
        }
        return parseFloat(record && record.compensation_amount) || 0;
    },

    openRecordsModal: function (projectId) {
        // Handle potential type mismatch (integer vs string) or null/empty
        const targetId = (projectId === null || projectId === undefined || projectId === '') ? "" : projectId;
        console.log('VFC: Requesting records for Project ID:', targetId);

        // Lookup group in the pre-loaded window.VFC_RECORDS
        const records = window.VFC_RECORDS || {};
        const group = records[targetId];

        if (!group || group.length === 0) {
            console.error('VFC: No records found for Project ID:', targetId);
            console.log('VFC: Available record keys in VFC_RECORDS:', Object.keys(records));
            Swal.fire('No Records', 'Could not find valuation records for this selection.', 'info');
            return;
        }

        try {
            console.log('VFC: Rendering modal content for project ID:', targetId);
            const project = group[0].project;
            console.log('VFC: Project Object:', project);

            $('#records-modal-title').text(project ? project.project_name : 'Individual Records');
            $('#records-modal-subtitle').text(project ? `${project.project_code} | ${project.project_fileno}` : '');

            // Reset buttons visibility
            $('#generate-batch-btn').removeClass('hidden').data('project-id', project ? project.id : '');
            $('.print-batch-btn').addClass('hidden').data('project-id', project ? project.id : '');

            // Group records by sub-project and aggregate items
            const subProjectGroups = {};
            group.forEach(record => {
                const subId = record.sub_project_id || 'unassigned';
                if (!subProjectGroups[subId]) {
                    subProjectGroups[subId] = {
                        name: record.sub_project ? record.sub_project.name : 'Unassigned Sub-Project',
                        records: [],
                        total: 0,
                        items: {} // Summary of items
                    };
                }
                subProjectGroups[subId].records.push(record);
                subProjectGroups[subId].total += this.computeRecordTotal(record);

                // Parse items for summary
                if (record.compensated_items) {
                    const items = record.compensated_items.split(', ');
                    items.forEach(itemStr => {
                        if (itemStr.startsWith('[Structure: ')) return; // Skip structure type in items summary

                        let name = itemStr;
                        let amount = 0;
                        if (itemStr.includes(' (₦')) {
                            const parts = itemStr.split(' (₦');
                            name = parts[0].trim();
                            amount = parseFloat(parts[1].replace(')', '').replace(/,/g, '')) || 0;
                        }
                        if (!subProjectGroups[subId].items[name]) {
                            subProjectGroups[subId].items[name] = { count: 0, amount: 0 };
                        }
                        subProjectGroups[subId].items[name].count++;
                        subProjectGroups[subId].items[name].amount += amount;
                    });
                }
            });

            let html = '';
            let projectGrandTotal = 0;

            Object.values(subProjectGroups).forEach(subGroup => {
                projectGrandTotal += subGroup.total;

                // 1. Sub-Project Group Header
                html += `
                    <tr class="bg-slate-50/80 border-l-4 border-indigo-500">
                        <td colspan="10" class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                <i data-lucide="layers" class="h-3 w-3 text-indigo-500"></i>
                                <span class="text-[11px] font-black text-slate-800 uppercase tracking-widest">${subGroup.name}</span>
                                <span class="text-[9px] font-bold text-indigo-500 bg-indigo-50 px-2 py-0.5 rounded-full border border-indigo-100/50">${subGroup.records.length} Records</span>
                            </div>
                        </td>
                    </tr>
                `;

                // 2. Records
                subGroup.records.forEach((record, recordIndex) => {
                    // Parse compensated_items — JSON (new) or legacy string
                    let buildings = [];
                    let subItems  = [];
                    if (record.compensated_items) {
                        let parsed = null;
                        try { parsed = JSON.parse(record.compensated_items); } catch(e) {}

                        if (parsed && parsed.buildings) {
                            buildings = parsed.buildings || [];
                            subItems  = parsed.items    || [];
                        } else {
                            // Legacy string fallback
                            record.compensated_items.split(', ').forEach(itemStr => {
                                if (itemStr.startsWith('[')) return;
                                let name = itemStr, amount = 0;
                                if (itemStr.includes(' (₦')) {
                                    const p = itemStr.split(' (₦');
                                    name   = p[0].trim();
                                    amount = parseFloat(p[1].replace(')', '').replace(/,/g, '')) || 0;
                                }
                                if (name) subItems.push({ name, amount });
                            });
                        }
                    }

                    // Fallback: if no JSON buildings, build one from aggregate columns
                    if (buildings.length === 0) {
                        const bType = record.building_type === 'Other' ? (record.building_type_other || 'Other') : (record.building_type || '');
                        buildings = [{
                            building_type: bType,
                            stage: record.completion_stage || '',
                            area:  parseFloat(record.area_covered)  || 0,
                            rate:  parseFloat(record.rate_of_cost)  || 0,
                            amount: (parseFloat(record.compensation_amount) || 0) - subItems.reduce((a, i) => a + (parseFloat(i.amount) || 0), 0)
                        }];
                    }

                    const dataRows  = buildings.length + subItems.length;
                    const totalRows = dataRows + (dataRows > 1 ? 1 : 0); // +1 for record total row
                    const actionsTd = `
                        <td rowspan="${totalRows}" class="px-2 py-4 text-right align-top">
                            <div class="flex justify-end gap-1 transition-opacity">
                                <a href="${this.config.routes.store}/${record.id}" target="_blank" class="p-1.5 text-blue-600 hover:bg-blue-50 rounded-lg" title="View/Print">
                                    <i data-lucide="printer" class="h-3.5 w-3.5"></i>
                                </a>
                                <button onclick='VFC.openEditModalFromBase64("${btoa(unescape(encodeURIComponent(JSON.stringify(record))))}")' class="p-1.5 text-amber-600 hover:bg-amber-50 rounded-lg" title="Edit">
                                    <i data-lucide="edit-3" class="h-3.5 w-3.5"></i>
                                </button>
                                <button onclick="VFC.deleteRecord(${record.id}, '${record.owner_name}')" class="p-1.5 text-red-600 hover:bg-red-50 rounded-lg" title="Delete">
                                    <i data-lucide="trash-2" class="h-3.5 w-3.5"></i>
                                </button>
                            </div>
                        </td>
                    `;

                    // First building row — includes rowspan cells for owner, account, phone, actions
                    const b0 = buildings[0];
                    html += `
                        <tr class="hover:bg-blue-50/10 transition-colors group/row border-b border-slate-50">
                            <td rowspan="${totalRows}" class="px-2 py-4 text-xs font-bold text-slate-400 align-top">${recordIndex + 1}</td>
                            <td rowspan="${totalRows}" class="px-2 py-4 font-bold text-slate-700 uppercase text-[11px] align-top">
                                ${record.owner_name}
                                <div class="text-[9px] font-normal text-slate-400 mt-1 font-mono">${record.our_ref || ''}</div>
                            </td>
                            <td class="px-2 py-4 text-[11px] text-slate-600 font-bold">${b0.building_type || ''}</td>
                            <td class="px-2 py-4 text-[11px] text-center text-slate-500">1</td>
                            <td class="px-2 py-4 text-[11px] text-center text-slate-500">${(b0.area || 0) > 0 ? parseFloat(b0.area).toLocaleString() : 'Allow'}</td>
                            <td class="px-2 py-4 text-[11px] text-right text-slate-500 font-mono">₦${parseFloat(b0.rate || 0).toLocaleString()}</td>
                            <td class="px-2 py-4 text-[11px] text-right text-slate-700 font-bold font-mono">₦${parseFloat(b0.amount || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}</td>
                            <td rowspan="${totalRows}" class="px-2 py-4 align-top">
                                <div class="text-[10px] font-bold text-slate-600">${record.account_number || 'N/A'}</div>
                                <div class="text-[9px] text-slate-400">${record.bank_name || ''}</div>
                            </td>
                            <td rowspan="${totalRows}" class="px-2 py-4 text-[10px] text-slate-500 align-top">${record.phone_number || 'N/A'}</td>
                            ${actionsTd}
                        </tr>
                    `;

                    // Additional building rows (Building 2, 3, …)
                    buildings.slice(1).forEach(b => {
                        html += `
                            <tr class="hover:bg-blue-50/10 border-b border-slate-50/50">
                                <td class="px-2 py-2 text-[11px] text-slate-600 font-bold pl-4">• ${b.building_type || ''}</td>
                                <td class="px-2 py-2 text-[11px] text-center text-slate-500">1</td>
                                <td class="px-2 py-2 text-[11px] text-center text-slate-500">${(b.area || 0) > 0 ? parseFloat(b.area).toLocaleString() : 'Allow'}</td>
                                <td class="px-2 py-2 text-[11px] text-right text-slate-500 font-mono">₦${parseFloat(b.rate || 0).toLocaleString()}</td>
                                <td class="px-2 py-2 text-[11px] text-right text-slate-700 font-bold font-mono">₦${parseFloat(b.amount || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}</td>
                            </tr>
                        `;
                    });

                    // Compensated items sub-rows
                    subItems.forEach(item => {
                        html += `
                            <tr class="hover:bg-blue-50/10 border-b border-slate-50/50 bg-slate-50/20">
                                <td class="px-2 py-1.5 text-[9px] text-indigo-400 italic font-medium pl-4">• ${item.name}</td>
                                <td class="px-2 py-1.5 text-[9px] text-center text-slate-400 font-mono">1</td>
                                <td class="px-2 py-1.5 text-[9px] text-center text-slate-400 font-mono">Allow</td>
                                <td class="px-2 py-1.5 text-[9px] text-right text-slate-400 font-mono">₦${parseFloat(item.amount || 0).toLocaleString()}</td>
                                <td class="px-2 py-1.5 text-[9px] text-right text-indigo-500/70 font-bold font-mono">₦${parseFloat(item.amount || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}</td>
                            </tr>
                        `;
                    });

                    // Record total row
                    if (dataRows > 1) {
                        html += `
                            <tr class="bg-slate-50/30">
                                <td colspan="4" class="px-2 py-1.5 text-[9px] text-right font-bold text-slate-400 uppercase tracking-tighter">Record Total:</td>
                                <td class="px-2 py-1.5 text-[10px] text-right font-black text-slate-700 border-t border-slate-200 font-mono">₦${this.computeRecordTotal(record).toLocaleString(undefined, { minimumFractionDigits: 2 })}</td>
                            </tr>
                        `;
                    }
                });

                // 3. Sub-Project Group Footer (Total)
                html += `
                    <tr class="bg-indigo-50/30 border-b-2 border-indigo-100">
                        <td colspan="8" class="px-4 py-4 text-right">
                            <span class="text-[10px] font-black text-indigo-900 uppercase tracking-widest">Total for ${subGroup.name}:</span>
                        </td>
                        <td class="px-2 py-4">
                            <div class="inline-flex items-center gap-2 bg-white px-3 py-1.5 rounded-xl border border-teal-200 shadow-sm">
                                <span class="text-xs font-black text-teal-700">₦${subGroup.total.toLocaleString(undefined, { minimumFractionDigits: 2 })}</span>
                            </div>
                        </td>
                        <td></td>
                    </tr>
                `;
            });

            // 4. Final Project Sub Total
            this.currentProjectSubTotal = projectGrandTotal;
            const projectPercent = project ? parseFloat(project.apply_percentage) : NaN;
            this.currentProjectApplyPercentage = isNaN(projectPercent) ? 0 : projectPercent;

            // Reset Batch Workflow Buttons
            $('#generate-batch-btn').removeClass('hidden');
            $('.print-batch-btn').addClass('hidden').removeData('apply-percentage');

            html += `
                <tr class="bg-white border-t-2 border-slate-200">
                    <td colspan="8" class="px-4 py-6 text-right">
                        <span class="text-xs font-black text-slate-800 uppercase tracking-widest">Project Sub Total</span>
                    </td>
                    <td class="px-2 py-6">
                        <div class="inline-flex items-center gap-2 bg-emerald-50 px-4 py-2 rounded-2xl border border-emerald-200 shadow-sm">
                            <span class="text-sm font-black text-emerald-700">₦${projectGrandTotal.toLocaleString(undefined, { minimumFractionDigits: 2 })}</span>
                        </div>
                    </td>
                    <td></td>
                </tr>
            `;

            $('#project-records-body').html(html);
            if (window.lucide) window.lucide.createIcons();

            console.log('VFC: Displaying modal elements...');

            // 1. Move the modal to body to break out of any stacking context traps
            const $modal = $('#records-modal');
            if ($modal.parent().is('body') === false) {
                console.log('VFC: Moving modal to body tag...');
                $('body').append($modal);
            }

            const $overlay = $('#records-modal-overlay');
            const $container = $('#records-modal-container');

            console.log('VFC: Modal element check after move:', {
                modal: $('#records-modal').length,
                overlay: $('#records-modal-overlay').length,
                container: $('#records-modal-container').length
            });

            // 2. Force visibility via CSS with !important where possible
            $modal.attr('style', 'display: flex !important; position: fixed !important; inset: 0 !important; z-index: 100 !important; background: rgba(0,0,0,0.5) !important;');
            $modal.removeClass('hidden');
            if (window.lucide) window.lucide.createIcons();

            setTimeout(() => {
                $overlay.attr('style', 'opacity: 1 !important; visibility: visible !important;');
                $container.attr('style', 'opacity: 1 !important; transform: scale(1) !important; visibility: visible !important; display: flex !important; background: white !important;');

                $overlay.addClass('opacity-100');
                $container.addClass('scale-100 opacity-100').removeClass('scale-95 opacity-0');

                console.log('VFC: Modal forced visible at body root.');
            }, 50);
        } catch (e) {
            console.error('VFC: Failed to render records modal', e);
            Swal.fire('Error', 'Failed to render the records modal. Check console for details.', 'error');
        }
    },

    openApplyPercentModal: function () {
        const self = this;
        $('#modal-sub-total').text('₦' + self.currentProjectSubTotal.toLocaleString(undefined, { minimumFractionDigits: 2 }));
        $('#apply-percentage-input').val(self.currentProjectApplyPercentage);
        this.updateFinalTotalInModal();

        const $modal = $('#apply-percent-modal');

        // Reset Buttons in Modal
        $('#confirm-apply-percent').removeClass('hidden');
        $('.print-batch-btn').addClass('hidden');

        // Move to body if not already there
        if ($modal.parent().is('body') === false) {
            $('body').append($modal);
        }

        const $overlay = $('#apply-percent-overlay');
        const $container = $('#apply-percent-container');

        $modal.removeClass('hidden').addClass('flex');
        $modal.attr('style', 'display: flex !important; z-index: 99999 !important;');

        setTimeout(() => {
            $overlay.addClass('opacity-100');
            $container.addClass('scale-100 opacity-100').removeClass('scale-95 opacity-0');
            if (window.lucide) window.lucide.createIcons();
        }, 50);
    },

    closeApplyPercentModal: function () {
        const $modal = $('#apply-percent-modal');
        const $overlay = $('#apply-percent-overlay');
        const $container = $('#apply-percent-container');

        $overlay.removeClass('opacity-100');
        $container.addClass('scale-95 opacity-0').removeClass('scale-100 opacity-100');
        setTimeout(() => {
            $modal.addClass('hidden').removeClass('flex').attr('style', 'display: none !important;');
        }, 300);
    },

    updateFinalTotalInModal: function () {
        const percent = parseFloat($('#apply-percentage-input').val()) || 0;
        const appliedAmount = (this.currentProjectSubTotal * percent) / 100;
        const finalTotal = this.currentProjectSubTotal + appliedAmount;
        $('#modal-final-total').text('₦' + finalTotal.toLocaleString(undefined, { minimumFractionDigits: 2 }));
    },

    closeRecordsModal: function () {
        console.log('VFC: Closing records modal...');
        const $modal = $('#records-modal');
        const $overlay = $('#records-modal-overlay');
        const $container = $('#records-modal-container');

        $overlay.removeClass('opacity-100');
        $container.addClass('scale-95 opacity-0').removeClass('scale-100 opacity-100');

        setTimeout(() => {
            $modal.addClass('hidden').removeClass('flex').css('display', 'none');
            // Ensure no lingering styles block clicks
            $overlay.css('display', 'none');
        }, 300);
    },

    closeModal: function () {
        console.log('VFC: Closing data entry modal...');
        const $modal = $('#valuation-modal');
        const $overlay = $('#modal-overlay');

        $overlay.removeClass('opacity-100');

        // Hide immediately to prevent click blocking, but keep a tiny delay if we want transitions
        // For robustness, we hide the main container immediately
        $modal.addClass('hidden').removeClass('flex').css('display', 'none');

        // Ensure overlay is also not blocking
        $overlay.css('display', 'none');
    },

    showItemsDetails: function (owner, items) {
        Swal.fire({
            title: `<div class="text-slate-800 font-black text-xl uppercase tracking-tight">Compensated Items</div>`,
            html: `
                <div class="mt-4 text-left">
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-4">Owner: ${owner}</p>
                    <div class="bg-slate-50 rounded-2xl p-6 border border-slate-100 shadow-inner">
                        <div class="space-y-3">
                            ${items.split(',').map(item => `
                                <div class="flex items-start gap-3">
                                    <div class="mt-1 h-1.5 w-1.5 rounded-full bg-blue-500 shrink-0 shadow-[0_0_8px_rgba(59,130,246,0.5)]"></div>
                                    <span class="text-sm font-bold text-slate-700 leading-tight">${item.trim()}</span>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                </div>
            `,
            confirmButtonText: 'Understood',
            confirmButtonColor: '#3b82f6',
            customClass: {
                popup: 'rounded-[2.5rem] border-none p-8',
                confirmButton: 'rounded-2xl px-10 py-4 font-bold uppercase tracking-widest text-xs shadow-lg shadow-blue-200 transition-all hover:scale-105 active:scale-95'
            },
            showClass: {
                popup: 'animate__animated animate__zoomIn animate__faster'
            },
            hideClass: {
                popup: 'animate__animated animate__zoomOut animate__faster'
            }
        });
    },

    // Batch Data Entry Logic
    batchRecords: [],

    addToBatch: function () {
        console.log('VFC: Adding current form to batch...');
        // 1. Validate form fields
        const form = $('#valuation-form')[0];
        if (!form.reportValidity()) {
            console.warn('VFC: Form validation failed.');
            return;
        }

        // 2. Gather form data
        const formData = {};
        $('#valuation-form').serializeArray().forEach(item => {
            formData[item.name] = item.value;
        });

        // Handle 'Other' building type
        if ($('#building_type').val() === 'Other') {
            formData.building_type = $('#building_type_other').val();
        }

        console.log('VFC: Staging record:', formData.owner_name);

        // 3. Add to batch array
        this.batchRecords.push(formData);

        // 4. Reset specific fields but keep project/worker/location context
        $('#owner_name').val('');
        $('#plot_no').val('');
        $('#building_count').val(1);
        this.syncBuildingTypes();
        $('#length, #breadth, #area_covered, #rate_of_cost, #compensation_amount').val('');
        $('#account_name, #account_number, #phone_number, #nin, #remarks').val('');

        // Reset compensated items
        $('.item-checkbox').prop('checked', false);
        $('.item-amount-wrapper').addClass('hidden');
        $('.item-amount-input, .sub-length, .sub-width, .sub-height, .sub-rate, .sub-length-formula, .sub-volume-val').val('');
        $('#compensated_items_val').val('');
        $('#compensated_items_other').addClass('hidden').val('');

        // 5. Update UI
        this.renderBatchTable();

        Swal.fire({
            title: 'Added to Batch',
            text: 'Record has been staged. You can add more or save all staged records.',
            icon: 'success',
            toast: true,
            position: 'top-end',
            timer: 3000,
            showConfirmButton: false
        });

        // Removing scrollIntoView to prevent layout shift in fixed modals
        // $('#batch-list-section')[0].scrollIntoView({ behavior: 'smooth' });
    },

    renderBatchTable: function () {
        const $tableBody = $('#batch-table-body');
        const $section = $('#batch-list-section');
        const $count = $('#batch-count');
        const $total = $('#batch-total-amount');
        const $saveBtn = $('#save-batch-btn');
        const $submitBtn = $('#submit-btn');

        if (this.batchRecords.length === 0) {
            $section.addClass('hidden');
            $saveBtn.addClass('hidden');
            $submitBtn.removeClass('hidden');
            return;
        }

        $section.removeClass('hidden');
        $saveBtn.removeClass('hidden');
        $submitBtn.addClass('hidden'); // Hide single save button when batching
        $count.text(this.batchRecords.length);

        let html = '';
        let grandTotal = 0;

        this.batchRecords.forEach((record, index) => {
            const amount = parseFloat(record.compensation_amount) || 0;
            grandTotal += amount;
            html += `
                <tr class="hover:bg-slate-50/50 transition">
                    <td class="px-6 py-4 font-black text-slate-900 uppercase tracking-tight">${record.owner_name}</td>
                    <td class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">${record.building_type}</td>
                    <td class="px-6 py-4 text-right font-black text-slate-900 font-mono text-sm">₦${amount.toLocaleString(undefined, { minimumFractionDigits: 2 })}</td>
                    <td class="px-6 py-4 text-center">
                        <button type="button" onclick="VFC.removeFromBatch(${index})" class="p-2 text-slate-300 hover:text-red-600 hover:bg-red-50 rounded-xl transition-all hover:scale-110 active:scale-90">
                            <i data-lucide="trash-2" class="h-4 w-4"></i>
                        </button>
                    </td>
                </tr>
            `;
        });

        $tableBody.html(html);
        $total.text('₦' + grandTotal.toLocaleString(undefined, { minimumFractionDigits: 2 }));
        if (window.lucide) window.lucide.createIcons();
    },

    removeFromBatch: function (index) {
        this.batchRecords.splice(index, 1);
        this.renderBatchTable();
    },

    clearBatch: function () {
        this.batchRecords = [];
        this.renderBatchTable();
    },

    saveAllBatch: function () {
        const self = this;
        if (this.batchRecords.length === 0) return;

        Swal.fire({
            title: 'Save All Staged Records?',
            text: `You are about to commit ${this.batchRecords.length} records to the database. This will save them all in a single batch.`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#059669',
            confirmButtonText: 'Yes, Save All Staged',
            cancelButtonText: 'Review More',
            customClass: {
                popup: 'rounded-[2rem]',
                confirmButton: 'rounded-xl px-6 py-3 font-bold',
                cancelButton: 'rounded-xl px-6 py-3 font-bold'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Saving Batch...',
                    html: '<div class="mt-2 text-slate-500">Writing records to database, please wait...</div>',
                    allowOutsideClick: false,
                    didOpen: () => { Swal.showLoading(); }
                });

                $.ajax({
                    url: self.config.routes.batchStore,
                    type: 'POST',
                    data: {
                        _token: self.config.csrf,
                        records: self.batchRecords
                    },
                    success: function (response) {
                        if (response.success) {
                            Swal.fire({
                                title: 'Batch Saved!',
                                text: response.message,
                                icon: 'success',
                                confirmButtonText: 'Great'
                            }).then(() => {
                                self.batchRecords = [];
                                window.location.reload();
                            });
                        }
                    },
                    error: function (xhr) {
                        Swal.fire('Batch Save Failed', xhr.responseJSON?.message || 'Something went wrong during bulk storage.', 'error');
                    }
                });
            }
        });
    },

    updateFinalBuildingType: function () {
        const types = [];
        const stages = [];
        const floors = [];
        $('.building-type-row').each(function () {
            let val = $(this).find('.building-type-select').val();
            const other = $(this).find('.building-type-other').val();
            if (val === 'Other' || val === 'Others') {
                val = other || val;
            }
            if (val) types.push(val);

            let sVal = $(this).find('.building-stage-select').val();
            const sOther = $(this).find('.building-stage-other').val();
            if (sVal === 'Other' || sVal === 'Others') {
                sVal = sOther || sVal;
            }
            if (sVal) stages.push(sVal);

            const fVal = $(this).find('.building-floors').val();
            if (fVal) floors.push(fVal);
        });
        $('#building_type_final').val(types.join(', '));
        $('#completion_stage_final').val(stages.join(', '));
        $('#number_of_floors_final').val(floors.join(', '));
    },

    syncBuildingTypes: function () {
        const count = parseInt($('#building_count').val()) || 1;
        const $container = $('#building_types_container');
        const $rows = $container.find('.building-type-row');
        const currentCount = $rows.length;

        if (count > currentCount) {
            // Add rows
            const $template = $rows.first().clone();
            $template.find('select').val('');
            $template.find('.building-type-other, .building-stage-other').val('').addClass('hidden');
            $template.find('.building-length, .building-breadth, .building-area, .building-rate, .building-comp, .building-floors').val('');
            for (let i = 0; i < count - currentCount; i++) {
                $container.append($template.clone());
            }
        } else if (count < currentCount) {
            // Remove rows
            for (let i = 0; i < currentCount - count; i++) {
                $container.find('.building-type-row').last().remove();
            }
        }

        // Update Building Numbers
        $container.find('.building-type-row').each(function (idx) {
            const $label = $(this).find('div').first();
            if ($label.length) {
                $label.html(`<i data-lucide="building" class="h-3 w-3"></i> Building ${idx + 1}`);
            }
        });

        if (window.lucide) window.lucide.createIcons();
        this.updateFinalBuildingType();
        this.calculateCompensation();
    }
};

$(document).ready(function () {
    if (window.VFC) {
        window.VFC.init();
    }
});
