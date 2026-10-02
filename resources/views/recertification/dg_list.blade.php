@extends('layouts.app')
@section('page-title')
    {{ __('DG\'s List') }}
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
                    <h1 class="text-3xl font-bold text-gray-900">DG's List</h1>
                    <p class="text-gray-600">Generated per batch (150 records each), listing CofOs ready for Director General's approval</p>
                </div>
                <div class="flex gap-3">
                    <button id="batch-process-btn" onclick="processBatch()" class="inline-flex items-center justify-center rounded-md font-medium text-sm px-4 py-2 transition-all cursor-pointer bg-green-600 text-white hover:bg-green-700 gap-2 disabled:bg-gray-400 disabled:cursor-not-allowed" disabled>
                        <i data-lucide="check-circle" class="h-4 w-4"></i>
                        Batch Processing & Approval
                    </button>
                    <a href="{{ route('recertification.index') }}" class="inline-flex items-center justify-center rounded-md font-medium text-sm px-4 py-2 transition-all cursor-pointer bg-transparent border border-gray-300 text-gray-700 hover:bg-gray-50 gap-2">
                        <i data-lucide="arrow-left" class="h-4 w-4"></i>
                        Back to Applications
                    </a>
                </div>
            </div>

            <!-- Batch Selection -->
            <div class="bg-white rounded-lg shadow border border-gray-200 mb-6">
                <div class="p-6">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-4">
                            <div class="flex items-center gap-2">
                                <i data-lucide="layers" class="h-5 w-5 text-blue-600"></i>
                                <label for="batch-select" class="text-sm font-medium text-gray-700">Select Batch:</label>
                            </div>
                            <select id="batch-select" onchange="loadBatchData()" class="px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10">
                                <option value="">Select a batch...</option>
                                <!-- Batch options will be populated dynamically -->
                            </select>
                        </div>
                        <div class="flex items-center gap-4">
                            <div class="text-sm text-gray-600">
                                <span class="font-medium">Current Batch:</span> 
                                <span id="current-batch-info" class="text-blue-600 font-semibold">None selected</span>
                            </div>
                            <div class="text-sm text-gray-600">
                                <span class="font-medium">Records:</span> 
                                <span id="batch-record-count" class="text-green-600 font-semibold">0 / 150</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statistics -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="bg-white rounded-lg shadow border border-gray-200 p-6">
                    <div class="flex items-center">
                        <div class="p-2 bg-blue-100 rounded-lg">
                            <i data-lucide="list-end" class="h-6 w-6 text-blue-600"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Total Applications</p>
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
                            <p class="text-sm font-medium text-gray-600">DG Approved</p>
                            <p class="text-2xl font-bold text-gray-900" id="approved-count">0</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow border border-gray-200 p-6">
                    <div class="flex items-center">
                        <div class="p-2 bg-yellow-100 rounded-lg">
                            <i data-lucide="clock" class="h-6 w-6 text-yellow-600"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Pending DG Review</p>
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
                                placeholder="Search by applicant name, file number, plot number, serial number..."
                                class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-md text-sm transition-all focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10"
                            />
                        </div>
                        <button class="inline-flex items-center justify-center rounded-md font-medium text-sm px-3 py-2 transition-all cursor-pointer bg-transparent border border-gray-300 text-gray-700 hover:bg-gray-50">
                            <i data-lucide="filter" class="h-4 w-4"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- DG's List Table -->
            <div class="bg-white rounded-lg shadow border border-gray-200">
                <div class="p-6 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h3 class="text-xl font-semibold text-gray-900 flex items-center gap-2">
                            <i data-lucide="list-end" class="h-5 w-5 text-blue-600"></i>
                            DG's List (<span id="applications-count">0</span>)
                        </h3>
                        <span class="badge badge-info">
                            Applications for DG approval
                        </span>
                    </div>
                </div>
                
                <div class="rounded-md border-t-0" id="dg-table-container">
                    <div class="p-6">
                        <!-- Table -->
                        <div class="table-container overflow-x-auto">
                            <table class="w-full" style="min-width: 1200px;">
                                <thead>
                                    <tr class="border-b bg-gray-50">
                                        <th class="text-left p-2 font-medium text-gray-700 text-xs">
                                            <input type="checkbox" id="select-all" onchange="toggleSelectAll()" class="rounded border-gray-300">
                                        </th>
                                        <th class="text-left p-2 font-medium text-gray-700 text-xs" style="min-width: 80px;">Serial No</th>
                                        <th class="text-left p-2 font-medium text-gray-700 text-xs" style="min-width: 120px;">CofO Serial No</th>
                                        <th class="text-left p-2 font-medium text-gray-700 text-xs" style="min-width: 140px;">New KANGIS File No</th>
                                        <th class="text-left p-2 font-medium text-gray-700 text-xs" style="min-width: 120px;">KANGIS File No</th>
                                        <th class="text-left p-2 font-medium text-gray-700 text-xs" style="min-width: 100px;">MLPP File No</th>
                                        <th class="text-left p-2 font-medium text-gray-700 text-xs" style="min-width: 100px;">Plot No</th>
                                        <th class="text-left p-2 font-medium text-gray-700 text-xs" style="min-width: 120px;">Land Use</th>
                                        <th class="text-left p-2 font-medium text-gray-700 text-xs" style="min-width: 180px;">Current Allottee</th>
                                        <th class="text-left p-2 font-medium text-gray-700 text-xs" style="min-width: 150px;">Layout Name</th>
                                         
                                        <th class="text-left p-2 font-medium text-gray-700 text-xs" style="min-width: 100px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="dg-table-body">
                                    <!-- Applications will be loaded dynamically -->
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- No results state -->
                        <div id="no-results" class="hidden text-center py-12">
                            <i data-lucide="list-end" class="h-12 w-12 text-gray-400 mx-auto mb-4"></i>
                            <h3 class="text-lg font-medium mb-2 text-gray-900">No applications found</h3>
                            <p id="no-results-message" class="text-gray-600">
                                No applications submitted to DG for approval
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
// DG's List Table Management
let dgData = [];
let allDGData = []; // Store all data for batch processing
let serialCounter = 1;
let currentBatch = null;
let batchSize = 150;

document.addEventListener('DOMContentLoaded', function() {
    console.log('DG list table script loaded');
    
    // Initialize Lucide icons
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
    
    // Initialize batch dropdown
    initializeBatchDropdown();
    
    // Load DG data
    loadDGData();
    
    // Setup search functionality
    setupSearch();
    
    // Setup modal handlers
    setupModalHandlers();
});

function initializeBatchDropdown() {
    const batchSelect = document.getElementById('batch-select');
    if (!batchSelect) return;
    
    // Clear existing options except the first one
    while (batchSelect.children.length > 1) {
        batchSelect.removeChild(batchSelect.lastChild);
    }
    
    // Add batch options starting from batch 11
    for (let i = 11; i <= 20; i++) {
        const option = document.createElement('option');
        option.value = i;
        option.textContent = `Batch ${i}`;
        batchSelect.appendChild(option);
    }
    
    // Set default to batch 11
    batchSelect.value = '11';
    currentBatch = 11;
    updateBatchInfo();
}

function loadBatchData() {
    const batchSelect = document.getElementById('batch-select');
    if (!batchSelect) return;
    
    const selectedBatch = parseInt(batchSelect.value);
    if (!selectedBatch) {
        currentBatch = null;
        dgData = [];
        renderDGTable();
        updateBatchInfo();
        return;
    }
    
    currentBatch = selectedBatch;
    
    // Calculate batch range
    const startIndex = (currentBatch - 11) * batchSize;
    const endIndex = startIndex + batchSize;
    
    // Filter data for current batch
    dgData = allDGData.slice(startIndex, endIndex);
    
    // Update UI
    renderDGTable();
    updateBatchInfo();
    updateBatchProcessingButton();
    
    // Auto-select all records in the current batch
    setTimeout(() => {
        autoSelectBatchRecords();
    }, 100);
    
    console.log(`Loaded batch ${currentBatch}: ${dgData.length} records`);
}

function autoSelectBatchRecords() {
    // Auto-check the "Select All" checkbox
    const selectAllCheckbox = document.getElementById('select-all');
    if (selectAllCheckbox) {
        selectAllCheckbox.checked = true;
        
        // Trigger the select all function
        toggleSelectAll();
        
        console.log(`Auto-selected all ${dgData.length} records in batch ${currentBatch}`);
    }
}

function updateBatchInfo() {
    const currentBatchInfo = document.getElementById('current-batch-info');
    const batchRecordCount = document.getElementById('batch-record-count');
    
    if (currentBatch) {
        currentBatchInfo.textContent = `Batch ${currentBatch}`;
        batchRecordCount.textContent = `${dgData.length} / ${batchSize}`;
        batchRecordCount.className = dgData.length === batchSize ? 'text-green-600 font-semibold' : 'text-orange-600 font-semibold';
    } else {
        currentBatchInfo.textContent = 'None selected';
        batchRecordCount.textContent = '0 / 150';
        batchRecordCount.className = 'text-gray-600 font-semibold';
    }
}

function loadDGData() {
    console.log('Loading DG data...');
    
    // Show loading state
    showLoadingState('dg-table-body');
    
    // Fetch data from backend
    fetch('/recertification/dg-data', {
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
        console.log('DG data received:', data);
        
        // Store all data for batch processing
        allDGData = data.data || [];
        
        // Reset serial counter
        serialCounter = 1;
        
        // Update statistics (based on all data)
        updateStatistics(data.statistics || {});
        
        // Load the current batch (default is batch 11)
        loadBatchData();
    })
    .catch(error => {
        console.error('Error loading DG data:', error);
        showErrorState('dg-table-body');
    });
}

function showLoadingState(tableBodyId) {
    const tableBody = document.getElementById(tableBodyId);
    if (tableBody) {
        tableBody.innerHTML = `
            <tr>
                <td colspan="12" class="text-center py-8">
                    <div class="loading-spinner mx-auto mb-2"></div>
                    <p class="text-gray-600">Loading DG list data...</p>
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
                <td colspan="12" class="text-center py-8">
                    <i data-lucide="alert-circle" class="h-8 w-8 text-red-500 mx-auto mb-2"></i>
                    <p class="text-red-600">Failed to load DG list data</p>
                    <button onclick="loadDGData()" class="mt-2 text-blue-600 hover:text-blue-800">
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
    // Update overall statistics (based on all data)
    document.getElementById('total-count').textContent = allDGData.length || 0;
    document.getElementById('approved-count').textContent = stats.approved || 0;
    document.getElementById('pending-count').textContent = stats.pending || 0;
    document.getElementById('month-count').textContent = stats.thisMonth || 0;
    
    // Update current batch count
    document.getElementById('applications-count').textContent = dgData.length || 0;
}

function getDGStatusBadge(status) {
    switch(status) {
        case 'approved':
            return '<span class="badge badge-success">DG Approved</span>';
        case 'pending':
            return '<span class="badge badge-warning">Pending DG Review</span>';
        case 'under_review':
            return '<span class="badge badge-info">Under DG Review</span>';
        default:
            return '<span class="badge badge-default">Unknown</span>';
    }
}

function getPrerequisitesStatus(app) {
    const prerequisites = [
        { key: 'acknowledgement_generated', label: 'Acknowledgement' },
        { key: 'verification_generated', label: 'Verification Sheet' },
        { key: 'gis_captured', label: 'GIS Captured' },
        { key: 'vetting_generated', label: 'Vetting Sheet' },
        { key: 'edms_captured', label: 'EDMS Captured' },
        { key: 'cofo_front_generated', label: 'CofO Front Page' }
    ];
    
    const completed = prerequisites.filter(p => app[p.key]).length;
    const total = prerequisites.length;
    const isComplete = completed === total;
    
    return {
        completed,
        total,
        isComplete,
        percentage: Math.round((completed / total) * 100),
        details: prerequisites.map(p => ({
            ...p,
            status: app[p.key] ? 'completed' : 'pending'
        }))
    };
}

function renderDGTable() {
    const tableBody = document.getElementById('dg-table-body');
    const noResults = document.getElementById('no-results');
    
    if (!tableBody) return;
    
    if (!dgData || dgData.length === 0) {
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
    
    // Reset serial counter for rendering
    let currentSerial = 1;
    
    // Generate table rows
    const rows = dgData.map(app => {
        const actionMenuId = `action-menu-${app.id}`;
        const prerequisitesStatus = getPrerequisitesStatus(app);
        const canSelect = prerequisitesStatus.isComplete && !app.dg_approval;
        const serialNo = currentSerial++;
        
        return `
            <tr class="table-row border-b hover:bg-gray-50 ${!canSelect ? 'opacity-60' : ''}">
                <td class="p-2">
                    <input 
                        type="checkbox" 
                        class="application-checkbox rounded border-gray-300" 
                        value="${app.id}"
                        onchange="updateBatchProcessingButton()"
                        ${!canSelect ? 'disabled' : ''}
                    >
                </td>
                <td class="p-2" style="max-width: 80px;">
                    <div class="text-xs font-medium text-gray-900">${serialNo}</div>
                </td>
                <td class="p-2" style="max-width: 120px;">
                    <div class="text-xs text-gray-900 truncate" title="${app.cofo_serial_no || app.cofO_serialNo || 'N/A'}">${app.cofo_serial_no || app.cofO_serialNo || 'N/A'}</div>
                </td>
                <td class="p-2" style="max-width: 140px;">
                    <div class="text-xs text-gray-900 truncate" title="${app.NewKANGISFileno || 'N/A'}">${app.NewKANGISFileno || 'N/A'}</div>
                </td>
                <td class="p-2" style="max-width: 120px;">
                    <div class="text-xs text-gray-900 truncate" title="${app.kangisFileNo || 'N/A'}">${app.kangisFileNo || 'N/A'}</div>
                </td>
                <td class="p-2" style="max-width: 100px;">
                    <div class="text-xs text-gray-900 truncate" title="${app.mlsfileNo || app.mlsfNo || 'N/A'}">${app.mlsfileNo || app.mlsfNo || 'N/A'}</div>
                </td>
                <td class="p-2" style="max-width: 100px;">
                    <div class="text-xs text-gray-900 truncate" title="${app.plotNo || app.plot_number || 'N/A'}">${app.plotNo || app.plot_number || 'N/A'}</div>
                </td>
                <td class="p-2" style="max-width: 120px;">
                    <div class="text-xs text-gray-900 truncate" title="${app.land_use || 'N/A'}">${app.land_use || 'N/A'}</div>
                </td>
                <td class="p-2" style="max-width: 180px;">
                    <div class="text-xs font-medium text-gray-900 truncate" title="${app.currentAllottee || app.applicant_name || 'N/A'}">${app.currentAllottee || app.applicant_name || 'N/A'}</div>
                </td>
                <td class="p-2" style="max-width: 150px;">
                    <div class="text-xs text-gray-900 truncate" title="${app.layoutName || app.layout_name || 'N/A'}">${app.layoutName || app.layout_name || 'N/A'}</div>
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

function updateBatchProcessingButton() {
    const checkboxes = document.querySelectorAll('.application-checkbox:checked');
    const batchProcessBtn = document.getElementById('batch-process-btn');
    
    const readyForBatch = dgData.filter(app => {
        const status = getPrerequisitesStatus(app);
        return status.isComplete && !app.dg_approval;
    }).length;
    
    if (batchProcessBtn) {
        if (checkboxes.length > 0) {
            batchProcessBtn.disabled = false;
            batchProcessBtn.innerHTML = `
                <i data-lucide="check-circle" class="h-4 w-4"></i>
                Process Selected (${checkboxes.length})
            `;
        } else {
            batchProcessBtn.disabled = readyForBatch === 0;
            batchProcessBtn.innerHTML = `
                <i data-lucide="check-circle" class="h-4 w-4"></i>
                Batch Processing & Approval ${readyForBatch > 0 ? `(${readyForBatch} ready)` : ''}
            `;
        }
        
        // Reinitialize icons
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    }
}

function toggleSelectAll() {
    const selectAllCheckbox = document.getElementById('select-all');
    const applicationCheckboxes = document.querySelectorAll('.application-checkbox:not(:disabled)');
    
    applicationCheckboxes.forEach(checkbox => {
        checkbox.checked = selectAllCheckbox.checked;
    });
    
    updateBatchProcessingButton();
}

function processBatch() {
    const selectedCheckboxes = document.querySelectorAll('.application-checkbox:checked');
    const batchProcessBtn = document.getElementById('batch-process-btn');
    
    if (selectedCheckboxes.length === 0) {
        showToast('Please select applications to process', 'error');
        return;
    }
    
    // Show loading state
    batchProcessBtn.disabled = true;
    batchProcessBtn.innerHTML = `
        <div class="loading-spinner"></div>
        Processing...
    `;
    
    // Get selected application IDs
    const applicationIds = Array.from(selectedCheckboxes).map(cb => parseInt(cb.value));
    
    // Send batch processing request
    fetch('/recertification/batch-process', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        },
        body: JSON.stringify({
            application_ids: applicationIds,
            batch_number: currentBatch
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(`Successfully processed Batch ${currentBatch} with ${data.processed_count} applications`, 'success');
            
            // Reload data to reflect changes
            loadDGData();
            
            // Uncheck select all
            const selectAllCheckbox = document.getElementById('select-all');
            if (selectAllCheckbox) {
                selectAllCheckbox.checked = false;
            }
        } else {
            showToast(data.message || 'Failed to process applications', 'error');
        }
    })
    .catch(error => {
        console.error('Error processing batch:', error);
        showToast('Failed to process applications', 'error');
    })
    .finally(() => {
        // Reset button state
        updateBatchProcessingButton();
    });
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
                renderDGTable();
                return;
            }
            
            const filteredData = dgData.filter(app => {
                return (
                    (app.file_number && app.file_number.toLowerCase().includes(searchTerm)) ||
                    (app.applicant_name && app.applicant_name.toLowerCase().includes(searchTerm)) ||
                    (app.currentAllottee && app.currentAllottee.toLowerCase().includes(searchTerm)) ||
                    (app.plot_details && app.plot_details.toLowerCase().includes(searchTerm)) ||
                    (app.plotNo && app.plotNo.toLowerCase().includes(searchTerm)) ||
                    (app.plot_number && app.plot_number.toLowerCase().includes(searchTerm)) ||
                    (app.lga_name && app.lga_name.toLowerCase().includes(searchTerm)) ||
                    (app.applicant_type && app.applicant_type.toLowerCase().includes(searchTerm)) ||
                    (app.cofo_serial_no && app.cofo_serial_no.toLowerCase().includes(searchTerm)) ||
                    (app.cofO_serialNo && app.cofO_serialNo.toLowerCase().includes(searchTerm)) ||
                    (app.NewKANGISFileno && app.NewKANGISFileno.toLowerCase().includes(searchTerm)) ||
                    (app.kangisFileNo && app.kangisFileNo.toLowerCase().includes(searchTerm)) ||
                    (app.mlsfileNo && app.mlsfileNo.toLowerCase().includes(searchTerm)) ||
                    (app.mlsfNo && app.mlsfNo.toLowerCase().includes(searchTerm)) ||
                    (app.layoutName && app.layoutName.toLowerCase().includes(searchTerm)) ||
                    (app.layout_name && app.layout_name.toLowerCase().includes(searchTerm)) ||
                    (app.land_use && app.land_use.toLowerCase().includes(searchTerm))
                );
            });
            
            // Update the global data and re-render
            const originalData = dgData;
            dgData = filteredData;
            renderDGTable();
            dgData = originalData; // Restore original data
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

// Toast notification function
function showToast(message, type = 'info') {
    const container = document.getElementById('toast-container');
    if (!container) return;
    
    const toast = document.createElement('div');
    toast.className = `p-4 rounded-lg shadow-lg border max-w-sm ${
        type === 'success' ? 'bg-green-50 border-green-200 text-green-800' :
        type === 'error' ? 'bg-red-50 border-red-200 text-red-800' :
        type === 'warning' ? 'bg-yellow-50 border-yellow-200 text-yellow-800' :
        'bg-blue-50 border-blue-200 text-blue-800'
    }`;
    
    toast.innerHTML = `
        <div class="flex items-center gap-2">
            <i data-lucide="${
                type === 'success' ? 'check-circle' :
                type === 'error' ? 'alert-circle' :
                type === 'warning' ? 'alert-triangle' :
                'info'
            }" class="h-4 w-4"></i>
            <span class="text-sm font-medium">${message}</span>
        </div>
    `;
    
    container.appendChild(toast);
    
    // Initialize icons
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        if (toast.parentNode) {
            toast.parentNode.removeChild(toast);
        }
    }, 5000);
}

// Make functions available globally
window.toggleActionMenu = toggleActionMenu;
window.viewApplication = viewApplication;
window.loadDGData = loadDGData;
window.loadBatchData = loadBatchData;
window.toggleSelectAll = toggleSelectAll;
window.processBatch = processBatch;
window.autoSelectBatchRecords = autoSelectBatchRecords;

console.log('DG list table script initialized');
</script>

@endsection