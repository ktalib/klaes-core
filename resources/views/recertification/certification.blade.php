@extends('layouts.app')
@section('page-title')
    {{ __('Certification Management') }}
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

/* Fade in animation */
.fade-in {
  animation: fadeIn 0.3s ease-in-out;
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(-10px); }
  to { opacity: 1; transform: translateY(0); }
}

/* Tab styles */
.tab-button {
  position: relative;
  padding: 0.75rem 1.5rem;
  font-weight: 500;
  border-bottom: 2px solid transparent;
  transition: all 0.2s ease;
}

.tab-button.active {
  color: #3b82f6;
  border-bottom-color: #3b82f6;
  background-color: rgba(59, 130, 246, 0.05);
}

.tab-button:hover:not(.active) {
  color: #6b7280;
  background-color: rgba(0, 0, 0, 0.025);
}

.tab-content {
  display: none;
}

.tab-content.active {
  display: block;
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
                    <h1 class="text-3xl font-bold text-gray-900">CofO Management</h1>
                    <p class="text-gray-600">Manage certificate generation and issuance for approved applications</p>
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
                        <div class="p-2 bg-green-100 rounded-lg">
                            <i data-lucide="check-circle" class="h-6 w-6 text-green-600"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Generated Certificates</p>
                            <p class="text-2xl font-bold text-gray-900" id="generated-count">0</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow border border-gray-200 p-6">
                    <div class="flex items-center">
                        <div class="p-2 bg-yellow-100 rounded-lg">
                            <i data-lucide="clock" class="h-6 w-6 text-yellow-600"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Pending Generation</p>
                            <p class="text-2xl font-bold text-gray-900" id="pending-count">0</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow border border-gray-200 p-6">
                    <div class="flex items-center">
                        <div class="p-2 bg-blue-100 rounded-lg">
                            <i data-lucide="file-text" class="h-6 w-6 text-blue-600"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Total Applications</p>
                            <p class="text-2xl font-bold text-gray-900" id="total-count">0</p>
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
                                placeholder="Search by applicant name, file number, plot number, or certificate number..."
                                class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-md text-sm transition-all focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10"
                            />
                        </div>
                        <button class="inline-flex items-center justify-center rounded-md font-medium text-sm px-3 py-2 transition-all cursor-pointer bg-transparent border border-gray-300 text-gray-700 hover:bg-gray-50">
                            <i data-lucide="filter" class="h-4 w-4"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Certification Table -->
            <div class="bg-white rounded-lg shadow border border-gray-200">
                <div class="p-6 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h3 class="text-xl font-semibold text-gray-900 flex items-center gap-2">
                            <i data-lucide="award" class="h-5 w-5 text-blue-600"></i>
                            CofO Management
                        </h3>
                    </div>
                </div>

                <!-- Tabs -->
                <div class="border-b border-gray-200">
                    <nav class="flex space-x-8 px-6" aria-label="Tabs">
                        <button id="tab-not-generated" class="tab-button active" onclick="switchTab('not-generated')">
                            <div class="flex items-center gap-2">
                                <i data-lucide="clock" class="h-4 w-4"></i>
                                Not Generated (<span id="not-generated-tab-count">0</span>)
                            </div>
                        </button>
                        <button id="tab-generated" class="tab-button" onclick="switchTab('generated')">
                            <div class="flex items-center gap-2">
                                <i data-lucide="check-circle" class="h-4 w-4"></i>
                                Generated (<span id="generated-tab-count">0</span>)
                            </div>
                        </button>
                    </nav>
                </div>
                
                <!-- Tab Content -->
                <div class="rounded-md border-t-0" id="certification-table-container">
                    <!-- Not Generated Tab -->
                    <div id="not-generated-content" class="tab-content active">
                        <div class="p-6">
                            <div class="table-container overflow-x-auto">
                                <table class="w-full" id="certification-table" style="min-width: 1200px;">
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
                                            <th class="text-left p-2 font-medium text-gray-700 text-xs" style="min-width: 100px;">LGA</th>
                                            <th class="text-left p-2 font-medium text-gray-700 text-xs" style="min-width: 120px;">Application Date</th>
                                            <th class="text-left p-2 font-medium text-gray-700 text-xs" style="min-width: 100px;">Status</th>
                                            <th class="text-left p-2 font-medium text-gray-700 text-xs" style="min-width: 100px;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="not-generated-table-body">
                                        <!-- Applications will be loaded dynamically -->
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- No results state -->
                            <div id="not-generated-no-results" class="hidden text-center py-12">
                                <i data-lucide="clock" class="h-12 w-12 text-gray-400 mx-auto mb-4"></i>
                                <h3 class="text-lg font-medium mb-2 text-gray-900">No pending certificates</h3>
                                <p class="text-gray-600">All certificates have been generated</p>
                            </div>
                        </div>
                    </div>

                    <!-- Generated Tab -->
                    <div id="generated-content" class="tab-content">
                        <div class="p-6">
                            <div class="table-container overflow-x-auto">
                                <table class="w-full" style="min-width: 1000px;">
                                    <thead>
                                        <tr class="border-b bg-gray-50">
                                             <th class="text-left p-2 font-medium text-gray-700 text-xs" style="min-width: 120px;">CofO Serial No</th>
                                             <th class="text-left p-2 font-medium text-gray-700 text-xs" style="min-width: 80px;">Batch</th>
                                             <th class="text-left p-2 font-medium text-gray-700 text-xs" style="min-width: 60px;">No</th>  
                                            <th class="text-left p-2 font-medium text-gray-700 text-xs" style="min-width: 140px;">New KANGIS FileNo</th>
                                            <th class="text-left p-2 font-medium text-gray-700 text-xs" style="min-width: 120px;">KANGIS FileNo</th>
                                            <th class="text-left p-2 font-medium text-gray-700 text-xs" style="min-width: 100px;">MLPP FNo</th>
                                            <th class="text-left p-2 font-medium text-gray-700 text-xs" style="min-width: 100px;">RegNo</th>
                                            <th class="text-left p-2 font-medium text-gray-700 text-xs" style="min-width: 120px;">Type</th>
                                            <th class="text-left p-2 font-medium text-gray-700 text-xs" style="min-width: 180px;">Applicant Name</th>
                                            <th class="text-left p-2 font-medium text-gray-700 text-xs" style="min-width: 120px;">Land Use</th>
                                            <th class="text-left p-2 font-medium text-gray-700 text-xs" style="min-width: 200px;">Plot Details</th>
                                            <th class="text-left p-2 font-medium text-gray-700 text-xs" style="min-width: 100px;">LGA</th>
                                            <th class="text-left p-2 font-medium text-gray-700 text-xs" style="min-width: 120px;">Generated Date</th>
                                            <th class="text-left p-2 font-medium text-gray-700 text-xs" style="min-width: 100px;">Status</th>
                                            <th class="text-left p-2 font-medium text-gray-700 text-xs" style="min-width: 100px;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="generated-table-body">
                                        <!-- Applications will be loaded dynamically -->
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- No results state -->
                            <div id="generated-no-results" class="hidden text-center py-12">
                                <i data-lucide="file-text" class="h-12 w-12 text-gray-400 mx-auto mb-4"></i>
                                <h3 class="text-lg font-medium mb-2 text-gray-900">No generated certificates</h3>
                                <p class="text-gray-600">No certificates have been generated yet</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    @include('admin.footer')
</div>

<!-- Alpine.js Modal for Cofo Serial Number -->
<div 
  x-data="cofoSerialModal()" 
  x-show="isOpen"
  x-cloak
  @keydown.escape.window="closeModal()"
  class="fixed inset-0 z-[1001] bg-black bg-opacity-50 flex items-center justify-center"
  style="display: none;"
>
  <div 
    class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4"
    @click.stop
    x-show="isOpen"
    x-transition:enter="transition ease-out duration-300"
    x-transition:enter-start="opacity-0 transform scale-95"
    x-transition:enter-end="opacity-100 transform scale-100"
    x-transition:leave="transition ease-in duration-200"
    x-transition:leave-start="opacity-100 transform scale-100"
    x-transition:leave-end="opacity-0 transform scale-95"
  >
    <div class="p-6 border-b border-gray-200">
      <div class="flex items-center justify-between">
        <h3 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
          <i data-lucide="hash" class="h-5 w-5 text-blue-600"></i>
          Enter Cofo Serial Number
        </h3>
        <button @click="closeModal()" class="text-gray-400 hover:text-gray-600">
          <i data-lucide="x" class="h-5 w-5"></i>
        </button>
      </div>
    </div>
    
    <form @submit.prevent="submitSerial()">
      <div class="p-6">
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 mb-2">
            Select Available Serial Number
          </label>
          <select 
            x-model="selectedSerial"
            class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm transition-all focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10"
            required
          >
            <option value="">Select a serial number...</option>
            <template x-for="serial in serialNumbers" :key="serial">
              <option :value="serial" x-text="serial"></option>
            </template>
            <option x-show="serialNumbers.length === 0" value="" disabled>No available serial numbers</option>
          </select>
          <p class="text-xs text-gray-500 mt-1">
            Only unused serial numbers are shown to prevent duplicates
          </p>
        </div>
        
        <div class="bg-blue-50 border border-blue-200 rounded-md p-3 mb-4">
          <div class="flex items-start gap-2">
            <i data-lucide="info" class="h-4 w-4 text-blue-600 mt-0.5 flex-shrink-0"></i>
            <div class="text-sm text-blue-800">
              <p class="font-medium">Serial Number Format</p>
              <p>Serial numbers are in the format: 000001, 000002, 000003, etc.</p>
            </div>
          </div>
        </div>
      </div>
      
      <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex justify-end gap-3">
        <button 
          type="button" 
          @click="closeModal()"
          class="inline-flex items-center justify-center rounded-md font-medium text-sm px-4 py-2 transition-all cursor-pointer bg-transparent border border-gray-300 text-gray-700 hover:bg-gray-50"
        >
          Cancel
        </button>
        <button 
          type="submit"
          :disabled="loading || !selectedSerial"
          class="inline-flex items-center justify-center rounded-md font-medium text-sm px-4 py-2 transition-all cursor-pointer border-0 bg-blue-600 text-white hover:bg-blue-700 gap-2"
          :class="{ 'opacity-50 cursor-not-allowed': loading || !selectedSerial }"
        >
          <template x-if="loading">
            <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-white mr-2"></div>
          </template>
          <template x-if="!loading">
           
          </template>
          <span x-text="loading ? 'Updating...' : 'Assign Serial Number'"></span>
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Toast Notifications -->
<div id="toast-container" class="fixed top-4 right-4 z-50 space-y-2">
    <!-- Toast messages will be inserted here -->
</div>

<script>
// Certification Management Table
let certificationData = [];
let currentTab = 'not-generated';

document.addEventListener('DOMContentLoaded', function() {
    console.log('Certification table script loaded');
    
    // Initialize Lucide icons
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
    
    // Load certification data
    loadCertificationData();
    
    // Setup search functionality
    setupSearch();
    
    // Setup modal handlers
    setupModalHandlers();
});

function loadCertificationData() {
    console.log('Loading certification data...');
    
    // Show loading state for both tabs
    showLoadingState('not-generated-table-body');
    showLoadingState('generated-table-body');
    
    // Fetch data from backend
    fetch('/recertification/certification-data', {
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
        console.log('Certification data received:', data);
        certificationData = data.data || [];
        
        // Update statistics
        updateStatistics(data.statistics || {});
        
        // Render tables
        renderCertificationTables();
    })
    .catch(error => {
        console.error('Error loading certification data:', error);
        showErrorState('not-generated-table-body');
        showErrorState('generated-table-body');
    });
}

function showLoadingState(tableBodyId) {
    const tableBody = document.getElementById(tableBodyId);
    if (tableBody) {
        const colspan = tableBodyId === 'generated-table-body' ? '15' : '12'; // Generated table has more columns
        tableBody.innerHTML = `
            <tr>
                <td colspan="${colspan}" class="text-center py-8">
                    <div class="loading-spinner mx-auto mb-2"></div>
                    <p class="text-gray-600">Loading certification data...</p>
                </td>
            </tr>
        `;
    }
}

function showErrorState(tableBodyId) {
    const tableBody = document.getElementById(tableBodyId);
    if (tableBody) {
        const colspan = tableBodyId === 'generated-table-body' ? '15' : '12'; // Generated table has more columns
        tableBody.innerHTML = `
            <tr>
                <td colspan="${colspan}" class="text-center py-8">
                    <i data-lucide="alert-circle" class="h-8 w-8 text-red-500 mx-auto mb-2"></i>
                    <p class="text-red-600">Failed to load certification data</p>
                    <button onclick="loadCertificationData()" class="mt-2 text-blue-600 hover:text-blue-800">
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
    document.getElementById('generated-count').textContent = stats.generated || 0;
    document.getElementById('pending-count').textContent = stats.pending || 0;
    document.getElementById('total-count').textContent = stats.total || 0;
    document.getElementById('month-count').textContent = stats.thisMonth || 0;
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

function getStatusBadge(isGenerated) {
    if (isGenerated) {
        return '<span class="badge badge-success">Generated</span>';
    } else {
        return '<span class="badge badge-warning">Pending</span>';
    }
}

function renderCertificationTables() {
    const notGeneratedData = certificationData.filter(app => !app.certificate_generated);
    const generatedData = certificationData.filter(app => app.certificate_generated);
    
    // Sort generated data: records with CofO Serial No first, then others
    const sortedGeneratedData = sortGeneratedDataWithBatching(generatedData);
    
    // Update tab counts
    document.getElementById('not-generated-tab-count').textContent = notGeneratedData.length;
    document.getElementById('generated-tab-count').textContent = generatedData.length;
    
    // Render not generated table
    renderTable('not-generated-table-body', 'not-generated-no-results', notGeneratedData, false);
    
    // Render generated table with batched data
    renderTable('generated-table-body', 'generated-no-results', sortedGeneratedData, true);
}

function sortGeneratedDataWithBatching(generatedData) {
    // Separate records with and without CofO Serial Numbers
    const withSerialNo = generatedData.filter(app => 
        app.cofo_number && app.cofo_number !== 'N/A' && app.cofo_number.trim() !== ''
    );
    const withoutSerialNo = generatedData.filter(app => 
        !app.cofo_number || app.cofo_number === 'N/A' || app.cofo_number.trim() === ''
    );
    
    // Sort records with serial numbers by creation date or ID for consistent batching
    withSerialNo.sort((a, b) => {
        // Sort by certificate_generated_date first, then by ID
        const dateA = new Date(a.certificate_generated_date || a.created_at || 0);
        const dateB = new Date(b.certificate_generated_date || b.created_at || 0);
        
        if (dateA.getTime() !== dateB.getTime()) {
            return dateA - dateB;
        }
        return (a.id || 0) - (b.id || 0);
    });
    
    // Add batch information to records with serial numbers
    const batchedWithSerialNo = withSerialNo.map((app, index) => {
        const batchNumber = Math.floor(index / 150) + 11; // Start from batch 11
        const batchPosition = (index % 150) + 1; // Position within batch (1-150)
        
        return {
            ...app,
            batch_number: batchNumber,
            batch_position: batchPosition,
            batch_info: `Batch ${batchNumber} No${batchPosition}`
        };
    });
    
    // Return records with serial numbers first, then others
    return [...batchedWithSerialNo, ...withoutSerialNo];
}

function renderTable(tableBodyId, noResultsId, data, isGenerated) {
    const tableBody = document.getElementById(tableBodyId);
    const noResults = document.getElementById(noResultsId);
    
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
    
    // Generate table rows
    const rows = data.map(app => {
        const actionMenuId = `action-menu-${app.id}`;
        const dateField = isGenerated ? (app.certificate_generated_date || 'N/A') : (app.created_at || 'N/A');
        
        // Different row structure for generated vs not generated tabs
        if (isGenerated) {
            return `
                <tr class="table-row border-b hover:bg-gray-50">
                    <td class="p-2" style="max-width: 120px;">
                        <div class="text-xs text-gray-900 truncate">${app.cofo_number || 'N/A'}</div>
                    </td>
                    <td class="p-2" style="max-width: 80px;">
                        <div class="text-xs font-medium ${app.batch_number ? 'text-blue-600' : 'text-gray-500'} truncate">
                            ${app.batch_number || 'N/A'}
                        </div>
                    </td>
                    <td class="p-2" style="max-width: 60px;">
                        <div class="text-xs font-medium ${app.batch_position ? 'text-blue-600' : 'text-gray-500'} truncate">
                            ${app.batch_position || 'N/A'}
                        </div>
                    </td>
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
                            ${app.land_use ? app.land_use.charAt(0).toUpperCase() + app.land_use.slice(1).toLowerCase() : 'N/A'}
                        </div>
                    </td>
                    <td class="p-2" style="max-width: 200px;">
                        <div class="text-xs text-gray-900 truncate" title="${app.plot_details || 'N/A'}">${app.plot_details || 'N/A'}</div>
                    </td>
                    <td class="p-2" style="max-width: 100px;">
                        <div class="text-xs text-gray-900 truncate">${app.lga_name || 'N/A'}</div>
                    </td>
                    <td class="p-2" style="max-width: 120px;">
                        <div class="text-xs text-gray-900 truncate">${dateField}</div>
                    </td>
                    <td class="p-2" style="max-width: 100px;">
                        ${getStatusBadge(isGenerated)}
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
                                    ${generateActionMenuItems(app, isGenerated)}
                                </div>
                            </div>
                        </div>
                    </td>
                </tr>
            `;
        } else {
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
                            ${app.land_use ? app.land_use.charAt(0).toUpperCase() + app.land_use.slice(1).toLowerCase() : 'N/A'}
                        </div>
                    </td>
                    <td class="p-2" style="max-width: 200px;">
                        <div class="text-xs text-gray-900 truncate" title="${app.plot_details || 'N/A'}">${app.plot_details || 'N/A'}</div>
                    </td>
                    <td class="p-2" style="max-width: 100px;">
                        <div class="text-xs text-gray-900 truncate">${app.lga_name || 'N/A'}</div>
                    </td>
                    <td class="p-2" style="max-width: 120px;">
                        <div class="text-xs text-gray-900 truncate">${dateField}</div>
                    </td>
                    <td class="p-2" style="max-width: 100px;">
                        ${getStatusBadge(isGenerated)}
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
                                    ${generateActionMenuItems(app, isGenerated)}
                                </div>
                            </div>
                        </div>
                    </td>
                </tr>
            `;
        }
    }).join('');
    
    tableBody.innerHTML = rows;
    
    // Reinitialize Lucide icons
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
}

function generateActionMenuItems(app, isGenerated) {
    let menuItems = '';
    
    if (isGenerated) {
        // Actions for generated certificates - include View CoR (View Pagination Details)
        menuItems = `
            <button onclick="viewCoR(${app.id})" class="flex items-center w-full px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 gap-2">
                <i data-lucide="eye" class="h-4 w-4"></i>
                View Pagination Details
            </button>
            <button onclick="viewCofoFrontPage(${app.id})" class="flex items-center w-full px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 gap-2">
                <i data-lucide="file-text" class="h-4 w-4"></i>
                View CofO (Front Page)
            </button>
            <button onclick="viewTDP(${app.id})" class="flex items-center w-full px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 gap-2">
                <i data-lucide="map" class="h-4 w-4"></i>
                View TDP
            </button>
            <button onclick="viewCofo(${app.id})" class="flex items-center w-full px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 gap-2">
                <i data-lucide="file-check" class="h-4 w-4"></i>
                View CofO
            </button>
                <button onclick="enterCofoSerialNumber(${app.id})" class="flex items-center w-full px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 gap-2 ${ (app.cofo_number && app.cofo_number !== 'N/A') ? 'opacity-50 cursor-not-allowed' : '' }" ${ (app.cofo_number && app.cofo_number !== 'N/A') ? 'disabled' : ''}>
                <i data-lucide="hash" class="h-4 w-4"></i>
                Enter Cofo Serial Number
            </button>
        `;
    } else {
        // Actions for not generated certificates - include Enter Cofo Serial Number and Generate CofO (Front Page)
        menuItems = `
        
            <hr class="my-1">
            <button onclick="generateCofoFrontPage(${app.id})" class="flex items-center w-full px-4 py-2 text-sm text-blue-600 hover:bg-blue-50 gap-2">
                <i data-lucide="file-plus" class="h-4 w-4"></i>
                Generate CofO (Front Page)
            </button>
        `;
    }
    
    return menuItems;
}

function switchTab(tabName) {
    // Update tab buttons
    document.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
    document.getElementById(`tab-${tabName}`).classList.add('active');
    
    // Update tab content
    document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
    document.getElementById(`${tabName}-content`).classList.add('active');
    
    currentTab = tabName;
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
                renderCertificationTables();
                return;
            }
            
            const filteredData = certificationData.filter(app => {
                return (
                    (app.file_number && app.file_number.toLowerCase().includes(searchTerm)) ||
                    (app.applicant_name && app.applicant_name.toLowerCase().includes(searchTerm)) ||
                    (app.plot_details && app.plot_details.toLowerCase().includes(searchTerm)) ||
                    (app.lga_name && app.lga_name.toLowerCase().includes(searchTerm)) ||
                    (app.cofo_number && app.cofo_number.toLowerCase().includes(searchTerm)) ||
                    (app.applicant_type && app.applicant_type.toLowerCase().includes(searchTerm)) ||
                    (app.cofO_serialNo && app.cofO_serialNo.toLowerCase().includes(searchTerm)) ||
                    (app.NewKANGISFileno && app.NewKANGISFileno.toLowerCase().includes(searchTerm)) ||
                    (app.kangisFileNo && app.kangisFileNo.toLowerCase().includes(searchTerm)) ||
                    (app.mlsfNo && app.mlsfNo.toLowerCase().includes(searchTerm)) ||
                    (app.reg_no && app.reg_no.toLowerCase().includes(searchTerm))
                );
            });
            
            // Filter and render based on current tab
            const notGeneratedData = filteredData.filter(app => !app.certificate_generated);
            const generatedData = filteredData.filter(app => app.certificate_generated);
            
            // Apply batching to filtered generated data
            const sortedGeneratedData = sortGeneratedDataWithBatching(generatedData);
            
            renderTable('not-generated-table-body', 'not-generated-no-results', notGeneratedData, false);
            renderTable('generated-table-body', 'generated-no-results', sortedGeneratedData, true);
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

// Certificate Action Functions
function viewCoR(id) {
    console.log('Viewing CoR for application:', id);
    closeActionMenus();
    
    // Find the application data
    const application = certificationData.find(app => app.id == id);
    if (!application) {
        showToast('Application data not found', 'error');
        return;
    }
    
    // Prepare parameters for CORI template
    const params = new URLSearchParams();
    
    // Add certificate generated date (current timestamp if not available)
    const certificateDate = application.certificate_generated_date || new Date().toISOString().slice(0, 19).replace('T', ' ') + '.000';
    params.append('certificate_generated_date', certificateDate);
    
    // Add serial number, page, and volume - use cofo_serial_no for serial number
    params.append('serial_no', application.serial_no | '1');
    params.append('reg_page', application.reg_page || '1');
    params.append('reg_volume', application.reg_volume || '1');
    
    // Add file numbers for reference
    if (application.NewKANGISFileno) {
        params.append('fileno', application.NewKANGISFileno);
    } else if (application.kangisFileNo) {
        params.append('fileno', application.kangisFileNo);
    } else if (application.mlsfNo) {
        params.append('fileno', application.mlsfNo);
    }
    
    // Add STM reference if available
    if (application.STM_Ref) {
        params.append('STM_Ref', application.STM_Ref);
    }
    
    // Navigate to CORI with parameters using the correct route format
    window.open(`/recertification/${id}/cor?${params.toString()}`, '_blank');
}

function generateCofoFrontPage(id) {
    console.log('Generating CofO Front Page for application:', id);
    closeActionMenus();
    
    if (!confirm('Are you sure you want to generate the Certificate of Occupancy Front Page for this application?')) {
        return;
    }
    
    showToast('Generating CofO Front Page...', 'info');
    
    fetch(`/recertification/${id}/generate-cofo-front`, {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
            'Content-Type': 'application/json',
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
            showToast('CofO Front Page generated successfully', 'success');
            // Update the certificate_generated_date in the application data
            const application = certificationData.find(app => app.id == id);
            if (application) {
                application.certificate_generated = true;
                application.certificate_generated_date = data.certificate_generated_date;
            }
            loadCertificationData(); // Reload data to reflect changes
        } else {
            showToast(data.message || 'Failed to generate CofO Front Page', 'error');
        }
    })
    .catch(error => {
        console.error('Error generating CofO Front Page:', error);
        showToast('Failed to generate CofO Front Page. Please try again.', 'error');
    });
}

function viewCofoFrontPage(id) {
    console.log('Viewing CofO Front Page for application:', id);
    closeActionMenus();
    window.location.href = `/recertification/${id}/cofo-front-page`;
}

function viewTDP(id) {
    console.log('Viewing TDP for application:', id);
    closeActionMenus();
    window.location.href = `/recertification/${id}/tdp`;
}

function viewCofo(id) {
    console.log('Viewing CofO for application:', id);
    closeActionMenus();
    window.location.href = `/recertification/${id}/cofo`;
}

function enterCofoSerialNumber(id) {
    console.log('Enter Cofo Serial Number for application:', id);
    closeActionMenus();
    
    // Check if cofo_number already exists (and is not 'N/A')
    const app = certificationData.find(a => a.id == id);
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
    } else {
        console.error('Alpine modal component not found or not initialized');
        alert('Modal not available. Please refresh the page and try again.');
    }
}

function closeActionMenus() {
    document.querySelectorAll('[id^="action-menu-"]').forEach(menu => {
        menu.classList.add('hidden');
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
window.switchTab = switchTab;
window.viewCoR = viewCoR;
window.generateCofoFrontPage = generateCofoFrontPage;
window.viewCofoFrontPage = viewCofoFrontPage;
window.viewTDP = viewTDP;
window.viewCofo = viewCofo;
window.removeToast = removeToast;
window.loadCertificationData = loadCertificationData;

console.log('Certification table script initialized');
</script>

<script>
function cofoSerialModal() {
  return {
    isOpen: false,
    applicationId: null,
    serialNumbers: [],
    selectedSerial: '',
    loading: false,
    
    openModal(appId) {
      console.log('Alpine modal opening for app ID:', appId);
      this.applicationId = appId;
      this.selectedSerial = '';
      this.isOpen = true;
      this.loadSerialNumbers();
      document.body.style.overflow = 'hidden';
      
      // Reinitialize Lucide icons after modal opens
      this.$nextTick(() => {
        if (typeof lucide !== 'undefined') {
          lucide.createIcons();
        }
      });
    },
    
    closeModal() {
      this.isOpen = false;
      this.applicationId = null;
      this.selectedSerial = '';
      this.serialNumbers = [];
      document.body.style.overflow = 'auto';
    },
    
    loadSerialNumbers() {
      fetch('/recertification/available-serial-numbers', {
        method: 'GET',
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json'
        }
      })
      .then(response => response.json())
      .then(data => {
        if (data.success && data.serialNumbers) {
          this.serialNumbers = data.serialNumbers;
        } else {
          this.serialNumbers = [];
        }
      })
      .catch(error => {
        console.error('Error loading serial numbers:', error);
        this.serialNumbers = [];
      });
    },
    
    submitSerial() {
      if (!this.selectedSerial) {
        alert('Please select a serial number');
        return;
      }
      
      this.loading = true;
      
      const formData = new FormData();
      formData.append('application_id', this.applicationId);
      formData.append('serial_number', this.selectedSerial);
      
      fetch('/recertification/assign-serial-number', {
        method: 'POST',
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
        },
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          if (typeof Swal !== 'undefined') {
            Swal.fire({
              title: 'Success!',
              text: 'Serial number assigned successfully!',
              icon: 'success',
              confirmButtonText: 'OK'
            }).then(() => {
              this.closeModal();
              window.location.reload();
            });
          } else {
            alert('Serial number assigned successfully!');
            this.closeModal();
            window.location.reload();
          }
        } else {
          if (typeof Swal !== 'undefined') {
            Swal.fire({
              title: 'Error!',
              text: data.message || 'Failed to assign serial number',
              icon: 'error',
              confirmButtonText: 'OK'
            });
          } else {
            alert(data.message || 'Failed to assign serial number');
          }
        }
      })
      .catch(error => {
        console.error('Error:', error);
        if (typeof Swal !== 'undefined') {
          Swal.fire({
            title: 'Error!',
            text: 'Failed to assign serial number',
            icon: 'error',
            confirmButtonText: 'OK'
          });
        } else {
          alert('Failed to assign serial number');
        }
      })
      .finally(() => {
        this.loading = false;
      });
    }
  }
}

// Global function to open modal from table
window.openCofoSerialModal = function(applicationId) {
  // Find the Alpine component and call openModal
  const modalElement = document.querySelector('[x-data*="cofoSerialModal"]');
  if (modalElement && modalElement._x_dataStack) {
    modalElement._x_dataStack[0].openModal(applicationId);
  }
};
</script>

@endsection