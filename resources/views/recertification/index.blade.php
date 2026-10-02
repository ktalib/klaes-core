@extends('layouts.app')
@section('page-title')
    {{ __('Recertification') }}
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
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<!-- DataTables CSS -->
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">
<!-- DataTables JS -->
<script type="text/javascript" src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script type="text/javascript" src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<script type="text/javascript" src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
<script type="text/javascript" src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>

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

/* Toggle switch styles */
.toggle-switch {
  position: relative;
  display: inline-block;
  width: 44px;
  height: 24px;
}

.toggle-switch input {
  opacity: 0;
  width: 0;
  height: 0;
}

.toggle-slider {
  position: absolute;
  cursor: pointer;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: #ccc;
  transition: .4s;
  border-radius: 24px;
}

.toggle-slider:before {
  position: absolute;
  content: "";
  height: 18px;
  width: 18px;
  left: 3px;
  bottom: 3px;
  background-color: white;
  transition: .4s;
  border-radius: 50%;
}

input:checked + .toggle-slider {
  background-color: #3b82f6;
}

input:checked + .toggle-slider:before {
  transform: translateX(20px);
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

/* Responsive table styles */
#recertification-table {
  table-layout: auto;
}

#recertification-table th,
#recertification-table td {
  word-wrap: break-word;
  overflow-wrap: break-word;
  max-width: 200px;
}

/* Mobile responsive adjustments */
@media (max-width: 768px) {
  #recertification-table th,
  #recertification-table td {
    padding: 8px 4px;
    font-size: 0.75rem;
    max-width: 120px;
  }
  
  #recertification-table th {
    min-width: 80px;
  }
  
  .container {
    padding-left: 1rem;
    padding-right: 1rem;
  }
}

@media (max-width: 640px) {
  #recertification-table th,
  #recertification-table td {
    padding: 6px 2px;
    font-size: 0.7rem;
    max-width: 100px;
  }
  
  #recertification-table th {
    min-width: 60px;
  }
}

/* DataTables responsive overrides */
.dataTables_wrapper .dataTables_scroll {
  overflow-x: auto;
}

.dataTables_wrapper .dataTables_scrollBody {
  overflow-x: auto;
}
</style>
<div class="flex-1 overflow-auto">
    <!-- Header -->
   @include('admin.header')
    <!-- Dashboard Content -->
    <div class="p-6">
      @include('recertification.js.form_js')
 
<div class="container mx-auto py-6 space-y-6 max-w-7xl px-4 sm:px-6 lg:px-8">
  
  <!-- Header -->
  <div class="flex justify-between items-center">
    <div>
       
      <p class="text-gray-600 mt-1">
        Manage approved certificate recertification and re-issuance applications
      </p>
    </div>
    <div class="flex items-center gap-4">
      <div class="flex items-center space-x-2">
        <label class="toggle-switch">
          <input type="checkbox" id="ocr-mode-toggle">
          <span class="toggle-slider"></span>
        </label>
        <label for="ocr-mode-toggle" class="flex items-center gap-2 cursor-pointer">
          <i data-lucide="scan" class="h-4 w-4"></i>
          OCR Mode
        </label>
      </div>
      <div class="flex gap-2">
        <!-- <button id="new-application-btn" class="inline-flex items-center justify-center rounded-md font-medium text-sm px-4 py-2 transition-all cursor-pointer border-0 bg-blue-600 text-white hover:bg-blue-700 gap-2">
          <i data-lucide="plus" class="h-4 w-4"></i>
          New Application (Modal)
        </button> -->
        <a href="{{ url('/recertification/application') }}" class="inline-flex items-center justify-center rounded-md font-medium text-sm px-4 py-2 transition-all cursor-pointer border border-blue-600 text-blue-600 hover:bg-blue-50 gap-2">
          <i data-lucide="external-link" class="h-4 w-4"></i>
          New Application  
        </a>
      </div>
    </div>
  </div>

  <!-- Statistics -->
  <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <div class="bg-white rounded-lg shadow border border-gray-200 p-6">
      <div class="flex items-center">
        <div class="p-2 bg-green-100 rounded-lg">
          <i data-lucide="check-circle" class="h-6 w-6 text-green-600"></i>
        </div>
        <div class="ml-4">
          <p class="text-sm font-medium text-gray-600">Total Applications</p>
          <p class="text-2xl font-bold text-gray-900" id="total-applications">0</p>
        </div>
      </div>
    </div>
    
    <div class="bg-white rounded-lg shadow border border-gray-200 p-6">
      <div class="flex items-center">
        <div class="p-2 bg-blue-100 rounded-lg">
          <i data-lucide="file-text" class="h-6 w-6 text-blue-600"></i>
        </div>
        <div class="ml-4">
          <p class="text-sm font-medium text-gray-600">This Month</p>
          <p class="text-2xl font-bold text-gray-900" id="this-month-applications">0</p>
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
            placeholder="Search by applicant name, application number, plot number, or certificate number..."
            class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-md text-sm transition-all focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10"
          />
        </div>
        <button class="inline-flex items-center justify-center rounded-md font-medium text-sm px-3 py-2 transition-all cursor-pointer bg-transparent border border-gray-300 text-gray-700 hover:bg-gray-50">
          <i data-lucide="filter" class="h-4 w-4"></i>
        </button>
      </div>
    </div>
  </div>

  <!-- Applications Table -->
  <div class="bg-white rounded-lg shadow border border-gray-200">
    <div class="p-6 border-b border-gray-200">
      <div class="flex items-center justify-between">
        <h3 class="text-xl font-semibold text-gray-900 flex items-center gap-2">
          <i data-lucide="check-circle" class="h-5 w-5 text-green-600"></i>
           Recertification Applications (<span id="applications-count">8</span>)
        </h3>
        <span class="badge badge-success">
          All applications have been successfully processed
        </span>
      </div>
    </div>
    
    <div class="rounded-md border border-gray-200" id="applications-table-container">
      <div class="overflow-x-auto">
        <!-- Table -->
        <table class="w-full divide-y divide-gray-200" id="recertification-table">
          <thead class="bg-gray-50">
            <tr>
               
              <th scope="col" class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                <div class="truncate">New KANGIS FileNo</div>
              </th>
              <th scope="col" class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                <div class="truncate">KANGIS FileNo</div>
              </th>
              <th scope="col" class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                <div class="truncate">MLPP FileNo</div>
              </th>
              <th scope="col" class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                <div class="truncate">RegNo</div>
              </th>
              <th scope="col" class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                <div class="truncate">Application Type</div>
              </th>
              <th scope="col" class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                <div class="truncate">Applicant Name</div>
              </th>
              <th scope="col" class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                <div class="truncate">Land Use</div>
              </th>
              <th scope="col" class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                <div class="truncate">Plot Details</div>
              </th>
              <th scope="col" class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                <div class="truncate">LGA</div>
              </th>
              <th scope="col" class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                <div class="truncate">Application Date</div>
              </th>
              <th scope="col" class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                <div class="truncate">Acknowledgement</div>
              </th>
              <th scope="col" class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                <div class="truncate">Actions</div>
              </th>
            </tr>
          </thead>
          <tbody id="applications-table-body" class="bg-white divide-y divide-gray-200">
            <!-- Applications will be loaded dynamically -->
          </tbody>
        </table>
      </div>
      </div>
        
        <!-- No results state -->
        <div id="no-results" class="hidden text-center py-12">
          <i data-lucide="file-text" class="h-12 w-12 text-gray-400 mx-auto mb-4"></i>
          <h3 class="text-lg font-medium mb-2 text-gray-900">No applications found</h3>
          <p id="no-results-message" class="text-gray-600">
            No approved applications available
          </p>
        </div>
      </div>
    

<!-- OCR Mode View -->
<div id="ocr-mode-view" class="hidden min-h-screen bg-gray-50">
  <div class="container mx-auto py-6 space-y-6 max-w-6xl px-4 sm:px-6 lg:px-8">
    <div class="flex items-center gap-4 mb-6">
      <button id="back-from-ocr" class="inline-flex items-center justify-center rounded-md font-medium text-sm px-3 py-2 transition-all cursor-pointer bg-transparent border border-gray-300 text-gray-700 hover:bg-gray-50 gap-2">
        <i data-lucide="arrow-left" class="h-4 w-4"></i>
        Back to Applications
      </button>
      <div>
        <h1 class="text-3xl font-bold text-gray-900">OCR Document Processing</h1>
        <p class="text-gray-600">Process recertification documents using OCR technology</p>
      </div>
    </div>
    
    <div class="bg-white rounded-lg shadow border border-gray-200 p-8">
      <div class="text-center">
        <i data-lucide="scan" class="h-16 w-16 text-blue-600 mx-auto mb-4"></i>
        <h3 class="text-xl font-semibold mb-2">OCR Processing Mode</h3>
        <p class="text-gray-600 mb-6">Upload documents to automatically extract recertification data</p>
        <button class="inline-flex items-center justify-center rounded-md font-medium text-sm px-6 py-3 transition-all cursor-pointer border-0 bg-blue-600 text-white hover:bg-blue-700 gap-2">
          <i data-lucide="upload" class="h-4 w-4"></i>
          Upload Document
        </button>
      </div>
    </div>
  </div>
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
            <i data-lucide="check" class="h-4 w-4"></i>
          </template>
          <span x-text="loading ? 'Updating...' : 'Assign Serial Number'"></span>
        </button>
      </div>
    </form>
  </div>
</div>

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

<!-- Toast Notifications -->
<div id="toast-container" class="fixed top-4 right-4 z-50 space-y-2">
  <!-- Toast messages will be inserted here -->
</div>

    
    </div>
    <!-- Include the New Application  form Modal -->
 
    @include('recertification.cofo_details_modal')
    <!-- Footer -->
    @include('admin.footer')
  </div>



@include('recertification.ack_modal')
@include('recertification.js.js')

<script>
// Load statistics when page loads
document.addEventListener('DOMContentLoaded', function() {
    loadStatistics();
});

function loadStatistics() {
    fetch('/recertification/statistics', {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.statistics) {
            // Update statistics display
            document.getElementById('total-applications').textContent = data.statistics.total_applications || 0;
            document.getElementById('this-month-applications').textContent = data.statistics.this_month_applications || 0;
            
            // Update applications count in table header
            document.getElementById('applications-count').textContent = data.statistics.total_applications || 0;
        } else {
            console.error('Failed to load statistics:', data.error || 'Unknown error');
        }
    })
    .catch(error => {
        console.error('Error loading statistics:', error);
    });
}

// Refresh statistics when table is reloaded
function refreshStatistics() {
    loadStatistics();
}
</script>

@endsection

 