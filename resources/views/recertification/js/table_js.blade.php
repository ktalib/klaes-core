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
                <td colspan="8" class="text-center py-8">
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
                    <td colspan="8" class="text-center py-8">
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
    // <button onclick="captureExtantCofo(${app.id})" class="flex items-center w-full px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 gap-2">
    //                                 <i data-lucide="camera" class="h-4 w-4"></i>
    //                                 Capture Extant CofO Details
    //  </button>
    // Generate table rows with correct column alignment
    const rows = data.map(app => {
        const actionMenuId = `action-menu-${app.id}`;
        
        return `
            <tr class="table-row border-b hover:bg-gray-50">
                <td class="p-4">
                    <div class="font-medium text-blue-900 font-mono">${app.file_number || 'N/A'}</div>
                </td>
                <td class="p-4">
                    <div class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ${getApplicationTypeClass(app.applicant_type)}">
                        ${app.applicant_type || 'N/A'}
                    </div>
                </td>
                <td class="p-4">
                    <div class="font-medium text-gray-900">${app.applicant_name || 'N/A'}</div>
                </td>
                <td class="p-4">
                    <div class="text-gray-900">${app.plot_details || 'N/A'}</div>
                </td>
                <td class="p-4">
                    <div class="text-gray-900">${app.lga_name || 'N/A'}</div>
                </td>
                <td class="p-4">
                    <div class="text-gray-900">${app.created_at || 'N/A'}</div>
                </td>
                <td class="p-4">
                    ${renderAcknowledgementBadge(app.acknowledgement)}
                </td>
                <td class="p-4">
                    <div class="relative">
                        <button 
                            onclick="toggleActionMenu('${actionMenuId}')"
                            class="inline-flex items-center justify-center rounded-md font-medium text-sm px-3 py-2 transition-all cursor-pointer bg-transparent border border-gray-300 text-gray-700 hover:bg-gray-50"
                        >
                            <i data-lucide="more-horizontal" class="h-4 w-4"></i>
                        </button>
                        
                        <div id="${actionMenuId}" class="hidden absolute right-0 top-full mt-1 w-56 bg-white rounded-md shadow-lg border border-gray-200 z-50">
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
        if (!event.target.closest('.relative')) {
            document.querySelectorAll('[id^="action-menu-"]').forEach(menu => {
                menu.classList.add('hidden');
            });
        }
        
        // Close details modal
        if (event.target.id === 'details-modal') {
            closeDetailsModal();
        }
    });
    
    // Close details modal button
    const closeDetailsBtn = document.getElementById('close-details-modal');
    if (closeDetailsBtn) {
        closeDetailsBtn.addEventListener('click', closeDetailsModal);
    }
    
    // ESC key to close modals
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeDetailsModal();
            // Close all action menus
            document.querySelectorAll('[id^="action-menu-"]').forEach(menu => {
                menu.classList.add('hidden');
            });
        }
    });
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
    
    showToast('Capture Extant CofO Details feature coming soon', 'info');
}

function generateAcknowledgement(id) {
    console.log('Generating acknowledgement for application:', id);
    
    // Close action menu
    document.querySelectorAll('[id^="action-menu-"]').forEach(menu => {
        menu.classList.add('hidden');
    });

    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: 'Generate Acknowledgement?',
            text: 'This will mark the acknowledgement as generated for this application.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, Generate',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                showToast('Generating acknowledgement...', 'info');
                fetch(`/recertification/${id}/acknowledgement/generate`, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast('Acknowledgement generated', 'success');
                        const genBtn = document.querySelector(`button[data-action="generate-ack"][data-app-id="${id}"]`);
                        const viewBtn = document.querySelector(`button[data-action="view-ack"][data-app-id="${id}"]`);
                        if (genBtn) { genBtn.disabled = true; genBtn.classList.add('opacity-50','cursor-not-allowed'); }
                        if (viewBtn) { viewBtn.disabled = false; viewBtn.classList.remove('opacity-50','cursor-not-allowed'); }
                        loadApplicationsData();
                        const url = data.view_url || `/recertification/${id}/acknowledgement`;
                        window.open(url, '_blank');
                    } else {
                        showToast(data.message || 'Failed to generate acknowledgement', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error generating acknowledgement:', error);
                    showToast('Failed to generate acknowledgement', 'error');
                });
            }
        });
    } else {
        // Fallback without SweetAlert
        if (confirm('Generate acknowledgement for this application?')) {
            showToast('Generating acknowledgement...', 'info');
            fetch(`/recertification/${id}/acknowledgement/generate`, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                }
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    const genBtn = document.querySelector(`button[data-action="generate-ack"][data-app-id="${id}"]`);
                    const viewBtn = document.querySelector(`button[data-action="view-ack"][data-app-id="${id}"]`);
                    if (genBtn) { genBtn.disabled = true; genBtn.classList.add('opacity-50','cursor-not-allowed'); }
                    if (viewBtn) { viewBtn.disabled = false; viewBtn.classList.remove('opacity-50','cursor-not-allowed'); }
                    loadApplicationsData();
                    window.open(data.view_url || `/recertification/${id}/acknowledgement`, '_blank');
                } else {
                    showToast(data.message || 'Failed to generate acknowledgement', 'error');
                }
            })
            .catch(err => {
                console.error(err);
                showToast('Failed to generate acknowledgement', 'error');
            });
        }
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

window.open(`/recertification/${id}/acknowledgement`, '_blank');
}

// Modal Functions
function closeDetailsModal() {
    const modal = document.getElementById('details-modal');
    if (modal) {
        modal.classList.add('hidden');
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

// Make functions available globally
window.toggleActionMenu = toggleActionMenu;
window.viewApplicationDetails = viewApplicationDetails;
window.editApplication = editApplication;
window.deleteApplication = deleteApplication;
window.captureExtantCofo = captureExtantCofo;
window.generateAcknowledgement = generateAcknowledgement;
window.viewAcknowledgement = viewAcknowledgement;
window.removeToast = removeToast;
window.loadApplicationsData = loadApplicationsData;

console.log('Recertification table script initialized');
</script>