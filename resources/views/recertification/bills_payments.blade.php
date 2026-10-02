@extends('layouts.app')
@section('page-title')
    {{ __('Bills & Payments') }}
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

.badge-green {
  background-color: #d1fae5;
  color: #065f46;
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
  min-width: 1000px;
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
  border-top: 2px solid #10b981;
  border-radius: 50%;
  animation: spin 1s linear infinite;
}

@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

/* Amount styling */
.amount-cell {
  font-family: 'Courier New', monospace;
  font-weight: 600;
}

/* Modal styles */
.modal {
  display: none;
  position: fixed;
  z-index: 1000;
  left: 0;
  top: 0;
  width: 100%;
  height: 100%;
  overflow: auto;
  background-color: rgba(0,0,0,0.4);
}

.modal-content {
  background-color: #fefefe;
  margin: 5% auto;
  padding: 0;
  border: none;
  border-radius: 8px;
  width: 90%;
  max-width: 600px;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.modal-header {
  padding: 1.5rem;
  border-bottom: 1px solid #e5e7eb;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.modal-body {
  padding: 1.5rem;
}

.modal-footer {
  padding: 1rem 1.5rem;
  border-top: 1px solid #e5e7eb;
  display: flex;
  justify-content: flex-end;
  gap: 0.5rem;
}

.close {
  color: #aaa;
  font-size: 28px;
  font-weight: bold;
  cursor: pointer;
}

.close:hover,
.close:focus {
  color: #000;
  text-decoration: none;
}

@media print {
  body * {
    visibility: hidden;
  }
  .print-content, .print-content * {
    visibility: visible;
  }
  .print-content {
    position: absolute;
    left: 0;
    top: 0;
    width: 100%;
  }
  .no-print {
    display: none !important;
  }
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
                    <h1 class="text-3xl font-bold text-gray-900">Bills & Payments</h1>
                    <p class="text-gray-600">Payment records and billing information for recertification applications</p>
                </div>
                <div class="flex gap-3">
                    <button id="export-btn" onclick="exportPayments()" class="inline-flex items-center justify-center rounded-md font-medium text-sm px-4 py-2 transition-all cursor-pointer bg-green-600 text-white hover:bg-green-700 gap-2">
                        <i data-lucide="download" class="h-4 w-4"></i>
                        Export Payments
                    </button>
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
                            <i data-lucide="credit-card" class="h-6 w-6 text-green-600"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Total Payments</p>
                            <p class="text-2xl font-bold text-gray-900" id="total-count">0</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow border border-gray-200 p-6">
                    <div class="flex items-center">
                        <div class="p-2 bg-blue-100 rounded-lg">
                            <i data-lucide="banknote" class="h-6 w-6 text-blue-600"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Total Amount</p>
                            <p class="text-2xl font-bold text-gray-900 amount-cell" id="total-amount">₦0.00</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow border border-gray-200 p-6">
                    <div class="flex items-center">
                        <div class="p-2 bg-yellow-100 rounded-lg">
                            <i data-lucide="calendar" class="h-6 w-6 text-yellow-600"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">This Month</p>
                            <p class="text-2xl font-bold text-gray-900" id="month-count">0</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow border border-gray-200 p-6">
                    <div class="flex items-center">
                        <div class="p-2 bg-purple-100 rounded-lg">
                            <i data-lucide="trending-up" class="h-6 w-6 text-purple-600"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Avg. Payment</p>
                            <p class="text-2xl font-bold text-gray-900 amount-cell" id="avg-amount">₦0.00</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="bg-white rounded-lg shadow border border-gray-200">
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div class="relative">
                            <i data-lucide="search" class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 h-4 w-4"></i>
                            <input
                                id="search-input"
                                type="text"
                                placeholder="Search by applicant name, receipt no..."
                                class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-md text-sm transition-all focus:outline-none focus:border-green-600 focus:ring-2 focus:ring-green-600/10"
                            />
                        </div>
                        
                        <select id="payment-method-filter" onchange="filterPayments()" class="px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:border-green-600 focus:ring-2 focus:ring-green-600/10">
                            <option value="">All Payment Types</option>
                            <option value="initial_bill">Initial Bill</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="cash">Cash</option>
                            <option value="cheque">Cheque</option>
                            <option value="online">Online Payment</option>
                            <option value="pos">POS</option>
                        </select>
                        
                        <input
                            id="date-from"
                            type="date"
                            placeholder="From Date"
                            onchange="filterPayments()"
                            class="px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:border-green-600 focus:ring-2 focus:ring-green-600/10"
                        />
                        
                        <input
                            id="date-to"
                            type="date"
                            placeholder="To Date"
                            onchange="filterPayments()"
                            class="px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:border-green-600 focus:ring-2 focus:ring-green-600/10"
                        />
                    </div>
                </div>
            </div>

            <!-- Bills & Payments Table -->
            <div class="bg-white rounded-lg shadow border border-gray-200">
                <div class="p-6 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h3 class="text-xl font-semibold text-gray-900 flex items-center gap-2">
                            <i data-lucide="receipt" class="h-5 w-5 text-green-600"></i>
                            Payment Records (<span id="payments-count">0</span>)
                        </h3>
                        <span class="badge badge-green">
                            Recertification Payments
                        </span>
                    </div>
                </div>
                
                <div class="rounded-md border-t-0" id="payments-table-container">
                    <div class="p-6">
                        <!-- Table -->
                        <div class="table-container overflow-x-auto">
                            <table class="w-full" style="min-width: 1000px;">
                                <thead>
                                    <tr class="border-b bg-gray-50">
                                        <th class="text-left p-3 font-medium text-gray-700 text-xs" style="min-width: 60px;">SN</th>
                                        <th class="text-left p-3 font-medium text-gray-700 text-xs" style="min-width: 200px;">Applicant Name</th>
                                        <th class="text-left p-3 font-medium text-gray-700 text-xs" style="min-width: 120px;">Payment Type</th>
                                        <th class="text-left p-3 font-medium text-gray-700 text-xs" style="min-width: 140px;">Receipt No</th>
                                        <th class="text-left p-3 font-medium text-gray-700 text-xs" style="min-width: 150px;">Bank Name</th>
                                        <th class="text-left p-3 font-medium text-gray-700 text-xs" style="min-width: 120px;">Payment Amount</th>
                                        <th class="text-left p-3 font-medium text-gray-700 text-xs" style="min-width: 120px;">Payment Date</th>
                                        <th class="text-left p-3 font-medium text-gray-700 text-xs" style="min-width: 100px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="payments-table-body">
                                    <!-- Payment records will be loaded dynamically -->
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- No results state -->
                        <div id="no-results" class="hidden text-center py-12">
                            <i data-lucide="receipt" class="h-12 w-12 text-gray-400 mx-auto mb-4"></i>
                            <h3 class="text-lg font-medium mb-2 text-gray-900">No payment records found</h3>
                            <p id="no-results-message" class="text-gray-600">
                                No payment records match your current filters
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

<!-- View Payment Modal -->
<div id="viewPaymentModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="text-xl font-semibold text-gray-900">Payment Details</h2>
            <span class="close" onclick="closeModal('viewPaymentModal')">&times;</span>
        </div>
        <div class="modal-body">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Applicant Name</label>
                    <p id="view-applicant-name" class="text-sm text-gray-900 bg-gray-50 p-2 rounded">-</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Payment Type</label>
                    <p id="view-payment-type" class="text-sm text-gray-900 bg-gray-50 p-2 rounded">-</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Receipt Number</label>
                    <p id="view-receipt-no" class="text-sm text-gray-900 bg-gray-50 p-2 rounded">-</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Bank Name</label>
                    <p id="view-bank-name" class="text-sm text-gray-900 bg-gray-50 p-2 rounded">-</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Payment Amount</label>
                    <p id="view-payment-amount" class="text-sm text-green-600 font-semibold bg-gray-50 p-2 rounded amount-cell">-</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Payment Date</label>
                    <p id="view-payment-date" class="text-sm text-gray-900 bg-gray-50 p-2 rounded">-</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">File Number</label>
                    <p id="view-file-number" class="text-sm text-gray-900 bg-gray-50 p-2 rounded">-</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Applicant Type</label>
                    <p id="view-applicant-type" class="text-sm text-gray-900 bg-gray-50 p-2 rounded">-</p>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button onclick="printPayment()" class="inline-flex items-center justify-center rounded-md font-medium text-sm px-4 py-2 transition-all cursor-pointer bg-blue-600 text-white hover:bg-blue-700 gap-2">
                <i data-lucide="printer" class="h-4 w-4"></i>
                Print Receipt
            </button>
            <button onclick="closeModal('viewPaymentModal')" class="inline-flex items-center justify-center rounded-md font-medium text-sm px-4 py-2 transition-all cursor-pointer bg-gray-300 text-gray-700 hover:bg-gray-400">
                Close
            </button>
        </div>
    </div>
</div>

<!-- Print Payment Modal -->
<div id="printPaymentModal" class="modal">
    <div class="modal-content">
        <div class="modal-header no-print">
            <h2 class="text-xl font-semibold text-gray-900">Print Payment Receipt</h2>
            <span class="close" onclick="closeModal('printPaymentModal')">&times;</span>
        </div>
        <div class="modal-body">
            <div class="print-content">
                <!-- Receipt Header -->
                <div class="text-center mb-6">
                    <h1 class="text-2xl font-bold text-gray-900">KANGIS</h1>
                    <h2 class="text-lg font-semibold text-gray-700">Kano Geographic Information System</h2>
                    <p class="text-sm text-gray-600">Payment Receipt</p>
                    <hr class="my-4">
                </div>
                
                <!-- Receipt Details -->
                <div class="space-y-3">
                    <div class="flex justify-between">
                        <span class="font-medium">Receipt No:</span>
                        <span id="print-receipt-no">-</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="font-medium">Date:</span>
                        <span id="print-payment-date">-</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="font-medium">Applicant Name:</span>
                        <span id="print-applicant-name">-</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="font-medium">File Number:</span>
                        <span id="print-file-number">-</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="font-medium">Payment Type:</span>
                        <span id="print-payment-type">-</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="font-medium">Bank Name:</span>
                        <span id="print-bank-name">-</span>
                    </div>
                    <hr class="my-4">
                    <div class="flex justify-between text-lg font-bold">
                        <span>Total Amount:</span>
                        <span id="print-payment-amount" class="amount-cell">-</span>
                    </div>
                </div>
                
                <!-- Receipt Footer -->
                <div class="mt-8 text-center text-sm text-gray-600">
                    <p>Thank you for your payment</p>
                    <p>This is a computer generated receipt</p>
                    <p class="mt-4">Generated on: <span id="print-generated-date"></span></p>
                </div>
            </div>
        </div>
        <div class="modal-footer no-print">
            <button onclick="window.print()" class="inline-flex items-center justify-center rounded-md font-medium text-sm px-4 py-2 transition-all cursor-pointer bg-green-600 text-white hover:bg-green-700 gap-2">
                <i data-lucide="printer" class="h-4 w-4"></i>
                Print
            </button>
            <button onclick="closeModal('printPaymentModal')" class="inline-flex items-center justify-center rounded-md font-medium text-sm px-4 py-2 transition-all cursor-pointer bg-gray-300 text-gray-700 hover:bg-gray-400">
                Close
            </button>
        </div>
    </div>
</div>

<!-- Toast Notifications -->
<div id="toast-container" class="fixed top-4 right-4 z-50 space-y-2">
    <!-- Toast messages will be inserted here -->
</div>

<script>
// Bills & Payments Table Management
let paymentsData = [];
let filteredPaymentsData = [];
let serialCounter = 1;
let currentPayment = null;

document.addEventListener('DOMContentLoaded', function() {
    console.log('Bills & Payments table script loaded');
    
    // Initialize Lucide icons
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
    
    // Load payments data
    loadPaymentsData();
    
    // Setup search functionality
    setupSearch();
    
    // Setup modal handlers
    setupModalHandlers();
});

function loadPaymentsData() {
    console.log('Loading payments data...');
    
    // Show loading state
    showLoadingState('payments-table-body');
    
    // Fetch data from backend
    fetch('/recertification/bills-payments-data', {
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
        console.log('Payments data received:', data);
        
        paymentsData = data.data || [];
        filteredPaymentsData = [...paymentsData];
        
        // Reset serial counter
        serialCounter = 1;
        
        // Update statistics
        updateStatistics(data.statistics || {});
        
        // Render table
        renderPaymentsTable();
    })
    .catch(error => {
        console.error('Error loading payments data:', error);
        showErrorState('payments-table-body');
    });
}

function showLoadingState(tableBodyId) {
    const tableBody = document.getElementById(tableBodyId);
    if (tableBody) {
        tableBody.innerHTML = `
            <tr>
                <td colspan="8" class="text-center py-8">
                    <div class="loading-spinner mx-auto mb-2"></div>
                    <p class="text-gray-600">Loading payment records...</p>
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
                <td colspan="8" class="text-center py-8">
                    <i data-lucide="alert-circle" class="h-8 w-8 text-red-500 mx-auto mb-2"></i>
                    <p class="text-red-600">Failed to load payment records</p>
                    <button onclick="loadPaymentsData()" class="mt-2 text-blue-600 hover:text-blue-800">
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
    document.getElementById('total-amount').textContent = formatCurrency(stats.totalAmount || 0);
    document.getElementById('month-count').textContent = stats.thisMonth || 0;
    document.getElementById('avg-amount').textContent = formatCurrency(stats.avgAmount || 0);
    document.getElementById('payments-count').textContent = filteredPaymentsData.length || 0;
}

function formatCurrency(amount) {
    return new Intl.NumberFormat('en-NG', {
        style: 'currency',
        currency: 'NGN',
        minimumFractionDigits: 2
    }).format(amount);
}

function formatDate(dateString) {
    if (!dateString) return 'N/A';
    try {
        const date = new Date(dateString);
        return date.toLocaleDateString('en-GB', {
            day: '2-digit',
            month: 'short',
            year: 'numeric'
        });
    } catch (e) {
        return dateString;
    }
}

function getPaymentTypeBadge(method) {
    // Default all payments to "Initial Bill" as requested
    return '<span class="badge badge-info">Initial Bill</span>';
}

function renderPaymentsTable() {
    const tableBody = document.getElementById('payments-table-body');
    const noResults = document.getElementById('no-results');
    
    if (!tableBody) return;
    
    if (!filteredPaymentsData || filteredPaymentsData.length === 0) {
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
    const rows = filteredPaymentsData.map(payment => {
        const actionMenuId = `action-menu-${payment.id}`;
        const serialNo = currentSerial++;
        
        return `
            <tr class="table-row border-b hover:bg-gray-50">
                <td class="p-3" style="max-width: 60px;">
                    <div class="text-sm font-medium text-gray-900">${serialNo}</div>
                </td>
                <td class="p-3" style="max-width: 200px;">
                    <div class="text-sm font-medium text-gray-900 truncate" title="${payment.applicant_name || 'N/A'}">${payment.applicant_name || 'N/A'}</div>
                </td>
                <td class="p-3" style="max-width: 120px;">
                    ${getPaymentTypeBadge(payment.payment_method)}
                </td>
                <td class="p-3" style="max-width: 140px;">
                    <div class="text-sm text-gray-900 truncate font-mono" title="${payment.receipt_no || 'N/A'}">${payment.receipt_no || 'N/A'}</div>
                </td>
                <td class="p-3" style="max-width: 150px;">
                    <div class="text-sm text-gray-900 truncate" title="${payment.bank_name || 'N/A'}">${payment.bank_name || 'N/A'}</div>
                </td>
                <td class="p-3" style="max-width: 120px;">
                    <div class="text-sm font-semibold text-green-600 amount-cell">${formatCurrency(payment.payment_amount || 0)}</div>
                </td>
                <td class="p-3" style="max-width: 120px;">
                    <div class="text-sm text-gray-900">${formatDate(payment.payment_date)}</div>
                </td>
                <td class="p-3" style="max-width: 100px;">
                    <div class="relative">
                        <button 
                            onclick="toggleActionMenu('${actionMenuId}')"
                            class="inline-flex items-center justify-center rounded-md font-medium text-sm px-2 py-1 transition-all cursor-pointer bg-transparent border border-gray-300 text-gray-700 hover:bg-gray-50"
                        >
                            <i data-lucide="more-horizontal" class="h-3 w-3"></i>
                        </button>
                        
                        <div id="${actionMenuId}" class="hidden absolute right-0 top-full mt-1 w-56 bg-white rounded-md shadow-lg border border-gray-200 z-50">
                            <div class="py-1">
                                <button onclick="viewPaymentDetails(${payment.id})" class="flex items-center w-full px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 gap-2">
                                    <i data-lucide="eye" class="h-4 w-4"></i>
                                    View Details
                                </button>
                                <button onclick="printPaymentReceipt(${payment.id})" class="flex items-center w-full px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 gap-2">
                                    <i data-lucide="printer" class="h-4 w-4"></i>
                                    Print Receipt
                                </button>
                            </div>
                        </div>
                    </div>
                </td>
            </tr>
        `;
    }).join('');
    
    tableBody.innerHTML = rows;
    
    // Update payments count
    document.getElementById('payments-count').textContent = filteredPaymentsData.length;
    
    // Reinitialize Lucide icons
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
}

function filterPayments() {
    const paymentMethodFilter = document.getElementById('payment-method-filter').value;
    const dateFrom = document.getElementById('date-from').value;
    const dateTo = document.getElementById('date-to').value;
    
    filteredPaymentsData = paymentsData.filter(payment => {
        // Payment method filter
        if (paymentMethodFilter && paymentMethodFilter !== 'initial_bill') {
            // Since all payments are "Initial Bill", only show if "initial_bill" is selected
            return false;
        }
        
        // Date range filter
        if (dateFrom || dateTo) {
            const paymentDate = new Date(payment.payment_date);
            if (dateFrom && paymentDate < new Date(dateFrom)) {
                return false;
            }
            if (dateTo && paymentDate > new Date(dateTo)) {
                return false;
            }
        }
        
        return true;
    });
    
    renderPaymentsTable();
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
                filteredPaymentsData = [...paymentsData];
                filterPayments(); // Apply other filters
                return;
            }
            
            filteredPaymentsData = paymentsData.filter(payment => {
                return (
                    (payment.applicant_name && payment.applicant_name.toLowerCase().includes(searchTerm)) ||
                    (payment.receipt_no && payment.receipt_no.toLowerCase().includes(searchTerm)) ||
                    (payment.bank_name && payment.bank_name.toLowerCase().includes(searchTerm)) ||
                    (payment.file_number && payment.file_number.toLowerCase().includes(searchTerm))
                );
            });
            
            filterPayments(); // Apply other filters to search results
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
        
        // Close modals when clicking outside
        if (event.target.classList.contains('modal')) {
            event.target.style.display = 'none';
        }
    });
    
    // ESC key to close modals
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            // Close all action menus
            document.querySelectorAll('[id^="action-menu-"]').forEach(menu => {
                menu.classList.add('hidden');
            });
            
            // Close all modals
            document.querySelectorAll('.modal').forEach(modal => {
                modal.style.display = 'none';
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

// Modal Functions
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'block';
        // Reinitialize icons after modal opens
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
    }
}

function findPaymentById(id) {
    return paymentsData.find(payment => payment.id == id);
}

// Action Functions
function viewPaymentDetails(id) {
    console.log('Viewing payment details:', id);
    closeActionMenus();
    
    const payment = findPaymentById(id);
    if (!payment) {
        showToast('Payment not found', 'error');
        return;
    }
    
    currentPayment = payment;
    
    // Populate modal with payment details
    document.getElementById('view-applicant-name').textContent = payment.applicant_name || 'N/A';
    document.getElementById('view-payment-type').textContent = 'Initial Bill';
    document.getElementById('view-receipt-no').textContent = payment.receipt_no || 'N/A';
    document.getElementById('view-bank-name').textContent = payment.bank_name || 'N/A';
    document.getElementById('view-payment-amount').textContent = formatCurrency(payment.payment_amount || 0);
    document.getElementById('view-payment-date').textContent = formatDate(payment.payment_date);
    document.getElementById('view-file-number').textContent = payment.file_number || 'N/A';
    document.getElementById('view-applicant-type').textContent = payment.applicant_type || 'N/A';
    
    openModal('viewPaymentModal');
}

function printPaymentReceipt(id) {
    console.log('Printing payment receipt:', id);
    closeActionMenus();
    
    const payment = findPaymentById(id);
    if (!payment) {
        showToast('Payment not found', 'error');
        return;
    }
    
    currentPayment = payment;
    
    // Populate print modal with payment details
    document.getElementById('print-applicant-name').textContent = payment.applicant_name || 'N/A';
    document.getElementById('print-payment-type').textContent = 'Initial Bill';
    document.getElementById('print-receipt-no').textContent = payment.receipt_no || 'N/A';
    document.getElementById('print-bank-name').textContent = payment.bank_name || 'N/A';
    document.getElementById('print-payment-amount').textContent = formatCurrency(payment.payment_amount || 0);
    document.getElementById('print-payment-date').textContent = formatDate(payment.payment_date);
    document.getElementById('print-file-number').textContent = payment.file_number || 'N/A';
    document.getElementById('print-generated-date').textContent = new Date().toLocaleDateString('en-GB');
    
    openModal('printPaymentModal');
}

function printPayment() {
    if (currentPayment) {
        printPaymentReceipt(currentPayment.id);
        closeModal('viewPaymentModal');
    }
}

function exportPayments() {
    console.log('Exporting payments...');
    showToast('Exporting payment records...', 'info');
    
    // Create export request
    window.location.href = '/recertification/export-payments';
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
window.viewPaymentDetails = viewPaymentDetails;
window.printPaymentReceipt = printPaymentReceipt;
window.printPayment = printPayment;
window.exportPayments = exportPayments;
window.loadPaymentsData = loadPaymentsData;
window.filterPayments = filterPayments;
window.openModal = openModal;
window.closeModal = closeModal;

console.log('Bills & Payments table script initialized');
</script>

@endsection