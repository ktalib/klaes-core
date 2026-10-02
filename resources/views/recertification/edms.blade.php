@extends('layouts.app')
@section('page-title')
    {{ __('EDMS - Electronic Document Management System') }}
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

.badge-warning {
  background-color: #fef3c7;
  color: #92400e;
}

.badge-info {
  background-color: #dbeafe;
  color: #1e40af;
}

.badge-purple {
  background-color: #f3e8ff;
  color: #7c3aed;
}

.badge-default {
  background-color: #f3f4f6;
  color: #374151;
}

/* Table hover effects */
.table-row:hover {
  background-color: rgba(0, 0, 0, 0.025);
}

/* Responsive table styling */
.table-container {
  max-width: 100%;
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
}

.table-container table {
  min-width: 1200px;
}

/* Mobile responsiveness */
@media (max-width: 768px) {
  .table-container th,
  .table-container td {
    padding: 0.5rem;
    font-size: 0.875rem;
  }
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
                    <h1 class="text-3xl font-bold text-gray-900">EDMS - Electronic Document Management</h1>
                    <p class="text-gray-600">Manage digital documents and file indexing for recertification applications</p>
                </div>
                <div class="flex gap-3">
                    <a href="{{ route('recertification.index') }}" class="inline-flex items-center justify-center rounded-md font-medium text-sm px-4 py-2 transition-all cursor-pointer bg-transparent border border-gray-300 text-gray-700 hover:bg-gray-50 gap-2">
                        <i data-lucide="arrow-left" class="h-4 w-4"></i>
                        Back to Applications
                    </a>
                </div>
            </div>

            <!-- Statistics -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="bg-white rounded-lg shadow border border-gray-200 p-6">
                    <div class="flex items-center">
                        <div class="p-2 bg-blue-100 rounded-lg">
                            <i data-lucide="hard-drive" class="h-6 w-6 text-blue-600"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Total Documents</p>
                            <p class="text-2xl font-bold text-gray-900" id="total-count">0</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow border border-gray-200 p-6">
                    <div class="flex items-center">
                        <div class="p-2 bg-green-100 rounded-lg">
                            <i data-lucide="check-circle" class="h-6 w-6 text-green-600"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Digitized</p>
                            <p class="text-2xl font-bold text-gray-900" id="digitized-count">0</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow border border-gray-200 p-6">
                    <div class="flex items-center">
                        <div class="p-2 bg-yellow-100 rounded-lg">
                            <i data-lucide="clock" class="h-6 w-6 text-yellow-600"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Pending Digitization</p>
                            <p class="text-2xl font-bold text-gray-900" id="pending-count">0</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow border border-gray-200 p-6">
                    <div class="flex items-center">
                        <div class="p-2 bg-purple-100 rounded-lg">
                            <i data-lucide="calendar" class="h-6 w-6 text-purple-600"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">This Month</p>
                            <p class="text-2xl font-bold text-gray-900" id="month-count">0</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Search and Filters -->
            <div class="bg-white rounded-lg shadow border border-gray-200">
                <div class="p-6">
                    <div class="flex gap-4 items-center">
                        <div class="relative flex-1">
                            <i data-lucide="search" class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 h-4 w-4"></i>
                            <input
                                id="search-input"
                                type="text"
                                placeholder="Search by applicant name, file number, document type..."
                                class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-md text-sm transition-all focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10"
                            />
                        </div>
                        <button class="inline-flex items-center justify-center rounded-md font-medium text-sm px-3 py-2 transition-all cursor-pointer bg-transparent border border-gray-300 text-gray-700 hover:bg-gray-50">
                            <i data-lucide="filter" class="h-4 w-4"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- EDMS Table -->
            <div class="bg-white rounded-lg shadow border border-gray-200">
                <div class="p-6 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h3 class="text-xl font-semibold text-gray-900 flex items-center gap-2">
                            <i data-lucide="hard-drive" class="h-5 w-5 text-blue-600"></i>
                            EDMS Documents (<span id="applications-count">0</span>)
                        </h3>
                        <span class="badge badge-info">
                            Electronic Document Management
                        </span>
                    </div>
                </div>
                
                <div class="rounded-md border-t-0" id="edms-table-container">
                    <div class="p-6">
                        <!-- Table -->
                        <div class="table-container overflow-x-auto">
                            <table class="w-full" id="edms-table" style="min-width: 1200px;">
                                <thead>
                                    <tr class="border-b bg-gray-50">
                                        
                                        <th class="text-left p-2 font-medium text-gray-700 text-xs" style="min-width: 140px;">New KANGIS FileNo</th>
                                        <th class="text-left p-2 font-medium text-gray-700 text-xs" style="min-width: 120px;">KANGIS FileNo</th>
                                        <th class="text-left p-2 font-medium text-gray-700 text-xs" style="min-width: 100px;">MLPP FileNo</th>
                                        <th class="text-left p-2 font-medium text-gray-700 text-xs" style="min-width: 100px;">RegNo</th>
                                        <th class="text-left p-2 font-medium text-gray-700 text-xs" style="min-width: 120px;">Type</th>
                                        <th class="text-left p-2 font-medium text-gray-700 text-xs" style="min-width: 180px;">Applicant Name</th>
                                        <th class="text-left p-2 font-medium text-gray-700 text-xs" style="min-width: 120px;">Land Use</th>
                                        <th class="text-left p-2 font-medium text-gray-700 text-xs" style="min-width: 200px;">Plot Details</th>
                                        <th class="text-left p-2 font-medium text-gray-700 text-xs" style="min-width: 120px;">Document Status</th>
                                        <th class="text-left p-2 font-medium text-gray-700 text-xs" style="min-width: 120px;">Last Updated</th>
                                        <th class="text-left p-2 font-medium text-gray-700 text-xs" style="min-width: 120px;">EDMS Status</th>
                                        <th class="text-left p-2 font-medium text-gray-700 text-xs" style="min-width: 100px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="edms-table-body">
                                    <!-- Applications will be loaded dynamically -->
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- No results state -->
                        <div id="no-results" class="hidden text-center py-12">
                            <i data-lucide="hard-drive" class="h-12 w-12 text-gray-400 mx-auto mb-4"></i>
                            <h3 class="text-lg font-medium mb-2 text-gray-900">No documents found</h3>
                            <p id="no-results-message" class="text-gray-600">
                                No documents available in EDMS
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

<script>
// EDMS Table Management
let edmsData = [];

document.addEventListener('DOMContentLoaded', function() {
    console.log('EDMS table script loaded');
    
    // Initialize Lucide icons
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
    
    // Load EDMS data
    loadEDMSData();
    
    // Setup search functionality
    setupSearch();
    
    // Setup modal handlers
    setupModalHandlers();
});

function loadEDMSData() {
    console.log('Loading EDMS data...');
    
    // Show loading state
    showLoadingState('edms-table-body');
    
    // Fetch data from backend
    fetch('/recertification/edms-data', {
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
        console.log('EDMS data received:', data);
        edmsData = data.data || [];
        
        // Update statistics
        updateStatistics(data.statistics || {});
        
        // Render table
        renderEDMSTable();
    })
    .catch(error => {
        console.error('Error loading EDMS data:', error);
        showErrorState('edms-table-body');
    });
}

function showLoadingState(tableBodyId) {
    const tableBody = document.getElementById(tableBodyId);
    if (tableBody) {
        tableBody.innerHTML = `
            <tr>
                <td colspan="13" class="text-center py-8">
                    <div class="loading-spinner mx-auto mb-2"></div>
                    <p class="text-gray-600">Loading EDMS data...</p>
                </td>
            </tr>
        `;
    }
}

function showErrorState(tableBodyId) {
    const tableBody = document.getElementById(tableBodyId);
    if (tableBody) {
        tableBody.innerHTML = `
            <tr>
                <td colspan="13" class="text-center py-8">
                    <i data-lucide="alert-circle" class="h-8 w-8 text-red-500 mx-auto mb-2"></i>
                    <p class="text-red-600">Failed to load EDMS data</p>
                    <button onclick="loadEDMSData()" class="mt-2 text-blue-600 hover:text-blue-800">
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
}

function updateStatistics(stats) {
    document.getElementById('total-count').textContent = stats.total || 0;
    document.getElementById('digitized-count').textContent = stats.digitized || 0;
    document.getElementById('pending-count').textContent = stats.pending || 0;
    document.getElementById('month-count').textContent = stats.thisMonth || 0;
    document.getElementById('applications-count').textContent = stats.total || 0;
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

function getEDMSStatusBadge(status) {
    switch(status) {
        case 'digitized':
            return '<span class="badge badge-success">Digitized</span>';
        case 'scanning':
            return '<span class="badge badge-info">Scanning</span>';
        case 'indexing':
            return '<span class="badge badge-warning">Indexing</span>';
        case 'pending':
            return '<span class="badge badge-default">Pending</span>';
        default:
            return '<span class="badge badge-default">Unknown</span>';
    }
}

function renderEDMSTable() {
    const tableBody = document.getElementById('edms-table-body');
    const noResults = document.getElementById('no-results');
    
    if (!tableBody) return;
    
    if (!edmsData || edmsData.length === 0) {
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
    
    // Generate table rows
    const rows = edmsData.map(app => {
        const actionMenuId = `action-menu-${app.id}`;
        
        return `
            <tr class="table-row border-b hover:bg-gray-50">
               
                <td class="p-2" style="max-width: 140px;">
                    <div class="text-xs text-gray-900 truncate" title="${app.NewKANGISFileno || 'N/A'}">${app.NewKANGISFileno || 'N/A'}</div>
                </td>
                <td class="p-2" style="max-width: 120px;">
                    <div class="text-xs text-gray-900 truncate" title="${app.kangisFileNo || 'N/A'}">${app.kangisFileNo || 'N/A'}</div>
                </td>
                <td class="p-2" style="max-width: 100px;">
                    <div class="text-xs text-gray-900 truncate" title="${app.mlsfNo || 'N/A'}">${app.mlsfNo || 'N/A'}</div>
                </td>
                <td class="p-2" style="max-width: 100px;">
                    <div class="text-xs text-gray-900 truncate" title="${app.reg_no || 'N/A'}">${app.reg_no || 'N/A'}</div>
                </td>
                <td class="p-2" style="max-width: 120px;">
                    <div class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ${getApplicationTypeClass(app.applicant_type)}">
                        ${app.applicant_type || 'N/A'}
                    </div>
                </td>
                <td class="p-2" style="max-width: 180px;">
                    <div class="text-xs font-medium text-gray-900 truncate" title="${app.applicant_name || 'N/A'}">${app.applicant_name || 'N/A'}</div>
                </td>
                <td class="p-2" style="max-width: 120px;">
                    <div class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">
                        ${app.current_land_use && app.current_land_use !== 'N/A' ? app.current_land_use.charAt(0).toUpperCase() + app.current_land_use.slice(1).toLowerCase() : 'N/A'}
                    </div>
                </td>
                <td class="p-2" style="max-width: 200px;">
                    <div class="text-xs text-gray-900 truncate" title="${app.plot_details || 'N/A'}">${app.plot_details || 'N/A'}</div>
                </td>
                <td class="p-2" style="max-width: 120px;">
                    <div class="text-xs text-gray-900 truncate">${app.document_count || 0} docs</div>
                </td>
                <td class="p-2" style="max-width: 120px;">
                    <div class="text-xs text-gray-900 truncate">${app.last_updated || 'N/A'}</div>
                </td>
                <td class="p-2" style="max-width: 120px;">
                    ${getEDMSStatusBadge(app.edms_status)}
                </td>
                <td class="p-2" style="max-width: 100px;">
                    <div class="relative">
                        <button 
                            onclick="toggleActionMenu('${actionMenuId}')"
                            class="inline-flex items-center justify-center rounded-md font-medium text-sm px-2 py-1 transition-all cursor-pointer bg-transparent border border-gray-300 text-gray-700 hover:bg-gray-50"
                        >
                            <i data-lucide="more-horizontal" class="h-3 w-3"></i>
                        </button>
                        
                        <div id="${actionMenuId}" class="hidden absolute right-0 top-full mt-1 w-56 bg-white rounded-md shadow-lg border border-gray-200 z-50">
                            <div class="py-1">
                                <button onclick="viewApplication(${app.id})" class="flex items-center w-full px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 gap-2">
                                    <i data-lucide="eye" class="h-4 w-4"></i>
                                    View Application
                                </button>
                                <button onclick="createEDMSRecord(${app.id})" class="flex items-center w-full px-4 py-2 text-sm text-blue-600 hover:bg-blue-50 gap-2">
                                    <i data-lucide="plus-circle" class="h-4 w-4"></i>
                                    Create DMS Record
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
                renderEDMSTable();
                return;
            }
            
            const filteredData = edmsData.filter(app => {
                return (
                    (app.file_number && app.file_number.toLowerCase().includes(searchTerm)) ||
                    (app.applicant_name && app.applicant_name.toLowerCase().includes(searchTerm)) ||
                    (app.plot_details && app.plot_details.toLowerCase().includes(searchTerm)) ||
                    (app.applicant_type && app.applicant_type.toLowerCase().includes(searchTerm)) ||
                    (app.edms_status && app.edms_status.toLowerCase().includes(searchTerm)) ||
                    (app.cofO_serialNo && app.cofO_serialNo.toLowerCase().includes(searchTerm)) ||
                    (app.NewKANGISFileno && app.NewKANGISFileno.toLowerCase().includes(searchTerm)) ||
                    (app.kangisFileNo && app.kangisFileNo.toLowerCase().includes(searchTerm)) ||
                    (app.mlsfNo && app.mlsfNo.toLowerCase().includes(searchTerm)) ||
                    (app.reg_no && app.reg_no.toLowerCase().includes(searchTerm))
                );
            });
            
            // Update the global data and re-render
            const originalData = edmsData;
            edmsData = filteredData;
            renderEDMSTable();
            edmsData = originalData; // Restore original data
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
function viewApplication(id) {
    console.log('Viewing application:', id);
    closeActionMenus();
    window.location.href = `/recertification/${id}/details`;
}

function closeActionMenus() {
    document.querySelectorAll('[id^="action-menu-"]').forEach(menu => {
        menu.classList.add('hidden');
    });
}

// Make functions available globally
window.toggleActionMenu = toggleActionMenu;
window.viewApplication = viewApplication;
window.loadEDMSData = loadEDMSData;

// Create EDMS Record Function
function createEDMSRecord(id) {
    console.log('Creating EDMS record for application:', id);
    closeActionMenus();
    window.location.href = `/edms/${id}/recertification`;
}

// Make createEDMSRecord available globally
window.createEDMSRecord = createEDMSRecord;

console.log('EDMS table script initialized');
</script>

@endsection