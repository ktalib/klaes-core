// Initialize global variables only if they don't exist
// and provide safe local references to avoid ReferenceErrors
var currentDropdown = window.currentDropdown || null;
var currentButton = window.currentButton || null;
var currentAppData = window.currentAppData || null;
var currentInstrumentContext = window.currentInstrumentContext || null;

var instrumentLookup = (() => {
    if (typeof window.instrumentLookup === 'undefined') {
        window.instrumentLookup = new Map();
    }
    return window.instrumentLookup;
})();

var appData = (() => {
    if (typeof window.appData === 'undefined') {
        window.appData = {};
    }
    return window.appData;
})();

var serverCofoData = (() => {
    // Attempt to get from window, fallback to empty array if missing
    return window.serverCofoData || [];
})();

function resolveInstrumentContext(identifier) {
    const key = String(identifier);
    let record = instrumentLookup.get(key) || appData[key] || null;

    if (!record) {
        record = serverCofoData.find(item => {
            if (String(item.id) === key) {
                return true;
            }
            if (item.registered_instrument_id !== null && item.registered_instrument_id !== undefined) {
                return String(item.registered_instrument_id) === key;
            }
            return false;
        }) || null;

        if (record) {
            instrumentLookup.set(String(record.id), record);
            if (record.registered_instrument_id !== null && record.registered_instrument_id !== undefined) {
                instrumentLookup.set(String(record.registered_instrument_id), record);
            }
        }
    }

    const registeredId = record && record.registered_instrument_id !== null && record.registered_instrument_id !== undefined
        ? String(record.registered_instrument_id)
        : null;

    return { instrument: record, registeredId };
}

function requireRegisteredInstrument(identifier, actionLabel) {
    const context = resolveInstrumentContext(identifier);

    if (!context.instrument) {
        Swal.fire({
            icon: 'error',
            title: 'Instrument Not Found',
            text: 'Unable to locate instrument details for this action. Please refresh and try again.'
        });
        return null;
    }

    if (!context.registeredId) {
        Swal.fire({
            icon: 'info',
            title: actionLabel || 'Action Unavailable',
            text: 'This action requires a registered instrument record, but none was found.'
        });
        return null;
    }

    return context;
}

async function ensureRdsState(context) {
    if (!context || !context.instrument) {
        return;
    }

    const instrument = context.instrument;

    if (instrument.status !== 'registered' || !context.registeredId) {
        instrument.rds_exists = false;
        instrument.rds_data = null;
        return;
    }

    if (instrument._rdsStatusPending) {
        return;
    }

    const lastChecked = instrument._rdsStatusCheckedAt || 0;
    const thirtySeconds = 30 * 1000;
    if (Date.now() - lastChecked < thirtySeconds && typeof instrument.rds_exists !== 'undefined') {
        return;
    }

    instrument._rdsStatusPending = true;

    try {
        const response = await fetch(`${window.KlaesConfig.urls.rdsStatus}/${context.registeredId}`);
        const data = await response.json();

        if (data && data.success) {
            instrument.rds_exists = !!data.exists;
            instrument.rds_data = data.rds || null;
        } else {
            instrument.rds_exists = false;
            instrument.rds_data = null;
        }
    } catch (error) {
        console.error('Error fetching RDS status:', error);
        instrument.rds_exists = false;
        instrument.rds_data = null;
    } finally {
        instrument._rdsStatusPending = false;
        instrument._rdsStatusCheckedAt = Date.now();
    }
} 


// Positioning helper function
async function positionDropdown(button, dropdown) {
    const floatingUI = window.FloatingUIDOM || window.FloatingUI;
    if (!floatingUI) {
        console.warn('Floating UI not found, using fallback positioning');
        const rect = button.getBoundingClientRect();
        const dropdownWidth = 200;
        const viewportWidth = window.innerWidth;
        let left = rect.right - dropdownWidth;
        if (left < 8) left = 8;
        if (left + dropdownWidth > viewportWidth - 8) {
            left = viewportWidth - dropdownWidth - 8;
        }
        Object.assign(dropdown.style, {
            left: `${left}px`,
            top: `${rect.bottom + 4}px`,
        });
        return;
    }

    const isMobile = window.innerWidth < 640;
    const placement = isMobile ? 'bottom' : 'bottom-end';

    const { x, y } = await floatingUI.computePosition(button, dropdown, {
        placement: placement,
        middleware: [
            floatingUI.offset(4),
            floatingUI.flip({
                fallbackPlacements: ['top', 'bottom', 'left', 'right'],
            }),
            floatingUI.shift({
                padding: isMobile ? 16 : 8,
                boundary: document.body
            }),
            floatingUI.size({
                apply({ availableWidth, availableHeight, elements }) {
                    Object.assign(elements.floating.style, {
                        maxWidth: `${Math.min(availableWidth - 16, 280)}px`,
                        maxHeight: `${Math.min(availableHeight - 16, window.innerHeight * 0.9)}px`,
                    });
                },
            })
        ],
    });

    Object.assign(dropdown.style, {
        left: `${x}px`,
        top: `${y}px`,
    });
}

async function toggleDropdown(button, appId) {
    console.log('toggleDropdown called for appId:', appId);
    const dropdown = document.getElementById('dropdown-menu');
    if (!dropdown) {
        console.error('Dropdown menu element not found');
        return;
    }

    // If clicking the same button, close dropdown
    if (currentButton === button && !dropdown.classList.contains('hidden')) {
        closeDropdown();
        return;
    }

    // Close any existing dropdown
    closeDropdown();

    // Set current references
    currentButton = button;
    currentDropdown = dropdown;

    const context = resolveInstrumentContext(appId);
    if (!context || !context.instrument) {
        console.error('Unable to resolve context for appId:', appId);
        return;
    }

    currentAppData = context.instrument;
    if (currentAppData && appId) {
        appData[appId] = currentAppData;
    }
    currentInstrumentContext = context;

    // 1. Show immediate loading state or partial content
    populateDropdownContent(currentAppData, context);
    
    // We remove hidden first so it can be measured correctly
    dropdown.classList.remove('hidden');
    
    // Position it
    await positionDropdown(button, dropdown);

    // Show backdrop on mobile
    const backdrop = document.getElementById('dropdown-backdrop');
    if (window.innerWidth < 640 && backdrop) {
        backdrop.classList.remove('hidden');
    }

    // 2. Refresh RDS state in background if needed
    if (currentAppData.status === 'registered') {
        const needsRefresh = typeof currentAppData.rds_exists === 'undefined' || 
                             (Date.now() - (currentAppData._rdsStatusCheckedAt || 0) > 30000);
        
        if (needsRefresh) {
            // Keep the dropdown open, but maybe show a subtle loading indicator 
            // on the RDS items if we really wanted to. 
            // For now, let's just update as it finishes.
            try {
                await ensureRdsState(context);
                // Update content with new RDS state
                if (currentDropdown === dropdown && currentButton === button) {
                    populateDropdownContent(currentAppData, context);
                    await positionDropdown(button, dropdown);
                }
            } catch (error) {
                console.error('Background RDS state check failed:', error);
            }
        }
    }
}


function populateDropdownContent(app, context = null) {
    const dropdown = document.getElementById('dropdown-menu');
    if (!dropdown) {
        return;
    }

    const editClass = app.status === 'pending' ? 'text-gray-700 hover:bg-gray-100' : 'text-gray-400 cursor-not-allowed';
    const editIcon = app.status === 'pending' ? 'text-blue-500' : 'text-gray-300';
    const editHref = app.status === 'pending' ? `${window.KlaesConfig.urls.base}/${app.id}/edit` : '#';
    const editClick = app.status !== 'pending' ? 'onclick="return false;"' : '';

    // Check if ST Assignment is registered for ST CofO instruments
    const canRegister = checkCanRegisterInstrument(app);
    const registerClass = canRegister ? 'text-gray-700 hover:bg-gray-100' : 'text-gray-400 cursor-not-allowed';
    const registerIcon = canRegister ? 'text-green-500' : 'text-gray-300';
    let registerClick = '';
    if (app.status === 'registered') {
        registerClick = 'onclick="showAlreadyRegisteredMessage(); return false;"';
    } else if (canRegister) {
        registerClick = `onclick="openSingleRegisterModalWithData('${app.id}'); return false;"`;
    } else {
        registerClick = 'onclick="showSTCofoRestrictionMessage(); return false;"';
    }

    const deleteClass = app.status === 'pending' ? 'text-red-600 hover:bg-gray-100' : 'text-gray-400 cursor-not-allowed';
    const deleteIcon = app.status === 'pending' ? '' : 'text-gray-300';
    const deleteClick = app.status === 'pending' ? `onclick="deleteInstrument('${app.id}'); return false;"` : 'onclick="return false;"';

    // RDS logic - implement workflow control based on instrument type
    let generateRdsContent = '';
    let viewRdsContent = '';

    console.log(`Building dropdown for instrument ${app.id}: status=${app.status}, STM_Ref=${app.STM_Ref}, type=${app.instrument_type}`);

    // ST Fragmentation - RDS is DISABLED for this instrument type
    if (app.instrument_type === 'ST Fragmentation') {
        // ST Fragmentation never generates RDS
        generateRdsContent = '';
        viewRdsContent = '';
    }
    // ST CofO - Use standard registered logic instead of strict dependency
    // else if (app.instrument_type === 'Sectional Titling CofO') { ... }

    // ST Assignment, ST CofO and other instruments - standard RDS logic
    else if (app.status === 'registered') {
        const hasRds = !!app.rds_exists;

        if (hasRds) {
            generateRdsContent = `<a href="#" onclick="return false;" class="dropdown-item text-gray-400 cursor-not-allowed">
                                    <i class="fas fa-file-alt w-4 h-4 text-gray-300"></i>
                                    <span>Generate RDS</span>
                                    </a>`;

            viewRdsContent = `<a href="#" onclick="viewRDS('${app.id}'); return false;" class="dropdown-item">
                                <i class="fas fa-eye w-4 h-4 text-indigo-500"></i>
                                <span>View RDS</span>
                                </a>`;
        } else {
            generateRdsContent = `<a href="#" onclick="showGenerateRDSModal('${app.id}'); return false;" class="dropdown-item">
                                    <i class="fas fa-file-alt w-4 h-4 text-purple-500"></i>
                                    <span>Generate RDS</span>
                                    </a>`;

            viewRdsContent = `<a href="#" onclick="return false;" class="dropdown-item text-gray-400 cursor-not-allowed">
                                <i class="fas fa-eye w-4 h-4 text-gray-300"></i>
                                <span>View RDS</span>
                                </a>`;
        }
    } else {
        // Not registered - disable both
        generateRdsContent = `<a href="#" onclick="return false;" class="dropdown-item text-gray-400 cursor-not-allowed">
                                <i class="fas fa-file-alt w-4 h-4 text-gray-300"></i>
                                <span>Generate RDS</span>
                                </a>`;

        viewRdsContent = `<a href="#" onclick="return false;" class="dropdown-item text-gray-400 cursor-not-allowed">
                            <i class="fas fa-eye w-4 h-4 text-gray-300"></i>
                            <span>View RDS</span>
                            </a>`;
    }

    // CoR logic - enabled only after RDS is generated
    let generateCorContent = '';
    let viewCorContent = '';

    if (app.status === 'registered' && app.instrument_type !== 'ST Fragmentation') {
        // CoR is only available after RDS is generated
        if (!!app.rds_exists) {
            // RDS exists - CoR can be generated
            if (!!app.cor_exists) {
                // CoR already exists - disable generate, enable view
                generateCorContent = `<a href="#" onclick="return false;" class="dropdown-item text-gray-400 cursor-not-allowed">
                                        <i class="fas fa-certificate w-4 h-4 text-gray-300"></i>
                                        <span>Generate CoR</span>
                                    </a>`;

                // Use STM_Ref for CoR URL if available, otherwise use ID (logic might need adjustment if CoR Controller requires STM_Ref)
                // Assuming CoR index can handle ID or we disable View CoR if no STM_Ref for now (safest path if CoR relies on STM_Ref)
                // For generic instruments without STM_Ref, we might need a different View URL or rely on ID.
                // Reverting to STM_Ref check for View URL construction to stay safe, but enabling actions.
                // Actually, for generic instruments, we might not have a CoR view implemented yet if it strictly requires STM_Ref.
                // But the request is to ENABLE actions.

                const viewCorId = context.registeredId || app.id;
                const viewCorUrl = app.STM_Ref
                    ? `${window.KlaesConfig.urls.corIndex}?STM_Ref=${app.STM_Ref}`
                    : `${window.KlaesConfig.urls.corIndex}?id=${viewCorId}`;

                viewCorContent = `<a href="${viewCorUrl}" class="dropdown-item">
                                    <i class="fas fa-eye w-4 h-4 text-blue-500"></i>
                                    <span>View CoR</span>
                                </a>`;
            } else {
                // RDS exists but CoR not generated yet - enable generate, disable view
                generateCorContent = `<a href="#" onclick="showGenerateCoRModal('${app.id}'); return false;" class="dropdown-item">
                                        <i class="fas fa-certificate w-4 h-4 text-orange-500"></i>
                                        <span>Generate CoR</span>
                                    </a>`;

                viewCorContent = `<a href="#" onclick="return false;" class="dropdown-item text-gray-400 cursor-not-allowed">
                                    <i class="fas fa-eye w-4 h-4 text-gray-300"></i>
                                    <span>View CoR</span>
                                </a>`;
            }
        } else {
            // RDS not generated yet - disable CoR generation
            generateCorContent = `<a href="#" onclick="showCoRDependsOnRDSMessage(); return false;" class="dropdown-item text-gray-400 cursor-not-allowed">
                                    <i class="fas fa-certificate w-4 h-4 text-gray-300"></i>
                                    <span>Generate CoR</span>
                                </a>`;

            viewCorContent = `<a href="#" onclick="return false;" class="dropdown-item text-gray-400 cursor-not-allowed">
                                <i class="fas fa-eye w-4 h-4 text-gray-300"></i>
                                <span>View CoR</span>
                                </a>`;
        }
    } else {
        // Not registered (disabled) or ST Fragmentation (hidden)
        if (app.instrument_type !== 'ST Fragmentation') {
            generateCorContent = `<a href="#" onclick="return false;" class="dropdown-item text-gray-400 cursor-not-allowed">
                                    <i class="fas fa-certificate w-4 h-4 text-gray-300"></i>
                                    <span>Generate CoR</span>
                                </a>`;

            viewCorContent = `<a href="#" onclick="return false;" class="dropdown-item text-gray-400 cursor-not-allowed">
                                <i class="fas fa-eye w-4 h-4 text-gray-300"></i>
                                <span>View CoR</span>
                                </a>`;
        } else {
            generateCorContent = '';
            viewCorContent = '';
        }
    }

    const tlPropId = String(app.prop_id || '').replace(/"/g, '');
    const tlFileNo = String(app.fileno || '').replace(/"/g, '');
    const timelineContent = tlFileNo
        ? `<a href="#" class="dropdown-item text-gray-700 hover:bg-gray-100 instr-timeline-item" data-prop-id="${tlPropId}" data-file-number="${tlFileNo}">
            <i class="fas fa-history w-4 h-4 text-indigo-500"></i>
            <span>View Timeline</span>
        </a>`
        : '';

    dropdown.innerHTML = `
        <a href="${editHref}" ${editClick} class="dropdown-item ${editClass}">
            <i class="fas fa-edit w-4 h-4 ${editIcon}"></i>
            <span>Edit Record</span>
        </a>
        <a href="#" ${registerClick} class="dropdown-item ${registerClass}">
            <i class="fas fa-file-signature w-4 h-4 ${registerIcon}"></i>
            <span>Register Instrument</span>
        </a>
        ${generateRdsContent}
        ${viewRdsContent}
        ${generateCorContent}
        ${viewCorContent}
        ${timelineContent}
        <a href="#" ${deleteClick} class="dropdown-item ${deleteClass}">
            <i class="fas fa-trash w-4 h-4 ${deleteIcon}"></i>
            <span>Delete Record</span>
        </a>
    `;
}

function closeDropdown() {
    const dropdown = document.getElementById('dropdown-menu');
    const backdrop = document.getElementById('dropdown-backdrop');

    if (dropdown) {
        dropdown.classList.add('hidden');
    }
    if (backdrop) {
        backdrop.classList.add('hidden');
    }

    currentDropdown = null;
    currentButton = null;
    currentAppData = null;
    currentInstrumentContext = null;
}

function deleteInstrument(id) {
    // Extract numeric ID if it contains underscore (e.g., "2_st_fragmentation" -> "2")
    const numericId = String(id).split('_')[0];

    Swal.fire({
        title: 'Are you sure?',
        text: "You won't be able to revert this!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            // Send DELETE request
            fetch(`${window.KlaesConfig.urls.delete}/${numericId}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': window.KlaesConfig.csrfToken,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                }
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire(
                            'Deleted!',
                            data.message,
                            'success'
                        ).then(() => {
                            // Reload the page to refresh the table
                            location.reload();
                        });
                    } else {
                        Swal.fire(
                            'Error!',
                            data.error || 'Failed to delete instrument',
                            'error'
                        );
                    }
                })
                .catch(error => {
                    // Error occurred while deleting the instrument
                    Swal.fire(
                        'Error!',
                        'An error occurred while deleting the instrument',
                        'error'
                    );
                });
        }
    });
}

// Function to generate RDS (Registered Document Sheet)
function generateRDS(identifier) {
    const context = requireRegisteredInstrument(identifier, 'RDS generation is available only after registration.');
    if (!context) {
        return;
    }

    const { instrument, registeredId } = context;
    const stmRef = instrument.STM_Ref || '';

    Swal.fire({
        title: 'Generating RDS',
        text: 'Please wait while we generate the Registered Document Sheet...',
        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    fetch(`${window.KlaesConfig.urls.generateRds}/${registeredId}`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': window.KlaesConfig.csrfToken,
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },
        body: JSON.stringify({
            stm_ref: stmRef
        })
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'RDS Generated',
                    text: data.message || 'Registered Document Sheet has been generated successfully',
                    confirmButtonText: 'View RDS'
                }).then((result) => {
                    // Update the local instrument state
                    instrument.rds_exists = true;
                    if (data.rds_data) {
                        instrument.rds_data = data.rds_data;
                    }

                    // Dynamically update the UI buttons without full page refresh
                    const rdsActions = document.getElementById(`rds-actions-${instrument.id}`);
                    const corActions = document.getElementById(`cor-actions-${instrument.id}`);

                    if (rdsActions) {
                        rdsActions.innerHTML = `
                            <span class="flex items-center gap-2 px-4 py-2 text-sm text-gray-400 cursor-not-allowed">
                                <i data-lucide="file-text" class="w-4 h-4"></i> Generate RDS
                                <i data-lucide="check-circle" class="w-3 h-3 text-green-500"></i>
                            </span>
                            <a href="#" id="view-rds-${instrument.id}" onclick="viewRDS('${instrument.id}'); return false;" 
                               class="flex items-center gap-2 px-4 py-2 text-sm text-blue-600 hover:bg-blue-50 transition">
                                <i data-lucide="eye" class="w-4 h-4"></i> View RDS
                            </a>
                        `;
                    }

                    if (corActions) {
                        corActions.innerHTML = `
                            <a href="#" id="gen-cor-${instrument.id}" onclick="showGenerateCoRModal('${instrument.id}'); return false;" 
                               class="flex items-center gap-2 px-4 py-2 text-sm text-green-600 hover:bg-green-50 transition">
                                <i data-lucide="check-square" class="w-4 h-4"></i> Generate CoR
                            </a>
                            <span class="flex items-center gap-2 px-4 py-2 text-sm text-gray-400 cursor-not-allowed">
                                <i data-lucide="file-check" class="w-4 h-4"></i> View CoR
                                <i data-lucide="x-circle" class="w-3 h-3 text-red-400"></i>
                            </span>
                        `;
                    }

                    // Refresh Lucide icons if applicable
                    if (window.lucide) {
                        window.lucide.createIcons();
                    }

                    if (result.isConfirmed && data.rds_url) {
                        const newWindow = window.open(data.rds_url, '_blank');
                        if (!newWindow || newWindow.closed || typeof newWindow.closed === 'undefined') {
                            window.location.href = data.rds_url;
                        }
                    }
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Generation Failed',
                    text: data.error || 'Failed to generate RDS'
                });
            }
        })
        .catch(() => {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'An error occurred while generating the RDS'
            });
        });
}

// Function to view RDS (Registered Document Sheet)
function viewRDS(identifier) {
    const context = requireRegisteredInstrument(identifier, 'RDS preview is available only after registration.');
    if (!context) {
        return;
    }

    const { registeredId } = context;

    Swal.fire({
        title: 'Loading RDS',
        text: 'Please wait...',
        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    fetch(`${window.KlaesConfig.urls.viewRds}/${registeredId}`, {
        method: 'GET',
        headers: {
            'X-CSRF-TOKEN': window.KlaesConfig.csrfToken,
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        }
    })
        .then(response => response.json())
        .then(data => {
            Swal.close();
            if (data.success && data.rds_url) {
                const newWindow = window.open(data.rds_url, '_blank');
                if (!newWindow || newWindow.closed || typeof newWindow.closed === 'undefined') {
                    Swal.fire({
                        icon: 'info',
                        title: 'Open RDS',
                        html: 'Please click the button below to view the RDS document:<br><br>' +
                            '<a href="' + data.rds_url + '" target="_blank" class="inline-block px-6 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">Open RDS</a>',
                        confirmButtonText: 'OK',
                        allowOutsideClick: true
                    });
                }
            } else {
                Swal.fire({
                    icon: 'warning',
                    title: 'RDS Not Available',
                    text: data.message || 'RDS has not been generated yet. Would you like to generate it now?',
                    showCancelButton: true,
                    confirmButtonText: 'Generate Now',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        generateRDS(identifier);
                    }
                });
            }
        })
        .catch(() => {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'An error occurred while loading the RDS'
            });
        });
}

// Function to show RDS generation confirmation modal
function showGenerateRDSModal(identifier, existingContext = null) {
    const context = existingContext || requireRegisteredInstrument(identifier, 'RDS generation requires a registered instrument.');
    if (!context) {
        return;
    }

    Swal.fire({
        title: 'Generate RDS',
        text: 'Are you sure you want to generate a Registered Document Sheet (RDS) for this instrument?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, Generate RDS',
        cancelButtonText: 'Cancel',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            generateRDS(identifier);
        }
    });
}

// Function to show restriction message for CoR
function showCoRDependsOnRDSMessage() {
    Swal.fire({
        icon: 'info',
        title: 'RDS Required',
        text: 'A Registered Document Sheet (RDS) must be generated for this instrument before you can generate the Certificate of Registration (CoR).',
        confirmButtonColor: '#3085d6'
    });
}

// Function to show restriction message for ST CofO RDS
function showSTCofoRDSRestrictionMessage() {
    Swal.fire({
        icon: 'info',
        title: 'Prerequisite Required',
        text: 'The RDS for the corresponding ST Assignment (Transfer of Title) must be generated before you can generate the RDS for Sectional Titling CofO.',
        confirmButtonColor: '#3085d6'
    });
}

// Function to show CoR generation confirmation modal
function showGenerateCoRModal(identifier) {
    const context = resolveInstrumentContext(identifier);

    if (!context.instrument) {
        Swal.fire({
            icon: 'error',
            title: 'Instrument Not Found',
            text: 'Unable to locate instrument details for this action.'
        });
        return;
    }

    Swal.fire({
        title: 'Generate CoR',
        text: 'Are you sure you want to generate a Certificate of Registration (CoR) for this instrument?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, Generate CoR',
        cancelButtonText: 'Cancel',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            generateCoR(identifier);
        }
    });
}

// Function to generate CoR
function generateCoR(identifier) {
    const context = resolveInstrumentContext(identifier);

    if (!context.instrument) {
        Swal.fire({
            icon: 'error',
            title: 'Instrument Not Found',
            text: 'Unable to locate instrument details for this action.'
        });
        return;
    }

    const { instrument, registeredId } = context;
    const stmRef = instrument.STM_Ref;

    if (!registeredId) {
        Swal.fire({
            icon: 'info',
            title: 'CoR Unavailable',
            text: 'This action requires a registered instrument record. Please complete registration before generating CoR.'
        });
        return;
    }

    Swal.fire({
        title: 'Generating CoR',
        text: 'Please wait while we update instrument status and generate Certificate of Registration...',
        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    // Step 1: Call the backend to mark it as generated
    fetch(`${window.KlaesConfig.urls.generateCor}/${registeredId}`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': window.KlaesConfig.csrfToken,
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        }
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Step 2: Open the view
                const viewUrl = stmRef
                    ? `${window.KlaesConfig.urls.corIndex}?STM_Ref=${stmRef}`
                    : `${window.KlaesConfig.urls.corIndex}?id=${registeredId}`;

                window.open(viewUrl, '_blank');

                // Step 3: Refresh the local view
                Swal.fire({
                    icon: 'success',
                    title: 'CoR Generated',
                    text: 'The Certificate of Registration has been generated and instrument status updated.',
                    timer: 1500,
                    showConfirmButton: false
                }).then(() => {
                    // Update local state before refresh
                    instrument.cor_exists = true;
                    if (data.instrument_data) {
                        Object.assign(instrument, data.instrument_data);
                    }
                    refreshInstrumentData();
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Generation Failed',
                    text: data.error || 'Failed to update CoR status in database.'
                });
            }
        })
        .catch(error => {
            console.error('CoR generation error:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'An error occurred while connecting to the server for CoR generation.'
            });
        });
}

// Function to view CoR (Certificate of Registration)
function viewCOR(identifier) {
    const context = resolveInstrumentContext(identifier);

    if (!context.instrument) {
        Swal.fire({
            icon: 'error',
            title: 'Instrument Not Found',
            text: 'Unable to locate instrument details for this action.'
        });
        return;
    }

    const { instrument, registeredId } = context;
    const viewUrl = instrument.STM_Ref
        ? `${window.KlaesConfig.urls.corIndex}?STM_Ref=${instrument.STM_Ref}`
        : `${window.KlaesConfig.urls.corIndex}?id=${registeredId || instrument.id}`;

    const newWindow = window.open(viewUrl, '_blank');
    if (!newWindow || newWindow.closed || typeof newWindow.closed === 'undefined') {
        window.location.href = viewUrl;
    }
}

// Function to refresh UI components after data changes
function refreshInstrumentData() {
    console.log('Refreshing instrument data UI...');
    
    // 1. If on /instrument_registration (DataTables mode)
    if (typeof window.updateTable === 'function') {
        window.updateTable();
        if (typeof window.updatePaginationControls === 'function') {
            window.updatePaginationControls();
        }
        console.log('DataTables updated.');
    } 
    // 2. If on /instruments (Blade mode), we already have some manual DOM updates in generateRDS, 
    // but we can also trigger a reload if we haven't implemented full DOM sync for everything.
    // For now, if we see the Blade table IDs, we assume DOM sync was handled or we reload.
    else if (document.getElementById('instrumentsTable')) {
        // If we want to be safe and ensure everything (stats cards etc) is correct:
        location.reload();
    }
    // 3. Fallback
    else {
        location.reload();
    }
}

// Function to check RDS status and handle Generate action
async function checkRDSAndShowGenerate(identifier) {
    console.log(`Checking RDS status for Generate on instrument ${identifier}`);

    const context = requireRegisteredInstrument(identifier, 'RDS generation requires a registered instrument.');
    if (!context) {
        return;
    }

    try {
        const response = await fetch(`${window.KlaesConfig.urls.rdsStatus}/${context.registeredId}`);
        const data = await response.json();

        if (data.success && data.exists) {
            Swal.fire({
                title: 'RDS Already Generated',
                text: 'An RDS document has already been generated for this instrument.',
                icon: 'info',
                showCancelButton: true,
                confirmButtonText: 'View Existing RDS',
                cancelButtonText: 'Close'
            }).then((result) => {
                if (result.isConfirmed) {
                    viewRDS(identifier);
                }
            });
        } else {
            showGenerateRDSModal(identifier, context);
        }
    } catch (error) {
        console.error('Error checking RDS status:', error);
        Swal.fire('Error', 'Failed to check RDS status', 'error');
    }
}

// Function to check RDS status and handle View action
async function checkRDSAndShowView(identifier) {
    console.log(`Checking RDS status for View on instrument ${identifier}`);

    const context = requireRegisteredInstrument(identifier, 'RDS viewing requires a registered instrument.');
    if (!context) {
        return;
    }

    try {
        const response = await fetch(`${window.KlaesConfig.urls.rdsStatus}/${context.registeredId}`);
        const data = await response.json();

        if (data.success && data.exists) {
            viewRDS(identifier);
        } else {
            Swal.fire({
                title: 'No RDS Available',
                text: 'No RDS document has been generated for this instrument yet.',
                icon: 'info',
                showCancelButton: true,
                confirmButtonText: 'Generate RDS Now',
                cancelButtonText: 'Close'
            }).then((result) => {
                if (result.isConfirmed) {
                    showGenerateRDSModal(identifier, context);
                }
            });
        }
    } catch (error) {
        console.error('Error checking RDS status:', error);
        Swal.fire('Error', 'Failed to check RDS status', 'error');
    }
}

// Debug function to manually check RDS status (can be called from console)
window.debugRDSStatus = async function (identifier) {
    console.log(`=== DEBUG RDS STATUS FOR INSTRUMENT ${identifier} ===`);

    const context = resolveInstrumentContext(identifier);
    if (!context.instrument) {
        console.error(`Instrument ${identifier} not found in data set`);
        return;
    }

    const { instrument, registeredId } = context;
    console.log('Instrument data:', instrument);
    console.log('Current RDS status in cache:', instrument.rds_exists);

    if (!registeredId) {
        console.warn('No registered instrument id found. RDS cannot be generated.');
        return;
    }

    try {
        const response = await fetch(`${window.KlaesConfig.urls.rdsStatus}/${registeredId}`);
        const data = await response.json();

        console.log('API Response:', data);

        if (data.success) {
            console.log(`API says RDS exists: ${data.exists}`);
            if (data.exists !== instrument.rds_exists) {
                console.warn(`MISMATCH! Cache says ${instrument.rds_exists}, API says ${data.exists}`);
                instrument.rds_exists = data.exists;
                instrument.rds_data = data.rds || null;
                console.log('Updated cache with API data');
            }
        } else {
            console.error('API returned error:', data);
        }
    } catch (error) {
        console.error('Error calling API:', error);
    }

    console.log('=== END DEBUG ===');
};

// Function to force refresh all RDS statuses (can be called from console)
window.forceRefreshAllRDSStatus = async function () {
    console.log('=== FORCE REFRESH ALL RDS STATUS ===');
    if (serverCofoData && serverCofoData.length > 0) {
        try {
            serverCofoData = await checkRDSStatus(serverCofoData);
            populateInstrumentCaches(serverCofoData);
            console.log('All RDS statuses refreshed successfully');
            serverCofoData.forEach(item => {
                if (item.status === 'registered') {
                    console.log(`Instrument ${item.id}: RDS exists = ${item.rds_exists}`);
                }
            });
        } catch (error) {
            console.error('Error refreshing RDS statuses:', error);
        }
    }
    console.log('=== END FORCE REFRESH ===');
};

// Function to force refresh dropdown for a specific instrument
function refreshDropdownForInstrument(instrumentId) {
    const context = resolveInstrumentContext(instrumentId);
    if (context.instrument) {
        // Find any open dropdown for this instrument and refresh it
        const activeDropdown = document.getElementById('dropdown-menu');
        if (activeDropdown && !activeDropdown.classList.contains('hidden')) {
            // Get the currently active app data from the dropdown's data attribute or similar
            // For now, just close the dropdown so user needs to re-open to see updated state
            closeDropdown();
        }

        console.log(`Refreshed dropdown data for instrument ${instrumentId}`);
    }
}

// Function to check if an instrument can be registered
function checkCanRegisterInstrument(app) {
    // For non-pending instruments, they cannot be registered
    if (app.status !== 'pending') {
        return false;
    }

    // For ST CofO, check if corresponding ST Assignment OR ST Fragmentation is registered
    if (app.instrument_type === 'Sectional Titling CofO') {
        try {
            // Check if there's a registered ST Assignment OR ST Fragmentation for the same file number
            const stAssignmentRegistered = serverCofoData.find(item =>
                item.fileno === app.fileno &&
                (item.instrument_type === 'ST Assignment (Transfer of Title)' || item.instrument_type === 'ST Fragmentation') &&
                item.status === 'registered'
            );

            return !!stAssignmentRegistered;
        } catch (error) {
            // Error checking ST Assignment registration
            return false;
        }
    }

    // For all other instrument types, allow registration if pending
    return true;
}

// Function to show ST CofO restriction message
function showSTCofoRestrictionMessage() {
    Swal.fire({
        title: 'Registration Restriction',
        html: `
            <div class="text-left">
                <p class="mb-3"><strong>ST CofO (Sectional Titling Certificate of Occupancy)</strong> cannot be registered directly.</p>
                <p class="mb-3">To register an ST CofO, you must first ensure that the corresponding <strong>ST Assignment (Transfer of Title)</strong> or <strong>ST Fragmentation</strong> has been registered.</p>
                <div class="bg-blue-50 p-3 rounded-lg mt-4">
                    <p class="text-sm text-blue-800"><i class="fas fa-info-circle mr-2"></i><strong>Registration Process:</strong></p>
                    <ol class="text-sm text-blue-700 mt-2 ml-4">
                        <li>1. Register the ST Assignment (Transfer of Title) or ST Fragmentation first</li>
                        <li>2. Once registered, the ST CofO will become available for registration</li>
                    </ol>
                </div>
            </div>
        `,
        icon: 'info',
        confirmButtonText: 'I Understand',
        confirmButtonColor: '#3085d6',
        width: '500px'
    });
}

function showAlreadyRegisteredMessage() {
    Swal.fire({
        title: 'Already Registered',
        text: 'This instrument has already been registered in the official registry. You can view the RDS or CoR from the actions menu.',
        icon: 'info',
        confirmButtonText: 'OK',
        confirmButtonColor: '#3085d6'
    });
}

// Close dropdown when clicking outside
document.addEventListener('click', function (e) {
    // Handle timeline item clicks
    const tlItem = e.target.closest('.instr-timeline-item');
    if (tlItem) {
        e.preventDefault();
        e.stopPropagation();
        const propId = tlItem.getAttribute('data-prop-id') || '';
        const fileNo = tlItem.getAttribute('data-file-number') || '';
        if (typeof openPropertyTimeline === 'function') {
            openPropertyTimeline(propId, fileNo);
        } else if (typeof window.openTailwindModal === 'function') {
            var params = new URLSearchParams();
            if (propId) params.set('prop_id', propId);
            if (fileNo) params.set('file_number', fileNo);
            params.set('mode', 'partial');
            window.openTailwindModal('/property-search/timeline?' + params.toString(), 'xl');
        }
        closeDropdown();
        return;
    }

    if (!e.target.closest('.dropdown-wrapper') && !e.target.closest('#dropdown-menu')) {
        closeDropdown();
    }
});

// Close dropdown when clicking backdrop
const backdrop = document.getElementById('dropdown-backdrop');
if (backdrop) {
    backdrop.addEventListener('click', closeDropdown);
}

// Close dropdown on scroll and resize
window.addEventListener('scroll', closeDropdown);
window.addEventListener('resize', closeDropdown);

// Close dropdown on table scroll
document.querySelector('.table-container')?.addEventListener('scroll', closeDropdown);

// Close dropdown on escape key
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        closeDropdown();
    }
});

// Function to update checkbox states based on ST Assignment registration status
function updateCheckboxStates() {
    const checkboxes = document.querySelectorAll('.main-table-checkbox');

    checkboxes.forEach((checkbox, index) => {
        const instrumentType = checkbox.getAttribute('data-instrument-type');
        const status = checkbox.getAttribute('data-status');
        const fileno = checkbox.getAttribute('data-fileno');
        const id = checkbox.getAttribute('data-id');

        // Handle ST Assignment (Transfer of Title) - should be enabled when pending
        if (status === 'pending' && instrumentType === 'ST Assignment (Transfer of Title)') {
            checkbox.disabled = false;
        }

        // Handle ST CofO (Sectional Titling CofO) - should be enabled only if corresponding ST Assignment is registered
        else if (status === 'pending' && instrumentType === 'Sectional Titling CofO') {
            // For aggregate rows use the pre-computed _cofoEnabled flag stamped as a data attribute;
            // for individual unit rows fall back to the serverCofoData lookup.
            const aggCofoEnabled = checkbox.getAttribute('data-agg-cofo-enabled');
            let shouldEnable;
            if (aggCofoEnabled !== null) {
                shouldEnable = aggCofoEnabled === '1';
            } else {
                const stAssignmentRegistered = serverCofoData.find(item => {
                    return item.fileno === fileno &&
                        item.instrument_type === 'ST Assignment (Transfer of Title)' &&
                        item.status === 'registered';
                });
                shouldEnable = !!stAssignmentRegistered;
            }

            // Enable/disable checkbox based on ST Assignment registration status
            // (shouldEnable already set above)
            checkbox.disabled = !shouldEnable;

            // Add visual indicator for disabled ST CofO checkboxes
            const row = checkbox.closest('tr');
            if (row) {
                if (!shouldEnable) {
                    row.classList.add('st-cofo-disabled');
                    checkbox.title = 'This ST CofO cannot be registered until the corresponding ST Assignment is registered first';
                } else {
                    row.classList.remove('st-cofo-disabled');
                    checkbox.title = '';
                }
            }

            // If checkbox becomes disabled and was checked, uncheck it
            if (checkbox.disabled && checkbox.checked) {
                checkbox.checked = false;
                handleMainTableCheckboxChange();
            }
        }

        // For registered instruments, disable checkboxes (already registered)
        else if (status === 'registered') {
            checkbox.disabled = true;
        }

        // For other pending instruments (not ST Assignment or ST CofO), enable them
        else if (status === 'pending') {
            checkbox.disabled = false;
        }
    });
}

// Enhanced batch registration functionality
function handleMainTableCheckboxChange(changedCheckbox) {
    // Enforce single selection: uncheck all other checkboxes when one is checked
    if (changedCheckbox && changedCheckbox.checked) {
        document.querySelectorAll('.main-table-checkbox:not([disabled])').forEach(function(cb) {
            if (cb !== changedCheckbox) cb.checked = false;
        });
        // Also uncheck any ST batch checkboxes so only one item is ever selected
        document.querySelectorAll('.st-batch-checkbox:checked:not([disabled])').forEach(function(cb) {
            cb.checked = false;
        });
    }

    const checkedBoxes = document.querySelectorAll('.main-table-checkbox:checked:not([disabled])');
    const checkedCount = checkedBoxes.length;
    const stCheckedCount = document.querySelectorAll('.st-batch-checkbox:checked:not([disabled])').length;
    const totalCount = checkedCount + stCheckedCount;
    const batchBtn = document.getElementById('batchRegisterBtn');
    const batchBtnText = document.getElementById('batchBtnText');

    // Drive the new Batch Registration button
    const groupBatchBtn = document.getElementById('groupBatchRegBtn');
    const batchBadge   = document.getElementById('batchSelectedBadge');
    if (groupBatchBtn) {
        groupBatchBtn.style.display = totalCount > 0 ? 'flex' : 'none';
    }
    if (batchBadge) batchBadge.textContent = totalCount;

    // Update legacy button state and text based on selection
    if (checkedCount === 0) {
        if (batchBtnText) batchBtnText.textContent = 'Registration';
    } else if (checkedCount === 1) {
        if (batchBtnText) batchBtnText.textContent = 'Registration';
    } else {
        if (batchBtnText) batchBtnText.textContent = 'Batch Registration';

        // Get the selected instrument data
        const selectedInstruments = Array.from(checkedBoxes).map(checkbox => {
            const id = checkbox.getAttribute('data-id');
            const status = checkbox.getAttribute('data-status');

            // Find the instrument data from serverCofoData
            const instrumentData = serverCofoData.find(item => String(item.id) === String(id));

            if (instrumentData) {
                return {
                    id: instrumentData.id,
                    fileNo: instrumentData.fileno,
                    grantor: instrumentData.Grantor || '',
                    grantee: instrumentData.Grantee || '',
                    status: status,
                    instrumentType: instrumentData.instrument_type || '',
                    lga: instrumentData.lga || '',
                    district: instrumentData.district || '',
                    plotNumber: instrumentData.plotNumber || '',
                    plotSize: instrumentData.size || '',
                    plotDescription: instrumentData.propertyDescription || '',
                    duration: instrumentData.duration || instrumentData.leasePeriod || '',
                    deeds_date: instrumentData.deeds_date || instrumentData.instrumentDate || '',
                    deeds_time: instrumentData.deeds_time || '',
                    rootRegistrationNumber: instrumentData.rootRegistrationNumber || instrumentData.Deeds_Serial_No || '',
                    solicitorName: instrumentData.solicitorName || '',
                    solicitorAddress: instrumentData.solicitorAddress || '',
                    landUseType: instrumentData.landUseType || instrumentData.land_use || '',
                    opType: instrumentData.op_type || ''
                };
            }
            return null;
        }).filter(item => item !== null);

        // Store selected instruments for the batch modal
        if (typeof window.setSelectedInstrumentsForBatch === 'function') {
            window.setSelectedInstrumentsForBatch(selectedInstruments);
        }
    }

    // Reset batch modal if needed
    if (typeof window.resetBatchModalIfNeeded === 'function') {
        window.resetBatchModalIfNeeded();
    }
}

// Select-all is disabled in single-select batch mode — clicking it does nothing
function toggleSelectAll(checkbox) {
    checkbox.checked = false;
}

// Pagination and Search Functionality
document.addEventListener('DOMContentLoaded', function () {
    // Pagination variables
    let currentPage = 1;
    let recordsPerPage = 25;
    let filteredData = [];
    let totalRecords = 0;

    // Filter data based on search and instrument type
    window.filterTable = function () {
        filterData();
    };

    function filterData() {
        // Ensure serverCofoData is available
        if (!window.serverCofoData) return;

        const searchInput = document.getElementById('searchInput');
        const typeFilter = document.getElementById('instrumentTypeFilter');

        const volumeFilter = document.getElementById('volumeFilter');

        // Safely get values
        const searchTerm = searchInput ? searchInput.value.toLowerCase().trim() : '';
        const selectedType = typeFilter ? typeFilter.value : '';
        const selectedVolume = volumeFilter ? volumeFilter.value : '';

        filteredData = window.serverCofoData.filter(item => {
            // Search term filter
            const matchesSearch = searchTerm === '' ||
                ((item.fileno && item.fileno.toLowerCase().includes(searchTerm)) ||
                    (item.Grantor && item.Grantor.toLowerCase().includes(searchTerm)) ||
                    (item.Grantee && item.Grantee.toLowerCase().includes(searchTerm)) ||
                    (item.application_type && item.application_type.toLowerCase().includes(searchTerm)) ||
                    (item.op_type && item.op_type.toLowerCase().includes(searchTerm)) ||
                    (item.lga && item.lga.toLowerCase().includes(searchTerm)) ||
                    (item.district && item.district.toLowerCase().includes(searchTerm)) ||
                    (item.plotNumber && item.plotNumber.toLowerCase().includes(searchTerm)) ||
                    (item.instrument_type && item.instrument_type.toLowerCase().includes(searchTerm)));

            // Instrument Type filter
            const matchesType = selectedType === '' || 
                               item.instrument_type === selectedType ||
                               (item.instrument_type === 'Occupancy Permit (OP)' && (
                                   item.op_type === selectedType || 
                                   (item.op_type && ("OP " + item.op_type === selectedType)) ||
                                   (item.op_type && (item.op_type === selectedType.replace(/^OP /, '')))
                               ));

            // Volume Filter
            const itemVolume = item.volume_no || item.Volume_No || item.Volume || '';
            const matchesVolume = selectedVolume === '' || String(itemVolume) === String(selectedVolume);

            return matchesSearch && matchesType && matchesVolume;
        });

        totalRecords = filteredData.length;
        currentPage = 1; // Reset to first page
        window.updateTable(); // Use global updateTable
        window.updatePaginationControls(); // Use global updatePaginationControls

        // Update showing total
        const showingTotal = document.getElementById('showingTotal');
        if (showingTotal) showingTotal.innerText = totalRecords;
    };

    // Nested DOMContentLoaded listener removed

    // Initialize data from global variable
    if (window.serverCofoData) {
        filteredData = [...window.serverCofoData];
        totalRecords = filteredData.length;
    }

    // Get DOM elements
    const recordsPerPageSelect = document.getElementById('recordsPerPage');
    const searchInput = document.getElementById('searchInput');
    const prevPageBtn = document.getElementById('prevPage');
    const nextPageBtn = document.getElementById('nextPage');
    const pageNumbers = document.getElementById('pageNumbers');
    const showingStart = document.getElementById('showingStart');
    const showingEnd = document.getElementById('showingEnd');
    const showingTotal = document.getElementById('showingTotal');
    const tableBody = document.getElementById('cofoTableBody');

    // Hoist updatePaginationControls to global scope
    window.updatePaginationControls = function () {
        const prevPageBtn = document.getElementById('prevPage');
        const nextPageBtn = document.getElementById('nextPage');
        const pageNumbers = document.getElementById('pageNumbers');

        if (!pageNumbers) return;

        const totalPages = Math.ceil(totalRecords / recordsPerPage);

        pageNumbers.innerHTML = '';

        // Update Previous/Next button states
        if (prevPageBtn) {
            prevPageBtn.disabled = currentPage === 1;
        }

        if (nextPageBtn) {
            nextPageBtn.disabled = currentPage === totalPages || totalPages === 0;
        }

        if (totalPages <= 1) return;

        const maxVisiblePages = 5;
        let startPage = Math.max(1, currentPage - Math.floor(maxVisiblePages / 2));
        let endPage = Math.min(totalPages, startPage + maxVisiblePages - 1);

        if (endPage - startPage < maxVisiblePages - 1) {
            startPage = Math.max(1, endPage - maxVisiblePages + 1);
        }

        if (startPage > 1) {
            addPageButton(1);
            if (startPage > 2) {
                pageNumbers.insertAdjacentHTML('beforeend', '<span class="px-2 py-1 text-gray-500">...</span>');
            }
        }

        for (let i = startPage; i <= endPage; i++) {
            addPageButton(i);
        }

        if (endPage < totalPages) {
            if (endPage < totalPages - 1) {
                pageNumbers.insertAdjacentHTML('beforeend', '<span class="px-2 py-1 text-gray-500">...</span>');
            }
            addPageButton(totalPages);
        }
    };

    // Hoist updateTable to global scope
    window.updateTable = function () {
        // Ensure elements exist
        const tableBody = document.getElementById('cofoTableBody');
        const showingStart = document.getElementById('showingStart');
        const showingEnd = document.getElementById('showingEnd');
        const showingTotal = document.getElementById('showingTotal');

        if (!tableBody) return;

        const startIndex = (currentPage - 1) * recordsPerPage;
        const endIndex = startIndex + recordsPerPage;
        const pageData = filteredData.slice(startIndex, endIndex);

        // Clear existing table body
        tableBody.innerHTML = '';

        if (pageData.length === 0) {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="17" class="px-6 py-10 text-center text-gray-500">
                        No instrument registrations found matching your criteria.
                    </td>
                </tr>
            `;
            if (showingStart) showingStart.innerText = 0;
            if (showingEnd) showingEnd.innerText = 0;
            if (showingTotal) showingTotal.innerText = totalRecords;
            document.getElementById('totalRecordsCount').textContent = `${totalRecords} Total Records`;
            return;
        }

        // Populate table with page data
        pageData.forEach(app => {
            let isDisabled;

            if (app._isAggregate) {
                // Aggregate row: enabled when there are pending units to register
                const hasPending = (app._pendingUnits || []).length > 0;
                isDisabled = !hasPending || (app._aggType === 'sectional_cofo' && !app._cofoEnabled);
            } else {
                isDisabled = app.status === 'registered';

                // For ST CofO, enable only if corresponding ST Assignment is registered
                if (app.status === 'pending' && app.instrument_type === 'Sectional Titling CofO') {
                    const stAssignmentRegistered = serverCofoData.find(item =>
                        item.fileno === app.fileno &&
                        (item.instrument_type === 'ST Assignment (Transfer of Title)' || item.instrument_type === 'ST Fragmentation') &&
                        item.status === 'registered'
                    );
                    isDisabled = !stAssignmentRegistered;
                }

                // In batch mode, force-enable pending non-CofO items.
                // CofO qualification is enforced by the check above — don't override it.
                if (window.batchModeActive && app.status === 'pending' && app.instrument_type !== 'Sectional Titling CofO') {
                    isDisabled = false;
                }
            }

            // Format dates with robust detection for swapped fields
            let rawRegDate = app.reg_date;
            let rawRegTime = app.reg_time;

            // Basic swap detection: if reg_date looks like a time (contains :) and 
            // reg_time looks like a date (contains - or /), swap them
            if (rawRegDate && rawRegTime && 
                rawRegDate.includes(':') && !rawRegDate.includes('-') &&
                (rawRegTime.includes('-') || rawRegTime.includes('/'))) {
                const temp = rawRegDate;
                rawRegDate = rawRegTime;
                rawRegTime = temp;
            }

            const capturedDate = app.captured_date ?
                `<div class="flex items-center">
                    <i class="fas fa-calendar-plus text-blue-400 mr-2"></i>
                    ${new Date(app.captured_date).toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' })}
                </div>` :
                '<span class="text-gray-400">-</span>';

            const capturedTime = app.captured_date ?
                `<div class="flex items-center">
                    <i class="fas fa-clock text-gray-400 mr-2"></i>
                    ${new Date(app.captured_date).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true })}
                </div>` :
                '<span class="text-gray-400">-</span>';

            const regDate = rawRegDate ?
                `<div class="flex items-center">
                    <i class="fas fa-calendar-check text-green-400 mr-2"></i>
                    ${new Date(rawRegDate).toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' })}
                </div>` :
                '<span class="text-gray-400">-</span>';

            const regTime = rawRegTime ?
                `<div class="flex items-center">
                    <i class="fas fa-clock text-gray-400 mr-2"></i>
                    ${rawRegTime}
                </div>` :
                '<span class="text-gray-400">-</span>';

            // Format reg particulars
            let regParticulars = '<span class="text-gray-400 text-xs">-</span>';
            if (app._isAggregate) {
                const reg   = app._registeredCount || 0;
                const total = app._totalCount || 0;
                const pct   = total > 0 ? Math.round((reg / total) * 100) : 0;
                const clr   = app._aggType === 'sectional_cofo' ? '#0d9488' : '#6366f1';
                regParticulars = `<span style="font-size:12px;font-weight:700;color:${clr};">${reg}/${total}</span>`
                    + `<div style="height:4px;background:#e5e7eb;border-radius:2px;margin-top:3px;width:60px;">`
                    + `<div style="height:4px;background:${clr};border-radius:2px;width:${pct}%;"></div></div>`;
            } else if (app.status === 'registered') {
                if (app.instrument_type === 'ST Fragmentation') {
                    regParticulars = '<span class="px-2 py-1 bg-purple-100 text-purple-800 rounded-md font-mono text-xs">0/0/0</span>';
                } else {
                    regParticulars = `<span class="px-2 py-1 bg-blue-100 text-blue-800 rounded-md font-mono text-xs">${app.Deeds_Serial_No || '-'}</span>`;
                }
            }

            // Format parent file number
            let parentFileNo = '<span class="text-gray-400">-</span>';
            if (app.instrument_type === 'ST Fragmentation') {
                parentFileNo = `<span class="file-number">${app.parent_fileNo || '-'}</span>`;
            } else if (['ST Assignment (Transfer of Title)', 'Sectional Titling CofO'].includes(app.instrument_type)) {
                parentFileNo = `<span class="file-number">${app.parent_fileNo || app.fileno || '-'}</span>`;
            }

            // Format instrument type badge
            let instrumentTypeBadge = '';
            if (app.instrument_type === 'ST Fragmentation') {
                instrumentTypeBadge = '<span class="badge badge-st-fragmentation"><i class="fas fa-puzzle-piece mr-1"></i>ST Fragmentation</span>';
            } else if (app.instrument_type === 'ST Assignment (Transfer of Title)') {
                instrumentTypeBadge = '<span class="badge badge-st-assignment"><i class="fas fa-exchange-alt mr-1"></i>ST Assignment</span>';
            } else if (app.instrument_type === 'Sectional Titling CofO') {
                instrumentTypeBadge = '<span class="badge badge-sectional-titling"><i class="fas fa-building mr-1"></i>ST CofO</span>';
            } else if (app.instrument_type === 'Occupancy Permit (OP)') {
                instrumentTypeBadge = `<span class="badge badge-op-instrument"><i class="fas fa-file-signature mr-1"></i>Occupancy Permit (OP)</span>`;
            } else {
                instrumentTypeBadge = `<span class="badge badge-other-instrument"><i class="fas fa-file-alt mr-1"></i>${app.instrument_type || 'Other'}</span>`;
            }

            // Create combined Instrument Type + App Type cell content
            let instrumentAndAppTypeContent = `
                <div class="flex flex-col items-start space-y-1">
                    ${instrumentTypeBadge}
                    ${app.application_type ?
                    (() => {
                        const typeKey = app.application_type.toLowerCase().trim();
                        let badgeClass = 'bg-gray-100 text-gray-800 border-gray-200';
                        let displayType = app.application_type;

                        if (app.instrument_type === 'Occupancy Permit (OP)' && app.op_type) {
                            displayType = app.op_type.startsWith('OP ') ? app.op_type : 'OP ' + app.op_type;
                            badgeClass = 'bg-teal-100 text-teal-800 border-teal-200';
                        } else if (typeKey === 'sua') {
                            badgeClass = 'bg-green-100 text-green-800 border-green-200';
                        } else if (typeKey === 'pua') {
                            badgeClass = 'bg-blue-100 text-blue-800 border-blue-200';
                        } else if (typeKey === 'primary') {
                            badgeClass = 'bg-orange-100 text-orange-800 border-orange-200';
                        } else if (typeKey === 'mother_st') {
                            return `<span class="px-2 py-0.5 rounded-full text-[10px] font-semibold tracking-wide border bg-blue-100 text-blue-800 border-blue-200">PUA</span>`;
                        } else {
                            displayType = 'Deeds';
                        }

                        return `<span class="px-2 py-0.5 rounded-full text-[10px] font-semibold tracking-wide border ${badgeClass}">${displayType}</span>`;
                    })()
                    : '<span class="text-gray-400">-</span>'}
                </div>
            `;

            const row = `
                <tr class="cofo-row${app._isAggregate ? ' st-aggregate-row' : ''}" data-status="${app.status}" data-id="${app.id}"${app._isAggregate ? ` style="border-left:3px solid ${app._aggType === 'sectional_cofo' ? '#0d9488' : '#6366f1'};"` : ''}>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <input type="checkbox" class="rounded main-table-checkbox"
                            data-id="${app.id}"
                            data-status="${app.status}"
                            data-instrument-type="${app.instrument_type}"
                            data-op-type="${app.op_type || ''}"
                            data-fileno="${app.fileno}"
                            ${app._isAggregate && app._aggType === 'sectional_cofo' ? `data-agg-cofo-enabled="${app._cofoEnabled ? '1' : '0'}"` : ''}
                            ${isDisabled ? 'disabled' : ''}
                            onchange="handleMainTableCheckboxChange(this)">
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                        <div class="flex flex-col items-start gap-1">
                            <span class="font-mono text-xs">${app.prop_id || '-'}</span>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">${regParticulars}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">${instrumentAndAppTypeContent}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">${regTime}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">${regDate}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">${capturedTime}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">${capturedDate}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                        ${(() => {
                            const isPrimary = (app.application_type || '').toLowerCase() === 'primary';
                            const motherId  = app.original_mother_app_id ? String(app.original_mother_app_id) : null;
                            const groupInfo = isPrimary && motherId && window.stGroupLookup ? window.stGroupLookup[motherId] : null;
                            if (groupInfo) {
                                const cnt = groupInfo.unit_count || 0;
                                return `<button class="st-fileno-link" onclick="openUnitsModal('grp_m_${motherId}')">${app.fileno || '-'}</button>
                                        <div style="font-size:11px;color:#9ca3af;margin-top:3px;display:flex;align-items:center;gap:3px;">
                                            <i class="fas fa-home" style="font-size:9px;"></i>
                                            ${cnt} unit${cnt !== 1 ? 's' : ''}
                                        </div>`;
                            }
                            if (app._isAggregate) {
                                const cnt = app._totalCount || 0;
                                const aggMid = String(app.id || '').replace(/^mother_(cofo|assign)_/, '');
                                return `<button class="st-fileno-link" onclick="openUnitsModal('grp_m_${aggMid}')">${app.fileno || '-'}</button>
                                        <div style="font-size:11px;color:#9ca3af;margin-top:3px;display:flex;align-items:center;gap:3px;">
                                            <i class="fas fa-layer-group" style="font-size:9px;"></i>
                                            ${cnt} unit${cnt !== 1 ? 's' : ''}
                                        </div>`;
                            }
                            return `<span class="file-number">${app.fileno || '-'}</span>`;
                        })()}
                        ${(app.prop_id || app.fileno) ? `
                            <div class="mt-1">
                                <button type="button" data-timeline-badge
                                    data-prop-id="${app.prop_id || ''}" data-file-no="${app.fileno || ''}"
                                    class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-medium bg-indigo-50 text-indigo-700 border border-indigo-200 hover:bg-indigo-100"
                                    onclick="openPropertyTimeline('${app.prop_id || ''}', '${app.fileno || ''}')"
                                    title="View the full property timeline">
                                    <i class="fas fa-history"></i> Timeline<span data-timeline-count></span>
                                </button>
                            </div>` : ''}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                        <span class="status-badge badge-${app.status}">${app.status.charAt(0).toUpperCase() + app.status.slice(1)}</span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-medium">
                        ${(() => {
                            let g = app.Grantor || '-';
                            if (typeof g === 'string' && g.startsWith('[')) {
                                try {
                                    let arr = JSON.parse(g);
                                    if (Array.isArray(arr) && arr.length > 1) {
                                        return `<span class="cursor-pointer underline decoration-dotted" onclick="Swal.fire({title: 'Grantors', html: '${arr.map(x => x.replace(/'/g, "\\'")).join('<br>')}', icon: 'info'})">${arr[0]} +${arr.length - 1} more</span>`;
                                    } else if (Array.isArray(arr)) {
                                        return arr[0] || '-';
                                    }
                                } catch(e) {}
                            }
                            return g;
                        })()}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-medium">
                        ${(() => {
                            let g = app.Grantee || '-';
                            if (typeof g === 'string' && g.startsWith('[')) {
                                try {
                                    let arr = JSON.parse(g);
                                    if (Array.isArray(arr) && arr.length > 1) {
                                        return `<span class="cursor-pointer underline decoration-dotted" onclick="Swal.fire({title: 'Grantees', html: '${arr.map(x => x.replace(/'/g, "\\'")).join('<br>')}', icon: 'info'})">${arr[0]} +${arr.length - 1} more</span>`;
                                    } else if (Array.isArray(arr)) {
                                        return arr[0] || '-';
                                    }
                                } catch(e) {}
                            }
                            return g;
                        })()}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">${app.lga || '-'}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">${app.district || '-'}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">${app.plotNumber || '-'}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">${app.size || '-'}</td>

                    ${(() => {
                        const urlParams = new URLSearchParams(window.location.search);
                        const isStDeeds = urlParams.get('url') === 'st_deeds';
                        if (isStDeeds) return '';
                        return `
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                <div class="dropdown-wrapper">
                                    <button class="action-button text-gray-500 hover:text-gray-700 p-2 rounded-md transition-colors duration-200"
                                        onclick="toggleDropdown(this, '${app.id}')" type="button">
                                        <i data-lucide="more-vertical" class="w-4 h-4"></i>
                                    </button>
                                </div>
                            </td>
                        `;
                    })()}
                </tr>
            `;
            tableBody.insertAdjacentHTML('beforeend', row);
        });

        // Re-initialize Lucide icons for new content
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }

        // Update checkbox states after rendering the table
        setTimeout(() => {
            updateCheckboxStates();
        }, 50);

        // Update showing information
        const start = totalRecords === 0 ? 0 : startIndex + 1;
        const end = Math.min(endIndex, totalRecords);

        if (showingStart) showingStart.textContent = start;
        if (showingEnd) showingEnd.textContent = end;
        if (showingTotal) showingTotal.textContent = totalRecords;

        // Update total records count in header
        document.getElementById('totalRecordsCount').textContent = `${totalRecords} Total Records`;
    };

    // Add page button
    function addPageButton(pageNum) {
        const pageNumbers = document.getElementById('pageNumbers');
        const button = document.createElement('button');
        button.className = `px-3 py-2 text-sm font-medium rounded-md ${pageNum === currentPage
            ? 'bg-blue-600 text-white'
            : 'text-gray-500 bg-white border border-gray-300 hover:bg-gray-50'
            }`;
        button.textContent = pageNum;
        button.onclick = () => goToPage(pageNum);
        pageNumbers.appendChild(button);
    }

    // Go to specific page
    function goToPage(page) {
        const totalPages = Math.ceil(totalRecords / recordsPerPage);
        if (page >= 1 && page <= totalPages) {
            currentPage = page;
            window.updateTable();
            window.updatePaginationControls();
        }
    }

    // Initialize Pagination function
    function initializePagination() {
        if (recordsPerPageSelect) {
            const val = parseInt(recordsPerPageSelect.value);
            if (!isNaN(val)) {
                recordsPerPage = val;
            }
        }
        filterData();
    }

    // Event listeners
    recordsPerPageSelect.addEventListener('change', initializePagination);
    searchInput.addEventListener('input', filterData);
    prevPageBtn.addEventListener('click', () => goToPage(currentPage - 1));
    nextPageBtn.addEventListener('click', () => goToPage(currentPage + 1));

    // Initialize
    initializePagination();

    // Update checkbox states after page loads
    setTimeout(() => {
        updateCheckboxStates();
    }, 100);
    // End of nested block removed


    // Also call updateCheckboxStates when the page is fully loaded
    window.addEventListener('load', function () {
        setTimeout(() => {
            updateCheckboxStates();
            
            // Initialize Select2 for Volume Filter
            if ($.fn.select2) {
                $('#volumeFilter').select2({
                    placeholder: "Vol",
                    allowClear: true,
                    width: '100%'
                }).on('change', function() {
                    if (typeof filterTable === 'function') filterTable();
                });
                
                // Style the Select2 container to match the UI
                $('.select2-container--default .select2-selection--single').css({
                    'height': '38px',
                    'border-color': '#d1d5db',
                    'border-radius': '0.5rem',
                    'display': 'flex',
                    'align-items': 'center'
                });
            }
        }, 200);
    });
});

// Export Registry Logic
window.exportData = [];

window.openExportModal = async function () {
    const modal = document.getElementById('exportPreviewModal');
    const body = document.getElementById('exportPreviewBody');
    const type = document.getElementById('instrumentTypeFilter').value;
    const volume = document.getElementById('volumeFilter').value;

    document.getElementById('previewFilterType').textContent = type || 'All Types';
    document.getElementById('previewFilterVolume').textContent = volume || 'All';
    modal.classList.remove('hidden');

    // Show loading
    body.innerHTML = `
        <tr>
            <td colspan="10" class="px-6 py-12 text-center text-gray-500 italic">
                <div class="flex flex-col items-center gap-2">
                    <i class="fas fa-spinner fa-spin text-3xl text-green-500"></i>
                    <span>Fetching data for preview...</span>
                </div>
            </td>
        </tr>
    `;

    try {
        const response = await fetch(`${window.KlaesConfig.urls.base}/export?instrument_type=${encodeURIComponent(type)}&volume_no=${volume}`);
        const result = await response.json();

        if (result.success) {
            window.exportData = result.data;
            document.getElementById('previewRecordCount').textContent = result.data.length;

            if (result.data.length === 0) {
                body.innerHTML = '<tr><td colspan="10" class="px-6 py-8 text-center text-gray-500">No records found matching filters.</td></tr>';
                return;
            }

            body.innerHTML = result.data.map(item => `
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-4 py-3 text-sm text-gray-600">${item.SN}</td>
                    <td class="px-4 py-3 text-sm font-medium text-gray-900">${item.fileno}</td>
                    <td class="px-4 py-3 text-sm text-gray-600 font-mono">${item.reg_particulars}</td>
                    <td class="px-4 py-3 text-sm text-gray-600 truncate max-w-[150px]" title="${item.party_1}">${item.party_1}</td>
                    <td class="px-4 py-3 text-sm text-gray-600 truncate max-w-[150px]" title="${item.party_2}">${item.party_2}</td>
                    <td class="px-4 py-3 text-sm text-gray-600 truncate max-w-[150px]" title="${item.party_3}">${item.party_3}</td>
                    <td class="px-4 py-3 text-sm text-gray-600 truncate max-w-[150px]" title="${item.party_4}">${item.party_4}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">${item.deed_date}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">${item.deed_time}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">${item.op_serial}</td>
                </tr>
            `).join('');
        } else {
            throw new Error(result.error || 'Failed to fetch data');
        }
    } catch (e) {
        body.innerHTML = `<tr><td colspan="10" class="px-6 py-8 text-center text-red-500">Error: ${e.message}</td></tr>`;
    }
};

window.closeExportModal = function () {
    document.getElementById('exportPreviewModal').classList.add('hidden');
};

window.downloadExportCsv = function () {
    if (!window.exportData || window.exportData.length === 0) {
        Swal.fire('No Data', 'There is no data to export.', 'warning');
        return;
    }

    const headers = ['SN', 'fileno', 'serialNo', 'pageNo', 'volumeNo', 'reg particulars', 'party 1', 'party 2', 'party 3', 'party 4', 'deed time', 'deed date', 'op serial', 'location'];
    const csvContent = [
        headers.join(','),
        ...window.exportData.map(item => [
            item.SN,
            `"${item.fileno}"`,
            item.serialNo,
            item.pageNo,
            item.volumeNo,
            `"${item.reg_particulars}"`,
            `"${item.party_1}"`,
            `"${item.party_2}"`,
            `"${item.party_3}"`,
            `"${item.party_4}"`,
            `"${item.deed_time}"`,
            `"${item.deed_date}"`,
            `"${item.op_serial}"`,
            `"${item.location.replace(/"/g, '""')}"`
        ].join(','))
    ].join('\n');

    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    const typeLabel = document.getElementById('instrumentTypeFilter').value || 'All';
    const volLabel = document.getElementById('volumeFilter').value || 'All';

    link.setAttribute('href', url);
    link.setAttribute('download', `Instrument_Registry_${typeLabel}_Vol_${volLabel}_${new Date().toISOString().split('T')[0]}.csv`);
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
};

window.downloadExportPdf = function () {
    if (!window.exportData || window.exportData.length === 0) {
        Swal.fire('No Data', 'There is no data to export.', 'warning');
        return;
    }

    // Load a remote image as a base64 data URL so jsPDF can embed it reliably.
    function loadImage(url) {
        return fetch(url)
            .then(function (response) {
                if (!response.ok) throw new Error('HTTP ' + response.status);
                return response.blob();
            })
            .then(function (blob) {
                return new Promise(function (resolve) {
                    var reader = new FileReader();
                    reader.onloadend = function () { resolve(reader.result); };
                    reader.onerror = function () { resolve(null); };
                    reader.readAsDataURL(blob);
                });
            })
            .catch(function (e) {
                console.warn('Failed to load logo:', url, e);
                return null;
            });
    }

    var headerLeftLogoUrl = '/assets/logo/ministry2.jpeg';
    var headerRightLogoUrl = '/assets/logo/ministry1.jpg';

    Promise.all([
        loadImage(headerLeftLogoUrl),
        loadImage(headerRightLogoUrl)
    ]).then(function (logos) {
        try {
            var headerLeftLogo = logos[0];
            var headerRightLogo = logos[1];

            var { jsPDF } = window.jspdf;
            var doc = new jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a4' });

            // Landscape A4 is 297mm wide. Pick header geometry that centres nicely
            // between the two logos placed at the outer edges.
            var pageWidth = doc.internal.pageSize.getWidth();
            var pageCenter = pageWidth / 2;
            var headerLogoSize = 22;

            if (headerLeftLogo) {
                doc.addImage(headerLeftLogo, 'JPEG', 10, 8, headerLogoSize, headerLogoSize);
            }
            if (headerRightLogo) {
                doc.addImage(headerRightLogo, 'JPEG', pageWidth - 10 - headerLogoSize, 8, headerLogoSize, headerLogoSize);
            }

            doc.setFont('helvetica', 'bold');
            doc.setFontSize(16);
            doc.text('KANO STATE GOVERNMENT', pageCenter, 14, { align: 'center' });
            doc.setFontSize(12);
            doc.text('MINISTRY OF LAND AND PHYSICAL PLANNING', pageCenter, 20, { align: 'center' });
            doc.setFontSize(11);
            doc.text('DEEDS DEPARTMENT', pageCenter, 26, { align: 'center' });

            doc.setLineWidth(0.5);
            doc.line(10, 32, pageWidth - 10, 32);

            var typeLabel = document.getElementById('instrumentTypeFilter').value || 'All Instruments';
            var volLabel = document.getElementById('volumeFilter').value || 'All Volumes';

            // Report title + metadata beneath the ministry header
            doc.setFontSize(13);
            doc.setFont('helvetica', 'bold');
            doc.text('Instruments - ' + typeLabel, 14, 39);
            doc.setFontSize(10);
            doc.setFont('helvetica', 'normal');
            doc.text('Volume: ' + volLabel + ' | Generated on: ' + new Date().toLocaleDateString(), 14, 45);

            var tableColumn = [
                "SN", "File No", "Serial", "Page", "Vol", "Particulars",
                "Party 1", "Party 2", "Party 3", "Time", "Date", "OP Serial", "Location"
            ];

            var tableRows = window.exportData.map(function (item) {
                return [
                    item.SN,
                    item.fileno,
                    item.serialNo,
                    item.pageNo,
                    item.volumeNo,
                    item.reg_particulars,
                    item.party_1,
                    item.party_2,
                    item.party_3,
                    item.deed_time,
                    item.deed_date,
                    item.op_serial || 'N/A',
                    item.location
                ];
            });

            doc.autoTable({
                head: [tableColumn],
                body: tableRows,
                startY: 50,
                theme: 'grid',
                styles: { fontSize: 8, cellPadding: 2, overflow: 'linebreak' },
                headStyles: { fillColor: [22, 163, 74], textColor: [255, 255, 255], fontStyle: 'bold' },
                columnStyles: {
                    0: { cellWidth: 10 },   // SN
                    1: { cellWidth: 25 },   // File No
                    2: { cellWidth: 12 },   // Serial
                    3: { cellWidth: 12 },   // Page
                    4: { cellWidth: 12 },   // Vol
                    5: { cellWidth: 35 },   // Particulars
                    6: { cellWidth: 25 },   // Party 1
                    7: { cellWidth: 25 },   // Party 2
                    8: { cellWidth: 25 },   // Party 3
                    9: { cellWidth: 15 },   // Time
                    10: { cellWidth: 20 },  // Date
                    11: { cellWidth: 20 },  // OP Serial
                    12: { cellWidth: 'auto' } // Location
                },
                // Reserve space for the ministry header on every page.
                margin: { top: 50 },
                didDrawPage: function (data) {
                    // Re-paint the ministry header on subsequent pages.
                    if (data.pageNumber > 1) {
                        if (headerLeftLogo) {
                            doc.addImage(headerLeftLogo, 'JPEG', 10, 8, headerLogoSize, headerLogoSize);
                        }
                        if (headerRightLogo) {
                            doc.addImage(headerRightLogo, 'JPEG', pageWidth - 10 - headerLogoSize, 8, headerLogoSize, headerLogoSize);
                        }
                        doc.setFont('helvetica', 'bold');
                        doc.setFontSize(16);
                        doc.text('KANO STATE GOVERNMENT', pageCenter, 14, { align: 'center' });
                        doc.setFontSize(12);
                        doc.text('MINISTRY OF LAND AND PHYSICAL PLANNING', pageCenter, 20, { align: 'center' });
                        doc.setFontSize(11);
                        doc.text('DEEDS DEPARTMENT', pageCenter, 26, { align: 'center' });
                        doc.setLineWidth(0.5);
                        doc.line(10, 32, pageWidth - 10, 32);
                    }
                },
                didParseCell: function (data) {
                    if (data.column.index === 11 && data.cell.text[0] === 'N/A') {
                        data.cell.styles.textColor = [150, 150, 150];
                    }
                }
            });

            doc.save('Instrument_Registry_' + typeLabel.replace(/\s+/g, '_') + '_Vol_' + volLabel + '_' + new Date().toISOString().split('T')[0] + '.pdf');
        } catch (e) {
            console.error('PDF Generation Error:', e);
            Swal.fire('Error', 'Failed to generate PDF: ' + e.message, 'error');
        }
    });
};
