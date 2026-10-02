// SuA (Standalone Unit Application) JavaScript Module for Commission Interface

/* =========================================================================
 * PLACEHOLDER — NOT REAL DATA. REMOVE WHEN THE BACKEND LANDS.
 *
 * ST commissioning does not yet open a file_tracker record, so the server
 * sends no tracking lines for it. These two rows are hardcoded purely so the
 * card can be screenshotted for sign-off; they are the SAME EVERY TIME and do
 * not reflect anything that was actually written.
 *
 * The real version is IndexingStorageSummaryService::pushTrackingLines(),
 * which reads the tracker's movement_log — that is what the Land commissioning
 * card already uses. Once ST commissioning writes its tracker, delete this
 * function and its call in showStCommissioningCard(); the rows will then come
 * from the server like every other row on the card.
 * ========================================================================= */
function addPlaceholderStTrackingLines(summary) {
    var PLACEHOLDER_ROWS = [
        { table: 'file_tracker', label: 'File Commissioning (DIIT)', count: 1, detail: 'File Commissioning Office' },
        { table: 'file_tracker', label: 'Log-out', count: 1, detail: 'Director Land' }
    ];

    var next = Object.assign({}, summary || {});
    next.groups = (next.groups || []).map(function (g) { return Object.assign({}, g); });

    var onward = next.groups.filter(function (g) { return g.tone === 'onward'; })[0];
    if (!onward) {
        onward = { title: 'Onward / derived', tone: 'onward', rows: [] };
        next.groups.push(onward);
    }

    // Don't double up if the server ever starts sending real tracker rows —
    // at that point this whole function should be deleted anyway.
    var hasTrackerRow = (onward.rows || []).some(function (r) { return r.table === 'file_tracker'; });
    onward.rows = hasTrackerRow ? onward.rows : (onward.rows || []).concat(PLACEHOLDER_ROWS);

    return next;
}

/**
 * Commissioning confirmation for ST (Primary / SuA / PuA).
 *
 * Commissioning an ST file writes across st_file_numbers, fileNumber,
 * file_indexings and the customer/entity staging tables, so the confirmation is
 * the shared "where did this go" card (js/shared/record-summary-card.js) — the
 * same one shown after file indexing — with the EDMS scan folder appended.
 *
 * Degrades to a plain success dialog if that script is not on the page.
 */
function showStCommissioningCard(result, message) {
    const edmsLine = renderEdmsFolderLine(result && result.edms_folder);

    if (typeof window.showRecordSummaryCard === 'function') {
        const payload = Object.assign({}, result, { message: message });
        payload.storage_summary = addPlaceholderStTrackingLines(payload.storage_summary);

        return window.showRecordSummaryCard(
            payload,
            {
                title: 'Commissioned — here is where it went',
                fallbackTitle: 'Success!',
                extraHtml: edmsLine
            }
        );
    }

    return Swal.fire({
        icon: 'success',
        title: 'Success!',
        html: `<p>${message}</p>` + edmsLine,
        confirmButtonColor: '#10b981'
    });
}
window.showStCommissioningCard = showStCommissioningCard;

/**
 * "Where scans for this file go" line for a commissioning confirmation.
 *
 * The EDMS scan folder is created server-side the moment a file number is
 * commissioned, so the operator can start scanning before the file is indexed.
 * Returns '' when the server did not report a folder, so callers can always
 * concatenate it. Shared by the SuA / PuA / Primary ST commissioning dialogs.
 */
function renderEdmsFolderLine(edmsFolder) {
    if (!edmsFolder || !edmsFolder.path) {
        return '';
    }

    const label = edmsFolder.existed ? 'Scan folder already present' : 'EDMS scan folder created';

    return `
        <div style="margin-top:10px;padding:8px 10px;border:1px solid #d1fae5;background:#ecfdf5;border-radius:6px;text-align:left;">
            <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#047857;">${label}</div>
            <div style="font-size:11px;font-family:monospace;color:#334155;word-break:break-all;margin-top:2px;">${edmsFolder.path}</div>
        </div>
    `;
}
window.renderEdmsFolderLine = renderEdmsFolderLine;

// SuA uses the shared applicant type change handler with 'sua_' prefix
// No need to redefine - the shared function handles this

// Allocation Information section removed - no longer needed

// Handle SuA land use change - PREVIEW ONLY (no automatic file generation)
function handleSuaLandUseChange(checkboxElement) {
    console.log('🎯 SuA Land use checkbox changed:', checkboxElement.value, 'checked:', checkboxElement.checked);
    
    // Handle checkbox selection (only allow one selection like radio button)
    const allCheckboxes = document.querySelectorAll('input[name="sua_selectedLandUse"]');
    const checkedBoxes = [];
    
    allCheckboxes.forEach(checkbox => {
        if (checkbox !== checkboxElement && checkboxElement.checked) {
            // Uncheck other checkboxes when one is selected
            checkbox.checked = false;
            checkbox.parentElement.classList.remove('selected');
        }
        
        if (checkbox.checked) {
            checkedBoxes.push(checkbox.value);
            checkbox.parentElement.classList.add('selected');
        } else {
            checkbox.parentElement.classList.remove('selected');
        }
    });
    
    // Update hidden input
    const selectedLandUse = checkedBoxes.length > 0 ? checkedBoxes[0] : '';
    const hiddenInput = document.getElementById('sua_land_use_hidden');
    if (hiddenInput) {
        hiddenInput.value = selectedLandUse;
    }
    
    console.log('🎯 Selected SuA land use:', selectedLandUse);
    
    if (selectedLandUse) {
        console.log('� Showing SuA file number preview for land use:', selectedLandUse);
        // Show preview only - DO NOT generate actual file numbers
        showSuaFileNumberPreview(selectedLandUse);
    } else {
        // Clear file numbers if no land use selected
        clearSuaFileNumbers();
    }
}

// Clear SuA file numbers
function clearSuaFileNumbers() {
    const primaryFileNoInput = document.getElementById('sua_primary_fileno');
    const suaFileNoInput = document.getElementById('sua_fileno');
    const mlsFileNoInput = document.getElementById('mls_fileno');
    
    if (primaryFileNoInput) primaryFileNoInput.value = '';
    if (suaFileNoInput) suaFileNoInput.value = '';
    if (mlsFileNoInput) mlsFileNoInput.value = '';
}

// Show SuA file number preview - REAL PREVIEW with actual serial numbers
function showSuaFileNumberPreview(landUse) {
    console.log('📋 Showing SuA file number preview for:', landUse);
    
    // Show loading state
    const primaryFileNoInput = document.getElementById('sua_primary_fileno');
    const mlsFileNoInput = document.getElementById('mls_fileno');
    const suaFileNoInput = document.getElementById('sua_fileno');
    
    if (primaryFileNoInput) primaryFileNoInput.value = 'Loading preview...';
    if (mlsFileNoInput) mlsFileNoInput.value = 'Loading preview...';
    if (suaFileNoInput) suaFileNoInput.value = 'Loading preview...';
    
    // Call preview API to get actual next file numbers
    fetch('/api/st-file-numbers/preview', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            land_use: landUse,
            type: 'SUA'
        })
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        console.log('SuA Preview API response:', data);
        
        if (data.success && data.data) {
            const primaryFileNo = data.data.preview_np_fileno;
            const unitFileNo = data.data.preview_unit_fileno;
            const mlsFileNo = data.data.preview_mls_fileno;
            
            // Update SuA Primary FileNo field with clean preview (no placeholder text)
            if (primaryFileNoInput) {
                primaryFileNoInput.value = primaryFileNo;
                primaryFileNoInput.classList.remove('text-green-600');
                primaryFileNoInput.classList.add('text-blue-600');
            }
            
            // Update MLS FileNo field (same as primary)  
            if (mlsFileNoInput) {
                mlsFileNoInput.value = mlsFileNo;
                mlsFileNoInput.classList.remove('text-green-600');
                mlsFileNoInput.classList.add('text-blue-600');
            }
            
            // Update SuA Unit FileNo field
            if (suaFileNoInput) {
                suaFileNoInput.value = unitFileNo;
                suaFileNoInput.classList.remove('text-green-600');
                suaFileNoInput.classList.add('text-blue-600');
            }
            
            console.log('✅ SuA file number preview updated (clean display):');
            console.log('  - Primary FileNo:', primaryFileNo);
            console.log('  - MLS FileNo:', mlsFileNo);
            console.log('  - Unit FileNo:', unitFileNo);
        } else {
            throw new Error(data.message || 'Failed to get SuA preview');
        }
    })
    .catch(error => {
        console.error('Error getting SuA preview:', error);
        
        // Fallback to error message on error
        if (primaryFileNoInput) {
            primaryFileNoInput.value = 'Error loading preview';
            primaryFileNoInput.classList.add('text-red-600');
        }
        if (mlsFileNoInput) {
            mlsFileNoInput.value = 'Error loading preview';
            mlsFileNoInput.classList.add('text-red-600');
        }
        if (suaFileNoInput) {
            suaFileNoInput.value = 'Error loading preview';
            suaFileNoInput.classList.add('text-red-600');
        }
    });
}

/**
 * Collect SuA applicant data from the form
 */
function collectSuAApplicantData() {
    // Determine which applicant type is selected
    const individualRadio = document.querySelector('input[name="sua_applicant_type"][value="Individual"]');
    const corporateRadio = document.querySelector('input[name="sua_applicant_type"][value="Corporate"]');
    const multipleRadio = document.querySelector('input[name="sua_applicant_type"][value="Multiple"]');

    let applicantType = null;
    if (individualRadio && individualRadio.checked) {
        applicantType = 'individual';
    } else if (corporateRadio && corporateRadio.checked) {
        applicantType = 'corporate';
    } else if (multipleRadio && multipleRadio.checked) {
        applicantType = 'multiple';
    }

    if (!applicantType) {
        Swal.fire({
            icon: 'warning',
            title: 'Missing Information',
            text: 'Please select an applicant type',
            confirmButtonColor: '#f59e0b'
        });
        return null;
    }

    const data = {
        applicant_type: applicantType
    };

    /*
     | Applicant contact, for the ST commissioning SMS. This payload is
     | hand-built, so a new input on the blade sends NOTHING until it is named
     | here. phone_confirmed is set by public/js/sms-applicant-phone.js when the
     | officer has cleared the shared-number warning.
     */
    data.applicant_phone = document.getElementById('sua_applicant_phone')?.value || '';
    data.phone_confirmed = document.querySelector('#sua_applicant_phone ~ input[name="phone_confirmed"]')?.value === '1';

    // Collect data based on applicant type
    if (applicantType === 'individual') {
        data.applicant_title = document.getElementById('sua_title')?.value || '';
        data.first_name = document.getElementById('sua_first_name')?.value || '';
        data.middle_name = document.getElementById('sua_middle_name')?.value || '';
        data.surname = document.getElementById('sua_last_name')?.value || '';

        // Validate required fields for individual
        if (!data.first_name || !data.surname) {
            console.log('Individual validation failed:', { first_name: data.first_name, surname: data.surname });
            Swal.fire({
                icon: 'warning',
                title: 'Missing Information',
                text: 'Please fill in first name and last name for individual applicant',
                confirmButtonColor: '#f59e0b'
            });
            return null;
        }
    } else if (applicantType === 'corporate') {
        data.corporate_name = document.getElementById('sua_corporate_name')?.value || '';
        data.rc_number = document.getElementById('sua_rc_number')?.value || '';

        // Validate required fields for corporate
        if (!data.corporate_name) {
            console.log('Corporate validation failed:', { corporate_name: data.corporate_name });
            Swal.fire({
                icon: 'warning',
                title: 'Missing Information',
                text: 'Please fill in corporate name for corporate applicant',
                confirmButtonColor: '#f59e0b'
            });
            return null;
        }
    } else if (applicantType === 'multiple') {
        // For multiple applicants, we need at least the primary applicant info
        data.first_name = document.getElementById('sua_owner_first_name')?.value || '';
        data.middle_name = document.getElementById('sua_owner_middle_name')?.value || '';  
        data.surname = document.getElementById('sua_owner_last_name')?.value || '';

        // Validate required fields for multiple
        if (!data.first_name || !data.surname) {
            console.log('Multiple validation failed:', { first_name: data.first_name, surname: data.surname });
            Swal.fire({
                icon: 'warning',
                title: 'Missing Information',
                text: 'Please fill in first name and last name for primary owner',
                confirmButtonColor: '#f59e0b'
            });
            return null;
        }
    }

    console.log('Collected SuA applicant data:', data);
    return data;
}

// Generate SuA file numbers
async function generateSuaFileNumbers() {
    console.log('Generating SuA file numbers...');
    
    // Get the selected land use from the hidden input (populated by checkbox handler)
    const landUseHidden = document.getElementById('sua_land_use_hidden');
    const landUse = landUseHidden?.value;
    
    if (!landUse) {
        Swal.fire({
            icon: 'warning',
            title: 'Land Use Required',
            text: 'Please select a land use before commissioning SuA file numbers.',
            confirmButtonColor: '#f59e0b'
        });
        return;
    }

    // Collect applicant data
    const applicantData = collectSuAApplicantData();
    if (!applicantData) {
        return; // Error already shown in collection function
    }

    // Collect allocation information (Source / Entity / Reference No)
    const allocationData = collectSuaAllocationData();
    if (!allocationData) {
        return; // Error already shown in collection function
    }
    
    // Show loading state
    const button = document.querySelector('button[onclick="generateSuaFileNumbers()"]');
    const originalText = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '<i data-lucide="loader-2" class="inline-block h-5 w-5 mr-2 animate-spin"></i>Generating...';
    
    try {
        // Get application type (required field)
        const applicationTypeRadio = document.querySelector('input[name="application_type"]:checked');
        const applicationType = applicationTypeRadio ? applicationTypeRadio.value : '';
        
        if (!applicationType) {
            Swal.fire({
                icon: 'warning',
                title: 'Application Type Required',
                text: 'Please select an application type (Direct Allocation or Conversion) before commissioning.',
                confirmButtonColor: '#f59e0b'
            });
            button.disabled = false;
            button.innerHTML = originalText;
            return;
        }
        
        // Prepare request data
        const requestData = {
            land_use: landUse.toUpperCase(),
            application_type: applicationType, // REQUIRED: Application Type
            ...applicantData,
            ...allocationData,
            ...(window.STLocationMaps?.sua ? window.STLocationMaps.sua.getPayload() : {}),
            commissioned_by: document.getElementById('sua_commissioned_by')?.value || '',
            commissioned_date: document.getElementById('sua_commissioned_date')?.value || ''
        };

        console.log('SuA Request payload:', requestData);

        // Make API call to commission SuA file number
        const response = await fetch('/commission-new-st/commission-sua', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify(requestData)
        });
        
        const result = await response.json();
        
        if (result.success) {
            const fileNumber = result.suaFileNumber || result.data.sua_file_number;
            
            // Update the preview field
            const previewField = document.getElementById('sua_next_file_no');
            if (previewField) {
                previewField.textContent = fileNumber;
            }
            
            // Full summary card: which tables the commissioning wrote to, plus the
            // EDMS scan folder created for the file. See showStCommissioningCard().
            showStCommissioningCard(result, `SuA file number ${fileNumber} commissioned successfully!`);
            
            // Update button state
            button.innerHTML = '<i data-lucide="check" class="inline-block h-5 w-5 mr-2"></i>SuA File Number Generated';
            button.classList.add('bg-gray-400', 'cursor-not-allowed');
            button.classList.remove('bg-blue-500');
            
        } else {
            console.error('SuA Commission failed:', result);
            
            if (result.errors) {
                const errorMessages = Object.values(result.errors).flat();
                Swal.fire({
                    icon: 'error',
                    title: 'Validation Error',
                    text: errorMessages.join('\n'),
                    confirmButtonColor: '#ef4444'
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: result.message || 'Failed to commission SuA file number',
                    confirmButtonColor: '#ef4444'
                });
            }
            
            // Reset button
            button.disabled = false;
            button.innerHTML = originalText;
        }
        
    } catch (error) {
        console.error('Error commissioning SuA file number:', error);
        
        Swal.fire({
            icon: 'error',
            title: 'Network Error',
            text: 'Network error occurred while commissioning',
            confirmButtonColor: '#ef4444'
        });
        
        // Reset button
        button.disabled = false;
        button.innerHTML = originalText;
    }
    
    // Reset button after a delay
    setTimeout(() => {
        button.disabled = false;
        button.innerHTML = originalText;
        // Re-initialize Lucide icons
        if (window.lucide) {
            window.lucide.createIcons();
        }
    }, 2000);
}

// SuA Primary File Number Generation (adapted from sub_application.blade.php)
function generateSUAPrimaryFileNo(landUse) {
    console.log('🚀 Starting SuA file number generation for:', landUse);
    
    const landUseCode = {
        'Commercial': 'COM',
        'Industrial': 'IND',
        'Residential': 'RES',
        'Mixed-Use': 'MIX'
    }[landUse] || 'RES';
    
    console.log('📋 Land use code:', landUseCode);
    
    const currentYear = new Date().getFullYear();
    
    // Get CSRF token
    const csrfToken = document.querySelector('meta[name="csrf-token"]') || 
                     document.querySelector('input[name="_token"]');
    
    console.log('🔒 CSRF Token found:', !!csrfToken);
    
    const apiUrl = `/api/st-file-numbers/reserve-sua`;
    console.log('🌐 API URL:', apiUrl);
    
    // Prepare request data
    const requestData = {
        land_use: landUse,
        applicant_type: 'Individual', // Default, will be updated when form is filled
        applicant_title: null,
        first_name: null,
        surname: null,
        corporate_name: null,
        rc_number: null,
        multiple_owners_names: null
    };
    
    // Fetch next file number from server with proper headers
    fetch(apiUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken ? csrfToken.content || csrfToken.value : '',
            'X-Requested-With': 'XMLHttpRequest'
        },
        credentials: 'same-origin',
        body: JSON.stringify(requestData)
    })
        .then(response => {
            console.log('📡 Response status:', response.status);
            console.log('📡 Response ok:', response.ok);
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('📊 API Response data:', data);
            
            if (data.success) {
                const primaryFileNoInput = document.getElementById('sua_primary_fileno');
                const suaFileNoInput = document.getElementById('sua_fileno');
                const mlsFileNoInput = document.getElementById('mls_fileno');
                
                console.log('🔍 Found input fields:', {
                    primary: !!primaryFileNoInput,
                    sua: !!suaFileNoInput,
                    mls: !!mlsFileNoInput
                });
                
                // Use the new API response structure
                if (primaryFileNoInput && data.data && data.data.np_fileno) {
                    primaryFileNoInput.value = data.data.np_fileno;
                    console.log('✅ Set primary file no:', data.data.np_fileno);
                }
                if (suaFileNoInput && data.data && data.data.unit_fileno) {
                    suaFileNoInput.value = data.data.unit_fileno;
                    console.log('✅ Set SuA file no:', data.data.unit_fileno);
                }
                if (mlsFileNoInput && data.data && data.data.mls_fileno) {
                    mlsFileNoInput.value = data.data.mls_fileno;
                    console.log('✅ Set MLS file no:', data.data.mls_fileno);
                }
                
                console.log('✅ SuA file numbers generated successfully:', data);
                
                // Show success message
                showSuccessMessage('SuA file numbers generated successfully!');
            } else {
                console.error('❌ API returned success=false:', data.message || 'Unknown error');
                throw new Error(data.message || 'API returned success=false');
            }
        })
        .catch((error) => {
            console.error('❌ Failed to fetch SuA file numbers:', error);
            console.error('❌ Error details:', {
                message: error.message,
                stack: error.stack
            });
            
            // Show error message
            showErrorMessage('Error generating SuA file numbers: ' + error.message);
            
            // Fallback: Generate client-side numbers for demonstration
            console.log('🔄 Using fallback file number generation');
            const serial = Math.floor(Math.random() * 1000) + 1;
            const primaryFileNo = `ST-${landUseCode}-${currentYear}-${String(serial).padStart(4, '0')}`;
            
            const primaryFileNoInput = document.getElementById('sua_primary_fileno');
            const suaFileNoInput = document.getElementById('sua_fileno');
            const mlsFileNoInput = document.getElementById('mls_fileno');
            
            if (primaryFileNoInput) {
                primaryFileNoInput.value = primaryFileNo;
                console.log('🔄 Fallback primary file no:', primaryFileNo);
            }
            if (mlsFileNoInput) {
                mlsFileNoInput.value = primaryFileNo; // Same as primary for MLPP
                console.log('🔄 Fallback MLS file no:', primaryFileNo);
            }
            if (suaFileNoInput) {
                suaFileNoInput.value = primaryFileNo + '-U001'; // Unit specific
            }
            
            showErrorMessage('Using fallback file number generation. Please check server connection.');
        });
}

// Utility functions
function showSuccessMessage(message) {
    // Create a toast notification
    const toast = document.createElement('div');
    toast.className = 'fixed top-4 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg z-50 transition-all duration-300';
    toast.innerHTML = `
        <div class="flex items-center gap-2">
            <i data-lucide="check-circle" class="h-5 w-5"></i>
            <span>${message}</span>
        </div>
    `;
    
    document.body.appendChild(toast);
    
    // Initialize Lucide icons
    if (window.lucide) {
        window.lucide.createIcons();
    }
    
    // Remove after 3 seconds
    setTimeout(() => {
        toast.remove();
    }, 3000);
}

function showErrorMessage(message) {
    // Create a toast notification
    const toast = document.createElement('div');
    toast.className = 'fixed top-4 right-4 bg-red-500 text-white px-6 py-3 rounded-lg shadow-lg z-50 transition-all duration-300';
    toast.innerHTML = `
        <div class="flex items-center gap-2">
            <i data-lucide="alert-circle" class="h-5 w-5"></i>
            <span>${message}</span>
        </div>
    `;
    
    document.body.appendChild(toast);
    
    // Initialize Lucide icons
    if (window.lucide) {
        window.lucide.createIcons();
    }
    
    // Remove after 5 seconds
    setTimeout(() => {
        toast.remove();
    }, 5000);
}

// PUA File Number Generation Function
function generatePuaFileNumber() {
    console.log('Generating PUA file number...');
    
    // Get the parent file number from the input
    const parentFileNoInput = document.getElementById('pua_parent_fileno');
    const parentFileNo = parentFileNoInput?.value;
    
    if (!parentFileNo) {
        showErrorMessage('Please enter a parent file number before generating PUA file number.');
        return;
    }
    
    // Validate parent file number format (ST-XXX-YYYY-N)
    const fileNoPattern = /^ST-[A-Z]+-\d{4}-\d+$/;
    if (!fileNoPattern.test(parentFileNo)) {
        showErrorMessage('Invalid parent file number format. Expected: ST-XXX-YYYY-N');
        return;
    }
    
    // Show loading state
    const button = document.querySelector('button[onclick="generatePuaFileNumber()"]');
    const originalText = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '<i data-lucide="loader-2" class="inline-block h-5 w-5 mr-2 animate-spin"></i>Generating...';
    
    // Get CSRF token
    const csrfToken = document.querySelector('meta[name="csrf-token"]') || 
                     document.querySelector('input[name="_token"]');
    
    const apiUrl = `/commission-new-st/pua-next-fileno?parent_file_number=${encodeURIComponent(parentFileNo)}`;
    console.log('🌐 PUA API URL:', apiUrl);
    
    // Fetch PUA file number from server
    fetch(apiUrl, {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken ? csrfToken.content || csrfToken.value : '',
            'X-Requested-With': 'XMLHttpRequest'
        },
        credentials: 'same-origin'
    })
        .then(response => {
            console.log('📡 PUA Response status:', response.status);
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('📊 PUA API Response data:', data);
            
            if (data.success) {
                const npFileNoInput = document.getElementById('pua_np_fileno');
                const unitFileNoInput = document.getElementById('pua_unit_fileno');
                
                console.log('🔍 Found PUA input fields:', {
                    npFileno: !!npFileNoInput,
                    unitFileno: !!unitFileNoInput
                });
                
                if (npFileNoInput && data.npFileNo) {
                    npFileNoInput.value = data.npFileNo;
                    console.log('✅ Set NP file no:', data.npFileNo);
                }
                if (unitFileNoInput && data.unitFileNo) {
                    unitFileNoInput.value = data.unitFileNo;
                    console.log('✅ Set Unit file no:', data.unitFileNo);
                }
                
                console.log('✅ PUA file number generated successfully:', data);
                
                // Show success message
                showSuccessMessage('PUA file number generated successfully!');
            } else {
                console.error('❌ PUA API returned success=false:', data.message || 'Unknown error');
                throw new Error(data.message || 'API returned success=false');
            }
        })
        .catch((error) => {
            console.error('❌ Failed to fetch PUA file number:', error);
            
            // Show error message
            showErrorMessage('Error generating PUA file number: ' + error.message);
            
            // Fallback: Generate client-side number for demonstration
            console.log('🔄 Using fallback PUA file number generation');
            const unitSerial = Math.floor(Math.random() * 100) + 1;
            const unitFileNo = `${parentFileNo}-${String(unitSerial).padStart(3, '0')}`;
            
            const npFileNoInput = document.getElementById('pua_np_fileno');
            const unitFileNoInput = document.getElementById('pua_unit_fileno');
            
            if (npFileNoInput) {
                npFileNoInput.value = parentFileNo;
                console.log('🔄 Fallback NP file no:', parentFileNo);
            }
            if (unitFileNoInput) {
                unitFileNoInput.value = unitFileNo;
                console.log('🔄 Fallback Unit file no:', unitFileNo);
            }
            
            showErrorMessage('Using fallback file number generation. Please check server connection.');
        })
        .finally(() => {
            // Reset button
            setTimeout(() => {
                button.disabled = false;
                button.innerHTML = originalText;
                // Re-initialize Lucide icons
                if (window.lucide) {
                    window.lucide.createIcons();
                }
            }, 1000);
        });
}

/**
 * Handle SuA Application Type Change
 */
function handleSuaApplicationTypeChange(radioElement) {
    console.log('SuA Application Type changed to:', radioElement.value);
    
    // Remove 'selected' class from all application type options
    document.querySelectorAll('.sua-application-type-option').forEach(option => {
        option.classList.remove('selected');
    });
    
    // Add 'selected' class to parent label
    const label = radioElement.closest('.sua-application-type-option');
    if (label) {
        label.classList.add('selected');
    }
    
    console.log('✅ SuA Application Type set to:', radioElement.value);
}

/* =========================================================================
 * Allocation Information (SuA commissioning)
 *
 * Allocation Source / Entity / Slip No used to be typed on the Standalone Unit
 * Application form. They are now answered here, once, and back-filled into that
 * form when the SuA file number is selected.
 *
 * "Allocation Source" is the allocating institution itself, picked from the
 * shared allocation_source_lookups list (Government names and Other-Institution
 * names in one dropdown, grouped). The group it came from is posted alongside
 * it, because that is what decides which Addressee list the Confirmation Sheet
 * offers - the sheet asks only for the officer, never for the institution again.
 *
 * The legacy 'State Government' / 'Local Government' pair is still stored, but
 * it is derived server-side by AllocationSourceResolver::toLegacy() rather than
 * answered here.
 * ========================================================================= */

/** The sentinel every lookup-driven dropdown appends. Never stored as a name. */
const SUA_OTHERS_SPECIFY = 'OTHERS (SPECIFY)';

/** The one institution that needs a second answer: which council. */
const SUA_LOCAL_GOVERNMENT = 'LOCAL GOVERNMENT';

const SUA_ALLOCATION_ENTITIES = {
    'State Government': ['KSIP', 'HOUSING', 'KUNPDA', 'Other'],
    'Local Government': [
        'Ajingi', 'Albasu', 'Bagwai', 'Bebeji', 'Bichi', 'Bunkure', 'Dala', 'Dambatta',
        'Dawakin Kudu', 'Dawakin Tofa', 'Doguwa', 'Fagge', 'Gabasawa', 'Garko',
        'Garum Mallam', 'Gaya', 'Gezawa', 'Gwale', 'Gwarzo', 'Kabo', 'Kano Municipal',
        'Karaye', 'Kibiya', 'Kiru', 'Kumbotso', 'Kunchi', 'Kura', 'Madobi', 'Makoda',
        'Minjibir', 'Nasarawa', 'Rano', 'Rimin Gado', 'Rogo', 'Shanono', 'Sumaila',
        'Takai', 'Tarauni', 'Tofa', 'Tsanyawa', 'Tudun Wada', 'Ungogo', 'Warawa', 'Wudil'
    ]
};

window.SUA_ALLOCATION_ENTITIES = SUA_ALLOCATION_ENTITIES;

/** Reveal the "Specify LGA" box only while "Other" is the selection. */
function handleSuaAllocationEntityChange(selectElement) {
    const wrap = document.getElementById('sua_allocation_entity_other_wrap');
    if (!wrap) return;

    const isOther = selectElement.value === 'Other';
    wrap.classList.toggle('hidden', !isOther);
    if (!isOther) {
        const input = document.getElementById('sua_allocation_entity_other');
        if (input) input.value = '';
    }
}

/** The category of the selected institution: 'GOVERNMENT' or 'OTHER'. */
function suaSelectedInstitutionCategory() {
    const select = document.getElementById('sua_allocation_source');
    const option = select && select.selectedIndex >= 0 ? select.options[select.selectedIndex] : null;

    return (option && option.dataset.category) === 'OTHER' ? 'OTHER' : 'GOVERNMENT';
}

/**
 * Picking the institution decides the rest of the card: "Others (Specify)" opens
 * the name box, and LOCAL GOVERNMENT is the only choice that also asks which
 * LGA. Anything else names itself, so the LGA half is cleared and hidden rather
 * than left holding a stale answer.
 *
 */
function handleSuaAllocationSourceChange(selectElement) {
    const otherWrap = document.getElementById('sua_allocation_source_other_wrap');
    const otherInput = document.getElementById('sua_allocation_source_other');
    const entityWrap = document.getElementById('sua_allocation_entity_wrap');
    const entity = document.getElementById('sua_allocation_entity');

    const isOther = selectElement.value === SUA_OTHERS_SPECIFY;
    if (otherWrap) otherWrap.classList.toggle('hidden', !isOther);
    if (otherInput && !isOther) otherInput.value = '';

    const isLga = selectElement.value.toUpperCase() === SUA_LOCAL_GOVERNMENT;
    if (entityWrap) entityWrap.classList.toggle('hidden', !isLga);
    if (!entity) return;

    if (!isLga) {
        entity.value = '';
        handleSuaAllocationEntityChange(entity);
        return;
    }

    // Rebuilt each time, so a council typed under "Other" cannot survive a detour
    // through another institution.
    entity.innerHTML = '<option value="">Select LGA</option>';
    SUA_ALLOCATION_ENTITIES['Local Government'].concat('Other').forEach(name => {
        const option = document.createElement('option');
        option.value = name;
        option.textContent = name;
        entity.appendChild(option);
    });

    // The edit screen asks for a stored council before this list exists; apply it
    // now that it does. A name the list does not offer was typed under "Other".
    const pendingCouncil = entity.dataset.pendingValue;
    if (pendingCouncil) {
        const known = Array.prototype.some.call(entity.options, o => o.value === pendingCouncil);
        entity.value = known ? pendingCouncil : 'Other';

        if (!known) {
            const box = document.getElementById('sua_allocation_entity_other');
            if (box) box.value = pendingCouncil;
        }

        delete entity.dataset.pendingValue;
    }

    handleSuaAllocationEntityChange(entity);
}

/**
 * Fill the Allocation Source dropdown from allocation_source_lookups, grouped by
 * the two lists the registry keeps. Each group ends with "Others (Specify)", and
 * a name typed there is remembered server-side, so the lists are never hardcoded
 * here.
 *
 * A failed lookup still leaves both sentinels, which answer the card on their own.
 */
function setupSuaAllocationDropdowns() {
    const source = document.getElementById('sua_allocation_source');
    if (!source) return;

    const render = (lookups) => {
        const groups = [
            ['Government', 'GOVERNMENT', lookups.institution_government || []],
            ['Other Institutions', 'OTHER', lookups.institution_other || []]
        ];

        source.innerHTML = '<option value="">Select Allocation Source</option>';

        groups.forEach(([label, category, names]) => {
            const group = document.createElement('optgroup');
            group.label = label;

            names.concat(SUA_OTHERS_SPECIFY).forEach(name => {
                const option = document.createElement('option');
                option.value = name;
                option.textContent = name;
                option.dataset.category = category;
                group.appendChild(option);
            });

            source.appendChild(group);
        });

        // The edit screen fills the card before the lists arrive; re-apply what it
        // asked for, now that the option exists.
        const pending = source.dataset.pendingValue;
        if (pending) {
            source.value = pending;
            delete source.dataset.pendingValue;
        }

        source.dispatchEvent(new Event('change', { bubbles: true }));
    };

    fetch('/api/reference/allocation-source-lookups', {
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    })
        .then(response => (response.ok ? response.json() : null))
        .then(payload => render((payload && payload.data) || {}))
        .catch(() => render({}));
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', setupSuaAllocationDropdowns);
} else {
    setupSuaAllocationDropdowns();
}

/**
 * Collect + validate the Allocation Information answers.
 * Returns null (after showing the warning) when a required answer is missing.
 */
function collectSuaAllocationData() {
    const warn = (title, text) => {
        Swal.fire({ icon: 'warning', title, text, confirmButtonColor: '#f59e0b' });
        return null;
    };

    const selectedSource = document.getElementById('sua_allocation_source')?.value || '';
    const otherSource = (document.getElementById('sua_allocation_source_other')?.value || '').trim();
    const selectedEntity = document.getElementById('sua_allocation_entity')?.value || '';
    const otherEntity = (document.getElementById('sua_allocation_entity_other')?.value || '').trim();
    const allocationRefNo = document.getElementById('sua_allocation_ref_no')?.value || '';
    const allocationReferenceNo = document.getElementById('sua_allocation_reference_no')?.value || '';

    if (!selectedSource) {
        return warn('Allocation Source Required',
            'Please select the institution this unit was allocated by before commissioning.');
    }

    if (selectedSource === SUA_OTHERS_SPECIFY && !otherSource) {
        return warn('Specify the Institution',
            'You picked "Others (Specify)" as the Allocation Source - type the institution name before commissioning.');
    }

    // "Others (Specify)" is a picker convenience; what gets stored is the name.
    const institutionName = selectedSource === SUA_OTHERS_SPECIFY ? otherSource : selectedSource;
    const isLga = institutionName.toUpperCase() === SUA_LOCAL_GOVERNMENT;

    if (isLga && !selectedEntity) {
        return warn('LGA Required',
            'Please pick the LGA this unit was allocated by before commissioning.');
    }

    if (isLga && selectedEntity === 'Other' && !otherEntity) {
        return warn('Specify the LGA',
            'You picked "Other" as the LGA - type its name before commissioning.');
    }

    if (!allocationRefNo.trim()) {
        return warn('Allocation Slip No Required',
            'Enter the Allocation Slip No - it is printed on the SuA Confirmation Sheet.');
    }

    return {
        institution_category: suaSelectedInstitutionCategory(),
        institution_name: institutionName,
        // Only a council allocation has a second name; the server derives the
        // legacy allocation_source / allocation_entity pair from these two.
        allocation_entity: isLga ? (selectedEntity === 'Other' ? otherEntity : selectedEntity) : null,
        allocation_ref_no: allocationRefNo.trim().toUpperCase(),
        // A second, optional number: the allocation's own reference, kept apart
        // from the slip it was raised under.
        allocation_reference_no: allocationReferenceNo.trim().toUpperCase() || null
    };
}

// Make functions globally available  
window.handleSuaLandUseChange = handleSuaLandUseChange;
window.handleSuaApplicationTypeChange = handleSuaApplicationTypeChange;
window.generateSuaFileNumbers = generateSuaFileNumbers;
window.generateSUAPrimaryFileNo = generateSUAPrimaryFileNo;
window.generatePuaFileNumber = generatePuaFileNumber;
window.clearSuaFileNumbers = clearSuaFileNumbers;
window.setupSuaAllocationDropdowns = setupSuaAllocationDropdowns;
window.handleSuaAllocationSourceChange = handleSuaAllocationSourceChange;
window.handleSuaAllocationEntityChange = handleSuaAllocationEntityChange;
window.suaSelectedInstitutionCategory = suaSelectedInstitutionCategory;

console.log('🎉 SuA JavaScript module loaded successfully');
console.log('🔧 Available SuA and PuA functions:', {
    handleSuaLandUseChange: typeof window.handleSuaLandUseChange,
    handleSuaApplicationTypeChange: typeof window.handleSuaApplicationTypeChange,
    generateSuaFileNumbers: typeof window.generateSuaFileNumbers,
    generateSUAPrimaryFileNo: typeof window.generateSUAPrimaryFileNo,
    generatePuaFileNumber: typeof window.generatePuaFileNumber,
    clearSuaFileNumbers: typeof window.clearSuaFileNumbers
});