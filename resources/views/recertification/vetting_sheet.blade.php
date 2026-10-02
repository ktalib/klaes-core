@extends('layouts.app')
@section('page-title')
    {{ __('Vetting Sheet') }}
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
                    <h1 class="text-3xl font-bold text-gray-900">Vetting Sheet</h1>
                    <p class="text-gray-600">Review and vet recertification applications for processing</p>
                </div>
                <div class="flex gap-3">
                    <a href="{{ route('recertification.index') }}" class="inline-flex items-center justify-center rounded-md font-medium text-sm px-4 py-2 transition-all cursor-pointer bg-transparent border border-gray-300 text-gray-700 hover:bg-gray-50 gap-2">
                        <i data-lucide="arrow-left" class="h-4 w-4"></i>
                        Back to Applications
                    </a>
                </div>
            </div>

            <!-- Statistics -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6  hidden" >
                <div class="bg-white rounded-lg shadow border border-gray-200 p-6">
                    <div class="flex items-center">
                        <div class="p-2 bg-blue-100 rounded-lg">
                            <i data-lucide="clipboard-check" class="h-6 w-6 text-blue-600"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Total Applications</p>
                            <p class="text-2xl font-bold text-gray-900" id="total-count">0</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow border border-gray-200 p-6  hidden">
                    <div class="flex items-center">
                        <div class="p-2 bg-green-100 rounded-lg">
                            <i data-lucide="check-circle" class="h-6 w-6 text-green-600"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Generated</p>
                            <p class="text-2xl font-bold text-gray-900" id="generated-count">0</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow border border-gray-200 p-6  hidden">
                    <div class="flex items-center">
                        <div class="p-2 bg-yellow-100 rounded-lg">
                            <i data-lucide="clock" class="h-6 w-6 text-yellow-600"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Pending</p>
                            <p class="text-2xl font-bold text-gray-900" id="pending-count">0</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow border border-gray-200 p-6 hidden">
                    <div class="flex items-center">
                        <div class="p-2 bg-purple-100 rounded-lg">
                            <i data-lucide="calendar" class="h-6 w-6 text-purple-600"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Ready</p>
                            <p class="text-2xl font-bold text-gray-900" id="ready-count">0</p>
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
                                placeholder="Search by applicant name, file number, plot number..."
                                class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-md text-sm transition-all focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10"
                            />
                        </div>
                        <button class="inline-flex items-center justify-center rounded-md font-medium text-sm px-3 py-2 transition-all cursor-pointer bg-transparent border border-gray-300 text-gray-700 hover:bg-gray-50">
                            <i data-lucide="filter" class="h-4 w-4"></i>
                        </button>
                    </div>
                </div>
            </div>

          
            <!-- Vetting Sheet Table -->
            <div class="bg-white rounded-lg shadow border border-gray-200">
                <div class="p-6 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h3 class="text-xl font-semibold text-gray-900 flex items-center gap-2">
                            <i data-lucide="clipboard-check" class="h-5 w-5 text-blue-600"></i>
                            Vetting Sheet (<span id="applications-count">0</span>)
                        </h3>
                        <span class="badge badge-default">
                            Applications for vetting review
                        </span>
                    </div>
                </div>
                
                <div class="rounded-md border-t-0" id="vetting-table-container">
                    <div class="p-6">
                        <!-- Table -->
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200" id="vetting-sheet-table">
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
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody id="vetting-table-body" class="bg-white divide-y divide-gray-200">
                                    <!-- Applications will be loaded dynamically -->
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- No results state -->
                        <div id="no-results" class="hidden text-center py-12">
                            <i data-lucide="clipboard-check" class="h-12 w-12 text-gray-400 mx-auto mb-4"></i>
                            <h3 class="text-lg font-medium mb-2 text-gray-900">No applications found</h3>
                            <p id="no-results-message" class="text-gray-600">
                                No applications available for vetting
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
// Vetting Sheet Table Management
let vettingData = [];

document.addEventListener('DOMContentLoaded', function() {
    console.log('Vetting sheet table script loaded');
    
    // Initialize Lucide icons
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
    
    // Load vetting data
    loadVettingData();
    
    // Setup search functionality
    setupSearch();
    
    // Setup modal handlers
    setupModalHandlers();
});

function showDebugInfo(message) {
    const debugInfo = document.getElementById('debug-info');
    const debugContent = document.getElementById('debug-content');
    if (debugInfo && debugContent) {
        debugContent.innerHTML += '<div>' + message + '</div>';
        debugInfo.classList.remove('hidden');
    }
}

function loadVettingData() {
    console.log('Loading vetting data...');
    showDebugInfo('Starting to load vetting data...');
    
    // Show loading state
    showLoadingState('vetting-table-body');
    
    // Fetch data from backend
    fetch('/recertification/vetting-data', {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
            'Content-Type': 'application/json'
        }
    })
    .then(response => {
        console.log('Response status:', response.status);
        showDebugInfo('Response status: ' + response.status);
        
        if (!response.ok) {
            return response.text().then(text => {
                console.error('Error response body:', text);
                showDebugInfo('Error response: ' + text);
                throw new Error(`HTTP error! status: ${response.status}, body: ${text}`);
            });
        }
        return response.json();
    })
    .then(data => {
        console.log('Vetting data received:', data);
        showDebugInfo('Data received: ' + JSON.stringify(data, null, 2));
        
        if (data.success === false) {
            console.error('API returned error:', data.error);
            showDebugInfo('API error: ' + (data.error || 'Unknown error'));
            showErrorState('vetting-table-body', data.error || 'Unknown error');
            return;
        }
        
        vettingData = data.data || [];
        console.log('Processed vetting data:', vettingData.length, 'records');
        showDebugInfo('Processed ' + vettingData.length + ' records');
        
        // Update statistics
        updateStatistics(data.statistics || {});
        
        // Render table
        renderVettingTable();
    })
    .catch(error => {
        console.error('Error loading vetting data:', error);
        showDebugInfo('Fetch error: ' + error.message);
        showErrorState('vetting-table-body', error.message);
    });
}

function showLoadingState(tableBodyId) {
    const tableBody = document.getElementById(tableBodyId);
    if (tableBody) {
        tableBody.innerHTML = `
            <tr>
                <td colspan="11" class="text-center py-8">
                    <div class="loading-spinner mx-auto mb-2"></div>
                    <p class="text-gray-600">Loading vetting data...</p>
                </td>
            </tr>
        `;
    }
}

function showErrorState(tableBodyId, errorMessage = 'Failed to load vetting data') {
    const tableBody = document.getElementById(tableBodyId);
    if (tableBody) {
        tableBody.innerHTML = `
            <tr>
                <td colspan="11" class="text-center py-8">
                    <i data-lucide="alert-circle" class="h-8 w-8 text-red-500 mx-auto mb-2"></i>
                    <p class="text-red-600 mb-2">${errorMessage}</p>
                    <button onclick="loadVettingData()" class="mt-2 px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
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
    document.getElementById('generated-count').textContent = stats.generated || 0;
    document.getElementById('pending-count').textContent = stats.pending || 0;
    document.getElementById('ready-count').textContent = stats.ready || 0;
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

function getStatusBadge(status) {
    switch(status) {
        case 'generated':
            return '<span class="badge badge-success">Generated</span>';
        case 'ready':
            return '<span class="badge badge-warning">Ready</span>';
        case 'pending':
            return '<span class="badge badge-default">Pending</span>';
        default:
            return '<span class="badge badge-default">Unknown</span>';
    }
}

function renderVettingTable() {
    const tableBody = document.getElementById('vetting-table-body');
    const noResults = document.getElementById('no-results');
    
    if (!tableBody) return;
    
    if (!vettingData || vettingData.length === 0) {
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
    const rows = vettingData.map(app => {
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
                    <div class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">
                        ${app.land_use && app.land_use !== 'N/A' ? app.land_use.charAt(0).toUpperCase() + app.land_use.slice(1).toLowerCase() : 'N/A'}
                    </div>
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
                                <button onclick="viewApplication(${app.id})" class="flex items-center w-full px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 gap-2">
                                    <i data-lucide="eye" class="h-4 w-4"></i>
                                    View Application
                                </button>
                                <button onclick="viewPrintVettingSheet()" class="flex items-center w-full px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 gap-2">
                                    <i data-lucide="folder-open" class="h-4 w-4"></i>
                                    View/Print Vetting Sheet
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
                renderVettingTable();
                return;
            }
            
            const filteredData = vettingData.filter(app => {
                return (
                    (app.file_number && app.file_number.toLowerCase().includes(searchTerm)) ||
                    (app.applicant_name && app.applicant_name.toLowerCase().includes(searchTerm)) ||
                    (app.plot_details && app.plot_details.toLowerCase().includes(searchTerm)) ||
                    (app.lga_name && app.lga_name.toLowerCase().includes(searchTerm)) ||
                    (app.applicant_type && app.applicant_type.toLowerCase().includes(searchTerm))
                );
            });
            
            // Update the global data and re-render
            const originalData = vettingData;
            vettingData = filteredData;
            renderVettingTable();
            vettingData = originalData; // Restore original data
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

function viewPrintVettingSheet() {
    console.log('Opening Vetting Sheet folder...');
    closeActionMenus();
    
    // Multiple folder path formats to try
    const folderPaths = [
        'file:///C:/Users/admin/Documents/',
        'file://C:/Users/admin/Documents/',
        'file:///C:/Users/admin/Documents',
        'C:/Users/admin/Documents/'
    ];
    
    let opened = false;
    
    // Try each path format
    for (let i = 0; i < folderPaths.length && !opened; i++) {
        try {
            console.log(`Trying to open: ${folderPaths[i]}`);
            
            // Method 1: Direct window.open
            const newWindow = window.open(folderPaths[i], '_blank', 'width=1200,height=800,scrollbars=yes,resizable=yes,toolbar=yes,location=yes');
            
            // Give it a moment to load
            setTimeout(() => {
                if (newWindow && !newWindow.closed) {
                    console.log('Successfully opened folder with window.open');
                    opened = true;
                }
            }, 100);
            
            if (opened) break;
            
            // Method 2: Create and click link
            const link = document.createElement('a');
            link.href = folderPaths[i];
            link.target = '_blank';
            link.style.display = 'none';
            document.body.appendChild(link);
            
            // Simulate user click
            const clickEvent = new MouseEvent('click', {
                view: window,
                bubbles: true,
                cancelable: true
            });
            
            link.dispatchEvent(clickEvent);
            document.body.removeChild(link);
            
            // Method 3: Try location.href in new window
            if (!opened) {
                const popup = window.open('', '_blank', 'width=1200,height=800,scrollbars=yes,resizable=yes,toolbar=yes,location=yes');
                if (popup) {
                    popup.location.href = folderPaths[i];
                    opened = true;
                }
            }
            
        } catch (error) {
            console.error(`Error with path ${folderPaths[i]}:`, error);
        }
    }
    
    // If nothing worked, try Windows-specific approach
    if (!opened) {
        try {
            // Try Windows explorer command
            const explorerPath = 'file:///C:/Windows/explorer.exe?C:\\Users\\admin\\Documents';
            window.open(explorerPath, '_blank');
            opened = true;
        } catch (error) {
            console.error('Explorer method failed:', error);
        }
    }
    
    // Final fallback with more detailed instructions
    if (!opened) {
        const message = `Unable to open folder automatically due to browser security restrictions.
        
Please manually navigate to the folder using one of these methods:

1. Copy and paste this path in File Explorer:
   C:\\Users\\admin\\Documents

2. Or copy and paste this in your browser address bar:
   file:///C:/Users/admin/Documents/

3. Or press Windows key + R, then type:
   C:\\Users\\admin\\Documents

Note: Some browsers block direct file system access for security reasons.`;
        
        alert(message);
        
        // Also try to copy the path to clipboard
        try {
            navigator.clipboard.writeText('C:\\Users\\admin\\Documents').then(() => {
                console.log('Path copied to clipboard');
            });
        } catch (clipboardError) {
            console.log('Could not copy to clipboard:', clipboardError);
        }
    }
}

function closeActionMenus() {
    document.querySelectorAll('[id^="action-menu-"]').forEach(menu => {
        menu.classList.add('hidden');
    });
}

// Make functions available globally
window.toggleActionMenu = toggleActionMenu;
window.viewApplication = viewApplication;
window.viewPrintVettingSheet = viewPrintVettingSheet;
window.loadVettingData = loadVettingData;

console.log('Vetting sheet table script initialized');
</script>

@endsection