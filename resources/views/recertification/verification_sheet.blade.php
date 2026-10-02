@extends('layouts.app')
@section('page-title')
    {{ __($PageTitle) }}
@endsection

@section('content')
<script>
// Tailwind config
tailwind.config = {
  theme: {
    extend: { 
      colors: {
        primary: '#3b82f6',
        'primary-foreground': '#ffffff',
        muted: '#f3f4f6',
        'muted-foreground': '#6b7280',
        border: '#e5e7eb',
        destructive: '#ef4444',
        'destructive-foreground': '#ffffff',
        secondary: '#f1f5f9',
        'secondary-foreground': '#0f172a',
      }
    }
  }
}
</script>

<style>
/* Custom styles */
.modal-backdrop {
  background-color: rgba(0, 0, 0, 0.5);
  backdrop-filter: blur(4px);
}

.badge {
  display: inline-flex;
  align-items: center;
  border-radius: 9999px;
  padding: 0.25rem 0.75rem;
  font-size: 0.75rem;
  font-weight: 500;
}

.badge-success {
  background-color: #dcfce7;
  color: #166534;
}

.badge-default {
  background-color: #f3f4f6;
  color: #374151;
}

/* Table hover effects */
.table-row:hover {
  background-color: rgba(0, 0, 0, 0.025);
}

/* Loading spinner */
.loading-spinner {
  width: 1rem;
  height: 1rem;
  border: 2px solid #e5e7eb;
  border-top: 2px solid #3b82f6;
  border-radius: 50%;
  animation: spin 1s linear infinite;
}

@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

/* Fade in animation */
.fade-in {
  animation: fadeIn 0.3s ease-in-out;
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(-10px); }
  to { opacity: 1; transform: translateY(0); }
}
</style>

<div class="flex-1 overflow-auto">
    <!-- Header -->
    @include('admin.header')
    
    <!-- Main Content -->
    <div class="p-6">
        <div class="container mx-auto py-6 space-y-6 max-w-7xl px-4 sm:px-6 lg:px-8">
            
            <!-- Header -->
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">{{ $PageTitle }}</h1>
                    <p class="text-gray-600">{{ $PageDescription }}</p>
                </div>
                <div class="flex gap-3">
                    <a href="{{ route('recertification.index') }}" class="inline-flex items-center justify-center rounded-md font-medium text-sm px-4 py-2 transition-all cursor-pointer bg-transparent border border-gray-300 text-gray-700 hover:bg-gray-50 gap-2">
                        <i data-lucide="arrow-left" class="h-4 w-4"></i>
                        Back to Applications
                    </a>
                </div>
            </div>

            <!-- Verification Sheet Table -->
            <div class="bg-white rounded-lg shadow border border-gray-200">
                <div class="p-6 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h3 class="text-xl font-semibold text-gray-900 flex items-center gap-2">
                            <i data-lucide="clipboard-check" class="h-5 w-5 text-green-600"></i>
                            Verification Sheet (<span id="applications-count">0</span>)
                        </h3>
                        <span class="badge badge-success">
                            Applications ready for verification
                        </span>
                    </div>
                </div>
                
                <div class="rounded-md border border-gray-200" id="applications-table-container">
                    <div class="overflow-x-auto">
                        <!-- Table -->
                        <table class="min-w-full divide-y divide-gray-200" id="verification-sheet-table">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">
                                        New KANGIS FileNo
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">
                                        KANGIS FileNo
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">
                                        MLPP FileNo
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">
                                        RegNo
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">
                                        Application Type
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">
                                        Applicant Name
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">
                                        Land Use
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">
                                        Plot Details
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">
                                        LGA
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">
                                        Application Date
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">
                                        Verification Status
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody id="applications-table-body" class="bg-white divide-y divide-gray-200">
                                <!-- Applications will be loaded dynamically -->
                            </tbody>
                        </table>
                        
                        <!-- No results state -->
                        <div id="no-results" class="hidden text-center py-12">
                            <i data-lucide="file-text" class="h-12 w-12 text-gray-400 mx-auto mb-4"></i>
                            <h3 class="text-lg font-medium mb-2 text-gray-900">No applications found</h3>
                            <p id="no-results-message" class="text-gray-600">
                                No applications available for verification
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    @include('admin.footer')
</div>

<!-- Toast Notifications -->
<div id="toast-container" class="fixed top-4 right-4 z-50 space-y-2">
    <!-- Toast messages will be inserted here -->
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// Verification Sheet Applications Table Management
let applicationsData = [];

document.addEventListener('DOMContentLoaded', function() {
    console.log('Verification sheet table script loaded');
    
    // Initialize Lucide icons
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
    
    // Load applications data
    loadVerificationData();
    
    // Setup modal handlers
    setupModalHandlers();
});

function loadVerificationData() {
    console.log('Loading verification data...');
    
    // Show loading state
    const tableBody = document.getElementById('applications-table-body');
    const noResults = document.getElementById('no-results');
    const applicationsCount = document.getElementById('applications-count');
    
    if (tableBody) {
        tableBody.innerHTML = `
            <tr>
                <td colspan="12" class="text-center py-8">
                    <div class="loading-spinner mx-auto mb-2"></div>
                    <p class="text-gray-600">Loading verification data...</p>
                </td>
            </tr>
        `;
    }
    
    // Fetch data from backend
    fetch('{{ route("recertification.verification-data") }}', {
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
        console.log('Verification data received:', data);
        applicationsData = data.data || [];
        
        // Update count
        if (applicationsCount) {
            applicationsCount.textContent = applicationsData.length;
        }
        
        // Render table
        renderVerificationTable(applicationsData);
        
        // Hide no results initially
        if (noResults) {
            noResults.classList.add('hidden');
        }
    })
    .catch(error => {
        console.error('Error loading verification data:', error);
        
        // Show error state
        if (tableBody) {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="12" class="text-center py-8">
                        <i data-lucide="alert-circle" class="h-8 w-8 text-red-500 mx-auto mb-2"></i>
                        <p class="text-red-600">Failed to load verification data</p>
                        <button onclick="loadVerificationData()" class="mt-2 text-blue-600 hover:text-blue-800">
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

function getVerificationStatusBadge(status) {
    let label = 'Pending';
    if (typeof status === 'string') {
        label = status.trim() || 'Pending';
    } else if (status === true || status === 1) {
        label = 'Verified';
    }
    const isVerified = label.toLowerCase() === 'verified';
    if (isVerified) {
        return `<span class="badge badge-success">Verified</span>`;
    }
    return `<span class="badge badge-default">Pending</span>`;
}

// Land use badge rendering function
function renderLandUseBadge(landUse) {
    const raw = (landUse ?? '').toString().trim();
    const lower = raw.toLowerCase();

    // Normalize empty/NA-like values
    if (!raw || ['n/a', 'n./a', 'na', '-', 'null', 'undefined'].includes(lower)) {
        return '<div class="text-sm text-gray-500">N/A</div>';
    }

    // Canonical label mapping (case-insensitive)
    const labelMap = {
        'residential': 'Residential',
        'commercial': 'Commercial',
        'industrial': 'Industrial',
        'agricultural': 'Agricultural',
        'mixed use': 'Mixed Use',
        'mixed-use': 'Mixed Use',
        'institutional': 'Institutional',
        'recreational': 'Recreational'
    };
    const label = labelMap[lower] || raw;

    // Define land use colors (by canonical label)
    const landUseColors = {
        'Residential': 'bg-blue-100 text-blue-800',
        'Commercial': 'bg-green-100 text-green-800',
        'Industrial': 'bg-orange-100 text-orange-800',
        'Agricultural': 'bg-yellow-100 text-yellow-800',
        'Mixed Use': 'bg-purple-100 text-purple-800',
        'Institutional': 'bg-indigo-100 text-indigo-800',
        'Recreational': 'bg-pink-100 text-pink-800'
    };

    const colorClass = landUseColors[label] || 'bg-gray-100 text-gray-800';

    return `<div class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ${colorClass}">${label}</div>`;
}

function renderVerificationTable(data) {
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
        const isVerified = (typeof app.verification === 'string' && app.verification.toLowerCase() === 'verified') || app.verification === true || app.verification === 1;
        
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
                    ${renderLandUseBadge(app.current_land_use || app.land_use || app.landUse || app.landUseType)}
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
                    ${getVerificationStatusBadge(app.verification)}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
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
                                    View Application
                                </button>
                                
                                ${!isVerified ? `
                                <button onclick="generateVerificationSheet(${app.id})" class="flex items-center w-full px-4 py-2 text-sm text-blue-700 hover:bg-blue-50 gap-2">
                                    <i data-lucide="file-plus" class="h-4 w-4"></i>
                                    Generate Verification Sheet
                                </button>
                                ` : `
                                <button onclick="generateVerificationSheet(${app.id})" class="flex items-center w-full px-4 py-2 text-sm text-gray-400 cursor-not-allowed gap-2" disabled>
                                    <i data-lucide="file-plus" class="h-4 w-4"></i>
                                    Generate Verification Sheet
                                </button>
                                `}
                                
                                ${isVerified ? `
                                <button onclick="viewVerificationSheet(${app.id})" class="flex items-center w-full px-4 py-2 text-sm text-green-700 hover:bg-green-50 gap-2">
                                    <i data-lucide="file-text" class="h-4 w-4"></i>
                                    View Verification Sheet
                                </button>
                                ` : `
                                <button onclick="viewVerificationSheet(${app.id})" class="flex items-center w-full px-4 py-2 text-sm text-gray-400 cursor-not-allowed gap-2" disabled>
                                    <i data-lucide="file-text" class="h-4 w-4"></i>
                                    View Verification Sheet
                                </button>
                                `}
                                
                                ${!isVerified ? `
                                <button onclick="markAsVerified(${app.id})" class="flex items-center w-full px-4 py-2 text-sm text-green-700 hover:bg-green-50 gap-2">
                                    <i data-lucide="check-circle" class="h-4 w-4"></i>
                                    Mark as Verified
                                </button>
                                ` : ''}
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

function setupModalHandlers() {
    // Close modal when clicking outside
    document.addEventListener('click', function(event) {
        // Close action menus when clicking outside
        if (!event.target.closest('.relative')) {
            document.querySelectorAll('[id^="action-menu-"]').forEach(menu => {
                menu.classList.add('hidden');
            });
        }
    });
    
    // ESC key to close modals
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
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
    
    // Toggle current menu
    menu.classList.toggle('hidden');
    
    // Position menu correctly
    if (!menu.classList.contains('hidden')) {
        const button = menu.previousElementSibling;
        const buttonRect = button.getBoundingClientRect();
        const menuRect = menu.getBoundingClientRect();
        const viewportHeight = window.innerHeight;
        const viewportWidth = window.innerWidth;
        
        // Reset positioning
        menu.style.position = 'fixed';
        menu.style.top = '';
        menu.style.bottom = '';
        menu.style.left = '';
        menu.style.right = '';
        
        // Calculate position
        let top = buttonRect.bottom + 4;
        let left = buttonRect.right - 224; // 224px = w-56 (14rem * 16px)
        
        // Adjust if menu goes outside viewport
        if (top + menuRect.height > viewportHeight) {
            top = buttonRect.top - menuRect.height - 4;
        }
        
        if (left < 8) {
            left = buttonRect.left;
        }
        
        if (left + 224 > viewportWidth) {
            left = viewportWidth - 224 - 8;
        }
        
        menu.style.top = `${top}px`;
        menu.style.left = `${left}px`;
        menu.style.zIndex = '1000';
    }
}

// Application Action Functions
function generateVerificationSheet(id) {
    console.log('Generating verification sheet for application:', id);
    
    // Close action menus
    document.querySelectorAll('[id^="action-menu-"]').forEach(menu => {
        menu.classList.add('hidden');
    });
    
    // Open verification template (printable) in new tab
    window.open(`/recertification/${id}/verification`, '_blank');
}

function viewVerificationSheet(id) {
    console.log('Viewing verification sheet for application:', id);
    
    // Close action menus
    document.querySelectorAll('[id^="action-menu-"]').forEach(menu => {
        menu.classList.add('hidden');
    });
    
    // Open verification sheet in new tab
    window.open(`/recertification/${id}/verification`, '_blank');
}

function viewApplicationDetails(id) {
    console.log('Viewing application details:', id);
    
    // Close action menu
    document.querySelectorAll('[id^="action-menu-"]').forEach(menu => {
        menu.classList.add('hidden');
    });
    
    // Navigate to application details page
    window.location.href = `/recertification/${id}/details`;
}

function verifyUrl(id) {
    return `{{ url('/recertification') }}/${id}/verify`;
}

function markAsVerified(id) {
    // Close action menus
    document.querySelectorAll('[id^="action-menu-"]').forEach(menu => menu.classList.add('hidden'));

    if (typeof Swal === 'undefined') {
        if (!confirm('Mark this application as Verified?')) return;
        return submitVerification(id);
    }
    Swal.fire({
        title: 'Generate Verification?',
        
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, verify',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            submitVerification(id);
        }
    });
}

function submitVerification(id) {
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    fetch(verifyUrl(id), {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...(token ? { 'X-CSRF-TOKEN': token } : {})
        },
        body: JSON.stringify({ verification: 'Verified' })
    })
    .then(response => {
        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
        return response.json().catch(() => ({}));
    })
    .then(() => {
        if (typeof Swal !== 'undefined') {
            Swal.fire('Updated', 'Verification set to Verified', 'success');
        } else {
            showToast('Verification set to Verified', 'success');
        }
        loadVerificationData();
    })
    .catch(error => {
        console.error('Error updating verification:', error);
        if (typeof Swal !== 'undefined') {
            Swal.fire('Error', 'Failed to update verification status', 'error');
        } else {
            showToast('Failed to update verification status', 'error');
        }
    });
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

// Make functions available globally
window.toggleActionMenu = toggleActionMenu;
window.viewApplicationDetails = viewApplicationDetails;
window.generateVerificationSheet = generateVerificationSheet;
window.viewVerificationSheet = viewVerificationSheet;
window.removeToast = removeToast;
window.loadVerificationData = loadVerificationData;
window.markAsVerified = markAsVerified;
window.renderLandUseBadge = renderLandUseBadge;

console.log('Verification sheet table script initialized');
</script>

@endsection