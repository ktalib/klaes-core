<script>
// Recertification Applications Table Management
let applicationsTable;
let applicationsData = [];

document.addEventListener('DOMContentLoaded', function() {
    console.log('Recertification table script loaded');
    
    // Initialize Lucide icons
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
    
    // Load applications data
    loadApplicationsData();
    
    // Setup search functionality
    setupSearch();
    
    // Setup modal handlers
    setupModalHandlers();
    
    // Setup OCR mode toggle
    setupOcrMode();
});

function loadApplicationsData() {
    console.log('Loading applications data...');
    
    // Show loading state
    const tableBody = document.getElementById('applications-table-body');
    const noResults = document.getElementById('no-results');
    const applicationsCount = document.getElementById('applications-count');
    
    if (tableBody) {
        tableBody.innerHTML = `
            <tr>
                <td colspan="12" class="text-center py-8">
                    <div class="loading-spinner mx-auto mb-2"></div>
                    <p class="text-gray-600">Loading applications...</p>
                </td>
            </tr>
        `;
    }
    
    // Fetch data from backend
    fetch('/recertification/data', {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        console.log('Applications data received:', data);
        applicationsData = data.data || [];
        
        // Update count
        if (applicationsCount) {
            applicationsCount.textContent = applicationsData.length;
        }
        
        // Render table
        renderApplicationsTable(applicationsData);
        
        // Hide no results initially
        if (noResults) {
            noResults.classList.add('hidden');
        }
    })
    .catch(error => {
        console.error('Error loading applications:', error);
        
        // Show error state
        if (tableBody) {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="12" class="text-center py-8">
                        <i data-lucide="alert-circle" class="h-8 w-8 text-red-500 mx-auto mb-2"></i>
                        <p class="text-red-600">Failed to load applications</p>
                        <button onclick="loadApplicationsData()" class="mt-2 text-blue-600 hover:text-blue-800">
                            Try Again
                        </button>
                    </td>
                </tr>
            `;
            
            // Reinitialize icons
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        }
    });
}

function getApplicationTypeClass(type) {
    switch(type) {
        case 'Individual':
            return 'bg-blue-100 text-blue-800';
        case 'Corporate':
            return 'bg-purple-100 text-purple-800';
        case 'Government Body':
            return 'bg-green-100 text-green-800';
        case 'Multiple Owners':
            return 'bg-orange-100 text-orange-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
}

function renderApplicationsTable(data) {
    const tableBody = document.getElementById('applications-table-body');
    const noResults = document.getElementById('no-results');
    
    if (!tableBody) return;
    
    if (!data || data.length === 0) {
        tableBody.innerHTML = '';
        if (noResults) {
            noResults.classList.remove('hidden');
        }
        return;
    }
    
    // Hide no results
    if (noResults) {
        noResults.classList.add('hidden');
    }
    
    // Generate table rows with correct column alignment
    const rows = data.map(app => {
        const actionMenuId = `action-menu-${app.id}`;
        
        return `
            <tr class="table-row border-b hover:bg-gray-50">
               
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm text-gray-900">${app.NewKANGISFileno || 'N/A'}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm text-gray-900">${app.kangisFileNo || 'N/A'}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm text-gray-900">${app.mlsfNo || 'N/A'}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm text-gray-900">${app.reg_no || 'N/A'}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ${getApplicationTypeClass(app.applicant_type)}">
                        ${app.applicant_type || 'N/A'}
                    </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm font-medium text-gray-900">${app.applicant_name || 'N/A'}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    ${renderLandUseBadge(app.current_land_use)}
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm text-gray-900">${app.plot_details || 'N/A'}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm text-gray-900">${app.lga_name || 'N/A'}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm text-gray-900">${app.created_at || 'N/A'}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    ${renderAcknowledgementBadge(app.acknowledgement)}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                    <div class="relative dropdown-container">
                        <button 
                            onclick="toggleActionMenu('${actionMenuId}')"
                            class="inline-flex items-center justify-center rounded-md font-medium text-sm px-3 py-2 transition-all cursor-pointer bg-transparent border border-gray-300 text-gray-700 hover:bg-gray-50"
                        >
                            <i data-lucide="more-horizontal" class="h-4 w-4"></i>
                        </button>
                        
                        <div id="${actionMenuId}" class="dropdown-menu hidden w-56 bg-white rounded-md shadow-xl border border-gray-200" style="position: fixed; z-index: 9999;">
                            <div class="py-1">
                                <button onclick="viewApplicationDetails(${app.id})" class="flex items-center w-full px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 gap-2">
                                    <i data-lucide="eye" class="h-4 w-4"></i>
                                    View Application Details
                                </button>
                                <button onclick="editApplication(${app.id})" class="flex items-center w-full px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 gap-2">
                                    <i data-lucide="edit" class="h-4 w-4"></i>
                                    Edit Application
                                </button>
                                <button onclick="deleteApplication(${app.id})" class="flex items-center w-full px-4 py-2 text-sm text-red-600 hover:bg-red-50 gap-2">
                                    <i data-lucide="trash-2" class="h-4 w-4"></i>
                                    Delete Application
                                </button>
                                <hr class="my-1">
                               
                                <button data-action="generate-ack" data-app-id="${app.id}" onclick="generateAcknowledgement(${app.id})" class="flex items-center w-full px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 gap-2 ${ (app.acknowledgement && app.acknowledgement.toLowerCase()==='generated') ? 'opacity-50 cursor-not-allowed' : '' }" ${ (app.acknowledgement && app.acknowledgement.toLowerCase()==='generated') ? 'disabled' : ''}>
                                    <i data-lucide="file-plus" class="h-4 w-4"></i>
                                    Generate Acknowledgement
                                </button>
                                <button data-action="view-ack" data-app-id="${app.id}" onclick="viewAcknowledgement(${app.id})" class="flex items-center w-full px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 gap-2 ${ !(app.acknowledgement && app.acknowledgement.toLowerCase()==='generated') ? 'opacity-50 cursor-not-allowed' : '' }" ${ !(app.acknowledgement && app.acknowledgement.toLowerCase()==='generated') ? 'disabled' : ''}>
                                    <i data-lucide="file-text" class="h-4 w-4"></i>
                                    View Acknowledgement
                                </button>
                                                            </div>
                        </div>
                    </div>
                </td>
            </tr>
        `;
    }).join('');
    
    tableBody.innerHTML = rows;
    
    // Reinitialize Lucide icons
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
}

function setupSearch() {
    const searchInput = document.getElementById('search-input');
    if (!searchInput) return;
    
    let searchTimeout;
    
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            const searchTerm = this.value.toLowerCase().trim();
            
            if (searchTerm === '') {
                renderApplicationsTable(applicationsData);
                return;
            }
            
            const filteredData = applicationsData.filter(app => {
                return (
                    (app.application_reference && app.application_reference.toLowerCase().includes(searchTerm)) ||
                    (app.applicant_name && app.applicant_name.toLowerCase().includes(searchTerm)) ||
                    (app.plot_details && app.plot_details.toLowerCase().includes(searchTerm)) ||
                    (app.lga_name && app.lga_name.toLowerCase().includes(searchTerm)) ||
                    (app.cofo_number && app.cofo_number.toLowerCase().includes(searchTerm)) ||
                    (app.file_number && app.file_number.toLowerCase().includes(searchTerm)) ||
                    (app.applicant_type && app.applicant_type.toLowerCase().includes(searchTerm))
                );
            });
            
            renderApplicationsTable(filteredData);
            
            // Update no results message
            const noResultsMessage = document.getElementById('no-results-message');
            if (noResultsMessage) {
                noResultsMessage.textContent = `No applications found matching "${searchTerm}"`;
            }
        }, 300);
    });
}

function setupModalHandlers() {
    // Close modal when clicking outside
    document.addEventListener('click', function(event) {
        // Close action menus when clicking outside
        if (!event.target.closest('.dropdown-container')) {
            document.querySelectorAll('[id^="action-menu-"]').forEach(menu => {
                menu.classList.add('hidden');
            });
        }
        
        // Close details modal
        if (event.target.id === 'details-modal') {
            closeDetailsModal();
        }
        
        // Close cofo serial modal
        if (event.target.id === 'cofo-serial-modal') {
            closeCofoSerialModal();
        }
    });
    
    // Close details modal button
    const closeDetailsBtn = document.getElementById('close-details-modal');
    if (closeDetailsBtn) {
        closeDetailsBtn.addEventListener('click', closeDetailsModal);
    }
    
    // New application modal button
    const newApplicationBtn = document.getElementById('new-application-btn');
    if (newApplicationBtn) {
        newApplicationBtn.addEventListener('click', function() {
            const modal = document.getElementById('new-recertification-modal');
            if (modal) {
                modal.style.display = 'flex';
                document.body.style.overflow = 'hidden';
            }
        });
    }
    
    // ESC key to close modals
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeDetailsModal();
            closeCofoSerialModal();
            // Close all action menus
            document.querySelectorAll('[id^="action-menu-"]').forEach(menu => {
                menu.classList.add('hidden');
            });
        }
    });
}

function setupOcrMode() {
    const ocrToggle = document.getElementById('ocr-mode-toggle');
    const backFromOcr = document.getElementById('back-from-ocr');
    
    if (ocrToggle) {
        ocrToggle.addEventListener('change', function() {
            toggleOcrMode(this.checked);
        });
    }
    
    if (backFromOcr) {
        backFromOcr.addEventListener('click', function() {
            toggleOcrMode(false);
            if (ocrToggle) ocrToggle.checked = false;
        });
    }
}

function toggleOcrMode(enabled) {
    const mainView = document.querySelector('.container');
    const ocrView = document.getElementById('ocr-mode-view');
    
    if (enabled) {
        if (mainView) mainView.style.display = 'none';
        if (ocrView) ocrView.classList.remove('hidden');
    } else {
        if (mainView) mainView.style.display = 'block';
        if (ocrView) ocrView.classList.add('hidden');
    }
}

// Action Menu Functions
function toggleActionMenu(menuId) {
    const menu = document.getElementById(menuId);
    if (!menu) return;

    // Close all other menus
    document.querySelectorAll('[id^="action-menu-"]').forEach(otherMenu => {
        if (otherMenu.id !== menuId) {
            otherMenu.classList.add('hidden');
        }
    });

    const button = menu.previousElementSibling;
    const rect = button.getBoundingClientRect();

    // Base styles for responsive, scrollable dropdown
    menu.style.position = 'fixed';
    menu.style.maxHeight = '70vh';
    menu.style.overflowY = 'auto';
    menu.style.zIndex = '9999';

    // Toggle show/hide with measurement
    const wasHidden = menu.classList.contains('hidden');
    if (wasHidden) {
        menu.classList.remove('hidden');
        menu.style.visibility = 'hidden';
    }

    // Default: open below the button, right-aligned
    const right = window.innerWidth - rect.right;
    menu.style.right = right + 'px';
    menu.style.left = 'auto';
    menu.style.top = (rect.bottom + 4) + 'px';
    menu.style.bottom = 'auto';

    // Measure and adjust if overflowing viewport bottom
    const margin = 8;
    const menuRect = menu.getBoundingClientRect();
    if (menuRect.bottom + margin > window.innerHeight) {
        // Open upwards
        const bottom = window.innerHeight - rect.top + 4;
        menu.style.top = 'auto';
        menu.style.bottom = bottom + 'px';
    }

    if (wasHidden) {
        menu.style.visibility = 'visible';
    } else {
        // If already visible, hide it
        menu.classList.add('hidden');
    }
}

// Application Action Functions
function viewApplicationDetails(id) {
    console.log('Viewing application details:', id);
    
    // Close action menu
    document.querySelectorAll('[id^="action-menu-"]').forEach(menu => {
        menu.classList.add('hidden');
    });
    
    // Navigate to application details page
    window.location.href = `/recertification/${id}/details`;
}

function editApplication(id) {
    console.log('Editing application:', id);
    
    // Close action menu
    document.querySelectorAll('[id^="action-menu-"]').forEach(menu => {
        menu.classList.add('hidden');
    });
    
    // Navigate to edit page
    window.location.href = `/recertification/${id}/edit`;
}

function deleteApplication(id) {
    console.log('Deleting application:', id);
    
    // Close action menu
    document.querySelectorAll('[id^="action-menu-"]').forEach(menu => {
        menu.classList.add('hidden');
    });
    
    // Find the application data for confirmation
    const app = applicationsData.find(a => a.id == id);
    const appName = app ? app.applicant_name : 'this application';
    
    if (!confirm(`Are you sure you want to delete the application for ${appName}? This action cannot be undone.`)) {
        return;
    }
    
    // Show loading toast
    showToast('Deleting application...', 'info');
    
    // Delete application
    fetch(`/recertification/${id}`, {
        method: 'DELETE',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            showToast('Application deleted successfully', 'success');
            // Reload data
            loadApplicationsData();
        } else {
            showToast(data.message || 'Failed to delete application', 'error');
        }
    })
    .catch(error => {
        console.error('Error deleting application:', error);
        showToast('Failed to delete application', 'error');
    });
}

function captureExtantCofo(id) {
    console.log('Capturing Extant CofO for application:', id);

    // Close action menu
    document.querySelectorAll('[id^="action-menu-"]').forEach(menu => {
        menu.classList.add('hidden');
    });

    // Find the application data for the row
    const app = applicationsData.find(a => a.id == id);
    if (!app) {
        showToast('Application data not found', 'error');
        return;
    }

    // Build applicant info
    let applicantType = (app.applicant_type || '').toLowerCase();
    let applicantData = null;
    if (applicantType === 'corporate') {
        applicantData = { corporate_name: app.applicant_name };
    } else if (applicantType === 'multiple owners' || applicantType === 'multiple') {
        applicantData = [];
    } else {
        // individual default: split by space to mimic title/first/middle/surname best effort
        const parts = (app.applicant_name || '').split(' ');
        applicantData = { applicant_title: parts[0] || '', first_name: parts[1] || '', middle_name: parts[2] || '', surname: parts.slice(3).join(' ') };
    }

    // Build property info
    const prop = {
        property_house_no: '',
        property_street_name: '',
        property_district: (app.plot_details || '').replace(/^Plot:\s*/i, ''),
        property_lga: app.lga_name || '',
        property_state: 'Kano',
        land_use: ''
    };

    // If CofO exists, do not open modal
    if (app.cofo_exists) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({ icon: 'info', title: 'Already captured', text: 'Extant CofO has already been captured for this file.' });
        } else {
            alert('Extant CofO already captured for this file.');
        }
        return;
    }

    // Open the modal
    if (typeof openCofoDetailsModal === 'function') {
        openCofoDetailsModal(id, app.file_number || '', '', applicantType, applicantData, prop);
    } else {
        showToast('CofO modal not available on this page', 'error');
    }
}

function generateAcknowledgement(id) {
    console.log('Generate Acknowledgement clicked for application:', id);
    // Close action menu
    document.querySelectorAll('[id^="action-menu-"]').forEach(menu => menu.classList.add('hidden'));
    // Open Title Document Status modal
    if (typeof openAckModal === 'function') {
        openAckModal(id);
    } else {
        if (typeof showToast === 'function') showToast('Acknowledgement modal not available on this page', 'error');
    }
}

function viewAcknowledgement(id) {
    console.log('Viewing acknowledgement for application:', id);
    
    // Close action menu
    document.querySelectorAll('[id^="action-menu-"]').forEach(menu => {
        menu.classList.add('hidden');
    });

    // Guard: only allow view when generated
    const app = applicationsData.find(a => a.id == id);
    const isGenerated = app && app.acknowledgement && app.acknowledgement.toLowerCase() === 'generated';
    if (!isGenerated) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'warning',
                title: 'Acknowledgement not generated',
                text: 'Please generate the acknowledgement first.'
            });
        } else {
            alert('Acknowledgement not generated. Please generate first.');
        }
        return;
    }

    // Open the acknowledgement view in a new tab
    window.open(`/recertification/${id}/acknowledgement`, '_blank');
}

function enterCofoSerialNumber(id) {
    console.log('Enter Cofo Serial Number for application:', id);
    console.log('Applications data:', applicationsData);
    
    // Close action menu
    document.querySelectorAll('[id^="action-menu-"]').forEach(menu => {
        menu.classList.add('hidden');
    });

    // Check if cofo_number already exists (and is not 'N/A')
    const app = applicationsData.find(a => a.id == id);
    console.log('Found application:', app);
    
    if (app && app.cofo_number && app.cofo_number !== 'N/A') {
        console.log('Application already has serial number:', app.cofo_number);
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'info',
                title: 'Serial Number Already Assigned',
                text: `This application already has Cofo Serial Number: ${app.cofo_number}`
            });
        } else {
            alert(`This application already has Cofo Serial Number: ${app.cofo_number}`);
        }
        return;
    }

    console.log('Opening Cofo Serial Modal for application ID:', id);
    // Open the modal
    openCofoSerialModal(id);
}

function openCofoSerialModal(applicationId) {
    console.log('openCofoSerialModal called with ID:', applicationId);
    
    // Find the Alpine component and call openModal
    const modalElement = document.querySelector('[x-data*="cofoSerialModal"]');
    console.log('Alpine modal element found:', modalElement);
    
    if (modalElement && modalElement._x_dataStack) {
        console.log('Calling Alpine openModal method');
        modalElement._x_dataStack[0].openModal(applicationId);
        
        // Force show the modal with direct DOM manipulation as backup
        setTimeout(() => {
            console.log('Forcing modal display as backup');
            modalElement.style.setProperty('display', 'flex', 'important');
            modalElement.style.setProperty('visibility', 'visible', 'important');
            modalElement.style.setProperty('opacity', '1', 'important');
            modalElement.style.setProperty('z-index', '9999', 'important');
            modalElement.style.setProperty('position', 'fixed', 'important');
            modalElement.style.setProperty('top', '0', 'important');
            modalElement.style.setProperty('left', '0', 'important');
            modalElement.style.setProperty('right', '0', 'important');
            modalElement.style.setProperty('bottom', '0', 'important');
            modalElement.classList.remove('hidden');
            
            document.body.style.overflow = 'hidden';
            
            // Reinitialize Lucide icons
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
            
            console.log('Modal forced to display');
        }, 100);
        
    } else {
        console.error('Alpine modal component not found or not initialized');
        alert('Modal not available. Please refresh the page and try again.');
    }
}

function closeCofoSerialModal() {
    // Try to close Alpine modal first
    const modalElement = document.querySelector('[x-data*="cofoSerialModal"]');
    if (modalElement && modalElement._x_dataStack) {
        modalElement._x_dataStack[0].closeModal();
    }
    
    // Also force hide with direct DOM manipulation
    if (modalElement) {
        modalElement.style.display = 'none';
        modalElement.classList.add('hidden');
    }
    
    document.body.style.overflow = 'auto';
}

function loadAvailableSerialNumbers() {
    const dropdown = document.getElementById('cofo-serial-dropdown');
    if (!dropdown) return;

    dropdown.innerHTML = '<option value="">Loading...</option>';

    // Fetch available serial numbers from backend
    fetch('/recertification/available-serial-numbers', {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        dropdown.innerHTML = '<option value="">Select a serial number...</option>';
        
        if (data.success && data.serialNumbers && data.serialNumbers.length > 0) {
            data.serialNumbers.forEach(serial => {
                const option = document.createElement('option');
                option.value = serial;
                option.textContent = serial;
                dropdown.appendChild(option);
            });
        } else {
            dropdown.innerHTML = '<option value="">No available serial numbers</option>';
        }
    })
    .catch(error => {
        console.error('Error loading serial numbers:', error);
        dropdown.innerHTML = '<option value="">Error loading serial numbers</option>';
        showToast('Failed to load available serial numbers', 'error');
    });
}

function submitCofoSerial() {
    const form = document.getElementById('cofo-serial-form');
    const formData = new FormData(form);
    const applicationId = formData.get('application_id');
    const serialNumber = formData.get('serial_number');

    if (!serialNumber) {
        showToast('Please select a serial number', 'warning');
        return;
    }

    // Show loading state
    const submitBtn = document.getElementById('cofo-serial-submit-btn');
    const originalText = submitBtn.textContent;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<div class="loading-spinner mr-2"></div>Updating...';

    // Submit to backend
    fetch('/recertification/assign-serial-number', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
        },
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            showToast('Cofo Serial Number assigned successfully', 'success');
            closeCofoSerialModal();
            // Reload applications data to reflect changes
            loadApplicationsData();
        } else {
            showToast(data.message || 'Failed to assign serial number', 'error');
        }
    })
    .catch(error => {
        console.error('Error assigning serial number:', error);
        showToast('Failed to assign serial number', 'error');
    })
    .finally(() => {
        // Reset button state
        submitBtn.disabled = false;
        submitBtn.textContent = originalText;
    });
}

// Modal Functions (kept for backward compatibility)
function closeDetailsModal() {
    const modal = document.getElementById('details-modal');
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = 'auto';
    }
}

// Toast notification function
function showToast(message, type = 'info') {
    const toastContainer = document.getElementById('toast-container');
    if (!toastContainer) return;
    
    const toastId = `toast-${Date.now()}`;
    
    const typeClasses = {
        success: 'bg-green-600 text-white',
        error: 'bg-red-600 text-white',
        warning: 'bg-yellow-600 text-white',
        info: 'bg-blue-600 text-white'
    };
    
    const typeIcons = {
        success: 'check-circle',
        error: 'alert-circle',
        warning: 'alert-triangle',
        info: 'info'
    };
    
    const toast = document.createElement('div');
    toast.id = toastId;
    toast.className = `${typeClasses[type]} px-4 py-2 rounded-md shadow-lg flex items-center gap-2 transform translate-x-full transition-transform duration-300`;
    toast.innerHTML = `
        <i data-lucide="${typeIcons[type]}" class="h-4 w-4"></i>
        <span>${message}</span>
        <button onclick="removeToast('${toastId}')" class="ml-2 hover:bg-black/20 rounded p-1">
            <i data-lucide="x" class="h-3 w-3"></i>
        </button>
    `;
    
    toastContainer.appendChild(toast);
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
    
    // Animate in
    setTimeout(() => {
        toast.classList.remove('translate-x-full');
    }, 100);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        removeToast(toastId);
    }, 5000);
}

function removeToast(toastId) {
    const toast = document.getElementById(toastId);
    if (toast) {
        toast.classList.add('translate-x-full');
        setTimeout(() => {
            toast.remove();
        }, 300);
    }
}

function renderAcknowledgementBadge(status) {
    const s = (status || '').toLowerCase();
    if (s === 'generated') {
        return '<span class="badge badge-success">Generated</span>';
    }
    return '<span class="badge badge-default">Pending</span>';
}

function renderLandUseBadge(landUse) {
    if (!landUse || landUse === 'N/A') {
        return '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800">N/A</span>';
    }
    
    // Capitalize first letter of each word
    const capitalizedLandUse = landUse.toLowerCase().replace(/\b\w/g, l => l.toUpperCase());
    
    // Determine badge color based on land use type
    let badgeClass = '';
    const lowerLandUse = landUse.toLowerCase();
    
    if (lowerLandUse.includes('residential')) {
        badgeClass = 'bg-blue-100 text-blue-800';
    } else if (lowerLandUse.includes('commercial')) {
        badgeClass = 'bg-green-100 text-green-800';
    } else if (lowerLandUse.includes('industrial')) {
        badgeClass = 'bg-purple-100 text-purple-800';
    } else if (lowerLandUse.includes('agricultural') || lowerLandUse.includes('farming')) {
        badgeClass = 'bg-yellow-100 text-yellow-800';
    } else if (lowerLandUse.includes('institutional') || lowerLandUse.includes('educational')) {
        badgeClass = 'bg-indigo-100 text-indigo-800';
    } else if (lowerLandUse.includes('recreational') || lowerLandUse.includes('park')) {
        badgeClass = 'bg-emerald-100 text-emerald-800';
    } else if (lowerLandUse.includes('mixed')) {
        badgeClass = 'bg-orange-100 text-orange-800';
    } else {
        badgeClass = 'bg-gray-100 text-gray-800';
    }
    
    return `<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ${badgeClass}">${capitalizedLandUse}</span>`;
}

// Make functions available globally
window.toggleActionMenu = toggleActionMenu;
window.viewApplicationDetails = viewApplicationDetails;
window.editApplication = editApplication;
window.deleteApplication = deleteApplication;
window.captureExtantCofo = captureExtantCofo;
window.generateAcknowledgement = generateAcknowledgement;
window.viewAcknowledgement = viewAcknowledgement;
window.enterCofoSerialNumber = enterCofoSerialNumber;
window.openCofoSerialModal = openCofoSerialModal;
window.closeCofoSerialModal = closeCofoSerialModal;
window.submitCofoSerial = submitCofoSerial;
window.removeToast = removeToast;
window.loadApplicationsData = loadApplicationsData;

console.log('Recertification table script initialized');
</script>