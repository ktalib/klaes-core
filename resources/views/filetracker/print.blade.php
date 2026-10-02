<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>File Tracking Sheet - KLAES</title>
<!-- Tailwind CSS -->
<script src="https://cdn.tailwindcss.com"></script>
<!-- Lucide Icons -->
<script src="https://unpkg.com/lucide@latest"></script>
<!-- QR Code Library -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrious/4.0.2/qrious.min.js"></script>

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
/* Print styles */
@media print {
  @page {
    size: A4 landscape;
    margin: 5mm;
  }
  body * {
    visibility: hidden;
  }
  #print-content, #print-content * {
    visibility: visible;
  }
  #print-content {
    position: absolute;
    left: 0;
    top: 0;
    width: 100%;
    padding: 5px;
    font-size: 9px;
    transform: scale(0.98);
    transform-origin: top left;
  }
  .no-print {
    display: none !important;
  }
  /* Ensure compact display */
  .mb-4 {
    margin-bottom: 0.5rem !important;
  }
  .mb-3 {
    margin-bottom: 0.25rem !important;
  }
  .mb-2 {
    margin-bottom: 0.15rem !important;
  }
  .p-4 {
    padding: 0.5rem !important;
  }
  .p-3 {
    padding: 0.25rem !important;
  }
  .p-2 {
    padding: 0.15rem !important;
  }
  .gap-4 {
    gap: 0.5rem !important;
  }
  .h-12 {
    height: 2rem !important;
  }
  .h-\[80px\] {
    height: 3rem !important;
  }
  .py-3 {
    padding-top: 0.5rem !important;
    padding-bottom: 0.5rem !important;
  }
}

/* Badge styles */
.badge {
  display: inline-flex;
  align-items: center;
  border-radius: 9999px;
  font-size: 0.65rem;
  font-weight: 500;
  line-height: 1;
  padding: 0.15rem 0.5rem;
  white-space: nowrap;
}

.badge-default {
  background-color: #3b82f6;
  color: white;
}

.badge-outline {
  background-color: transparent;
  border: 1px solid #e5e7eb;
  color: #374151;
}

.badge-secondary {
  background-color: #f1f5f9;
  color: #0f172a;
}

.badge-destructive {
  background-color: #ef4444;
  color: white;
}

.badge-warning {
  background-color: #f59e0b;
  color: white;
}

/* Button styles */
.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 0.375rem;
  font-weight: 500;
  font-size: 0.875rem;
  line-height: 1.25rem;
  padding: 0.5rem 1rem;
  transition: all 0.2s;
  cursor: pointer;
  border: none;
}

.btn-primary {
  background-color: #3b82f6;
  color: white;
}

.btn-primary:hover {
  background-color: #2563eb;
}

/* Compact table styles */
.compact-table th,
.compact-table td {
  padding: 0.25rem 0.5rem;
  font-size: 0.75rem;
}

/* QR Code container */
.qr-container {
  border: 1px solid #e5e7eb;
  border-radius: 0.375rem;
  padding: 0.5rem;
  background-color: #ffffff;
}

#qr-code-canvas {
  border: 1px solid #d1d5db;
  border-radius: 0.25rem;
}
</style>
</head>
<body class="bg-gray-50 text-sm">

<!-- Print Button (Fixed Position) -->
<div class="fixed top-2 right-2 z-10 no-print">
  <button id="print-btn" class="btn btn-primary shadow-lg text-xs">
    <i data-lucide="printer" class="h-3 w-3 mr-1"></i>
    Print
  </button>
</div>

<!-- Tracking Sheet Content -->
<div id="print-content" class="container mx-auto py-3 max-w-7xl">
  <div class="bg-white rounded-lg shadow-sm border p-4">
    <div class="tracking-sheet">
      <!-- Header Section -->
      <div class="flex justify-between items-start mb-4 pb-2 border-b-2 border-gray-800">
        <div>
          <div class="flex items-center gap-2">
            <div class="w-8 h-8 flex items-center justify-center bg-blue-100 rounded-full">
              <i data-lucide="file-text" class="h-5 w-5 text-blue-700"></i>
            </div>
            <div>
              <h2 class="text-lg font-bold text-gray-900">KANO STATE LAND REGISTRY</h2>
              <h3 class="text-sm font-semibold text-gray-700">FILE TRACKING SHEET</h3>
            </div>
          </div>
        </div>
        <div class="text-right">
          <div class="inline-block border border-gray-300 rounded-md px-3 py-1 bg-gray-50">
            <p class="text-sm font-bold text-blue-600">Tracking ID: <span id="tracking-id">{{ isset($tracking) ? 'TRK-' . str_pad($tracking->id, 6, '0', STR_PAD_LEFT) : 'TRK-000000' }}</span></p>
            <p class="text-xs text-gray-500">Generated: <span id="generated-date">{{ now()->format('Y-m-d H:i A') }}</span></p>
          </div>
        </div>
      </div>

      <!-- Main Content Grid -->
      <div class="grid grid-cols-12 gap-4 mb-4">
        
        <!-- File Details - Left Side -->
        <div class="col-span-8">
          <div class="text-sm font-bold border-b border-gray-300 pb-1 mb-2 text-gray-800">
            File Details
          </div>
          <div class="border border-gray-200 rounded-md p-3 bg-gray-50 mb-4">
            <h3 class="text-base font-bold mb-2 text-gray-900" id="file-title">{{ isset($tracking) ? ($tracking->fileIndexing->file_title ?? 'No Title Available') : 'No File Selected' }}</h3>
            <div class="flex items-center gap-2 mb-2">
              @if(isset($tracking))
                @php
                  $statusBadge = match($tracking->status) {
                    'active' => ['badge-default', 'Active'],
                    'checked_out' => ['badge-warning', 'Checked Out'],
                    'overdue' => ['badge-destructive', 'Overdue'],
                    'returned' => ['badge-outline', 'Returned'],
                    'lost' => ['badge-destructive', 'Lost'],
                    'archived' => ['badge-outline', 'Archived'],
                    'in_process' => ['badge-default', 'In Process'],
                    'pending' => ['badge-warning', 'Pending'],
                    'on_hold' => ['badge-destructive', 'On Hold'],
                    'completed' => ['badge-outline', 'Completed'],
                    default => ['badge-secondary', ucfirst($tracking->status)]
                  };
                  $priorityBadge = match($tracking->status) {
                    'overdue' => ['badge-destructive', 'High Priority'],
                    'checked_out' => ['badge-warning', 'Medium Priority'],
                    default => ['badge-default', 'Normal Priority']
                  };
                @endphp
                <span class="badge {{ $statusBadge[0] }}" id="status-badge">
                  Status: {{ $statusBadge[1] }}
                </span>
                <span class="badge {{ $priorityBadge[0] }}" id="priority-badge">
                  Priority: {{ $priorityBadge[1] }}
                </span>
              @else
                <span class="badge badge-secondary" id="status-badge">Status: Unknown</span>
                <span class="badge badge-default" id="priority-badge">Priority: Normal</span>
              @endif
            </div>
          </div>

          <!-- File Information and Current Location in 2 columns -->
          <div class="grid grid-cols-2 gap-4">
            <div>
              <div class="text-sm font-bold border-b border-gray-300 pb-1 mb-2 text-gray-800">
                File Information
              </div>
              <div class="bg-white rounded-md border border-gray-200 p-2">
                <div class="space-y-1 text-xs">
                  <div class="flex justify-between">
                    <span class="text-gray-600">MLPP Number:</span>
                    <span class="font-medium" id="mls-number">{{ isset($mlsNumber) ? $mlsNumber : 'N/A' }}</span>
                  </div>
                  <div class="flex justify-between">
                    <span class="text-gray-600">KANGIS Number:</span>
                    <span class="font-medium" id="kangis-number">{{ isset($kangisNumber) ? $kangisNumber : 'N/A' }}</span>
                  </div>
                  <div class="flex justify-between">
                    <span class="text-gray-600">New KANGIS:</span>
                    <span class="font-medium" id="new-kangis-number">{{ isset($newKangisNumber) ? $newKangisNumber : 'N/A' }}</span>
                  </div>
                  <div class="flex justify-between">
                    <span class="text-gray-600">Date Received:</span>
                    <span class="font-medium" id="date-received">{{ isset($tracking) ? ($tracking->date_received ? $tracking->date_received->format('Y-m-d') : 'N/A') : 'N/A' }}</span>
                  </div>
                  <div class="flex justify-between">
                    <span class="text-gray-600">Due Date:</span>
                    <span class="font-medium" id="due-date">{{ isset($tracking) ? ($tracking->due_date ? $tracking->due_date->format('Y-m-d') : 'N/A') : 'N/A' }}</span>
                  </div>
                </div>
              </div>
            </div>
            
            <div>
              <div class="text-sm font-bold border-b border-gray-300 pb-1 mb-2 text-gray-800">
                Current Location
              </div>
              <div class="bg-white rounded-md border border-gray-200 p-2">
                <div class="space-y-2 text-xs">
                  <div class="flex items-center gap-2">
                    <div class="bg-blue-100 text-blue-700 p-1 rounded">
                      <i data-lucide="map-pin" class="h-3 w-3"></i>
                    </div>
                    <div>
                      <p class="font-medium" id="current-location">{{ isset($tracking) ? ($tracking->current_location ?? 'Not Set') : 'Not Set' }}</p>
                      <p class="text-gray-500">Last updated: <span id="last-updated">{{ isset($tracking) ? $tracking->updated_at->format('Y-m-d') : 'N/A' }}</span></p>
                    </div>
                  </div>
                  <div class="flex items-center gap-2">
                    <div class="bg-green-100 text-green-700 p-1 rounded">
                      <i data-lucide="user" class="h-3 w-3"></i>
                    </div>
                    <div>
                      <p class="font-medium" id="current-handler">{{ isset($tracking) ? ($tracking->current_handler ?? 'Not Assigned') : 'Not Assigned' }}</p>
                      <p class="text-gray-500">Current handler</p>
                    </div>
                  </div>
                  <div class="flex items-center gap-2">
                    <div class="bg-purple-100 text-purple-700 p-1 rounded">
                      <i data-lucide="radio" class="h-3 w-3"></i>
                    </div>
                    <div>
                      <p class="font-medium" id="last-scanned">{{ isset($tracking) ? $tracking->updated_at->format('Y-m-d H:i A') : 'N/A' }}</p>
                      <p class="text-gray-500">Last updated</p>
                    </div>
                  </div>
                  @if(isset($tracking) && $tracking->rfid_tag)
                    <div class="flex items-center gap-2">
                      <div class="bg-indigo-100 text-indigo-700 p-1 rounded">
                        <i data-lucide="tag" class="h-3 w-3"></i>
                      </div>
                      <div>
                        <p class="font-medium" id="rfid-tag">{{ $tracking->rfid_tag }}</p>
                        <p class="text-gray-500">RFID Tag</p>
                      </div>
                    </div>
                  @endif
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- QR Code - Right Side -->
        <div class="col-span-4">
          <div class="text-sm font-bold border-b border-gray-300 pb-1 mb-2 text-gray-800">
            QR Code
          </div>
          <div class="qr-container">
            <div class="flex flex-col items-center">
              <canvas id="qr-code-canvas" width="100" height="100" class="mx-auto mb-1"></canvas>
              <p class="text-xs font-medium text-center">Contains file details</p>
              <p class="text-xs text-gray-500">File: <span id="file-display">{{ isset($tracking) ? ($tracking->fileIndexing->file_number ?? 'N/A') : 'N/A' }}</span></p>
              @if(isset($tracking) && $tracking->rfid_tag)
                <div class="mt-1 inline-flex items-center gap-1 bg-blue-50 text-blue-700 px-2 py-1 rounded border border-blue-200 text-xs">
                  <i data-lucide="tag" class="h-3 w-3"></i>
                  <span class="font-medium" id="rfid-display">{{ $tracking->rfid_tag }}</span>
                </div>
              @endif
            </div>
          </div>
        </div>
      </div>

      <!-- Movement History -->
      <div class="mb-4">
        <div class="text-sm font-bold border-b border-gray-300 pb-1 mb-2 text-gray-800">
          Movement History
        </div>
        <div class="bg-white rounded-md border border-gray-200 overflow-hidden">
          <table class="w-full text-xs compact-table">
            <thead class="bg-gray-50">
              <tr class="border-b">
                <th class="text-left py-1 px-2 font-medium text-gray-600">Date & Time</th>
                <th class="text-left py-1 px-2 font-medium text-gray-600">Location</th>
                <th class="text-left py-1 px-2 font-medium text-gray-600">Handler</th>
                <th class="text-left py-1 px-2 font-medium text-gray-600">Action</th>
                <th class="text-left py-1 px-2 font-medium text-gray-600">Method</th>
              </tr>
            </thead>
            <tbody id="history-table-body">
              @if(isset($tracking) && $tracking->movement_history)
                @foreach(array_slice($tracking->movement_history, 0, 10) as $index => $movement)
                  <tr class="{{ $index % 2 === 0 ? 'bg-gray-50' : 'bg-white' }}">
                    <td class="py-1 px-2 border-b text-xs">
                      <div class="flex items-center gap-1">
                        <i data-lucide="calendar" class="h-2 w-2 text-gray-400"></i>
                        <span>{{ \Carbon\Carbon::parse($movement['timestamp'] ?? now())->format('Y-m-d H:i A') }}</span>
                      </div>
                    </td>
                    <td class="py-1 px-2 border-b text-xs">
                      <div class="flex items-center gap-1">
                        <i data-lucide="map-pin" class="h-2 w-2 text-gray-400"></i>
                        <span>{{ $movement['to_location'] ?? $movement['initial_location'] ?? 'Unknown' }}</span>
                      </div>
                    </td>
                    <td class="py-1 px-2 border-b text-xs">
                      <div class="flex items-center gap-1">
                        <i data-lucide="user" class="h-2 w-2 text-gray-400"></i>
                        <span>{{ $movement['user_name'] ?? 'System' }}</span>
                      </div>
                    </td>
                    <td class="py-1 px-2 border-b text-xs">{{ ucfirst(str_replace('_', ' ', $movement['action'] ?? 'Unknown action')) }}</td>
                    <td class="py-1 px-2 border-b text-xs">
                      <span class="{{ isset($movement['rfid_tag']) ? 'text-blue-600 font-medium' : 'text-gray-600' }}">
                        {{ isset($movement['rfid_tag']) ? 'RFID Scan' : 'Manual' }}
                      </span>
                    </td>
                  </tr>
                @endforeach
              @else
                <tr>
                  <td colspan="5" class="py-3 px-2 text-center text-gray-500 text-xs">No movement history available</td>
                </tr>
              @endif
            </tbody>
          </table>
        </div>
      </div>

      <!-- Signature and Notes -->
      <div class="grid grid-cols-2 gap-4 mb-3">
        <div>
          <div class="text-sm font-bold border-b border-gray-300 pb-1 mb-2 text-gray-800">
            Signature
          </div>
          <div class="bg-white rounded-md border border-gray-200 p-3">
            <div class="h-12 border-b border-dashed mb-2"></div>
            <div class="flex justify-between items-center text-xs">
              <span class="text-gray-500">Authorized Signature</span>
              <span class="text-gray-500">Date: _____________</span>
            </div>
          </div>
        </div>
        <div>
          <div class="text-sm font-bold border-b border-gray-300 pb-1 mb-2 text-gray-800">
            Notes
          </div>
          <div class="bg-white rounded-md border border-gray-200 p-3 h-[80px]">
            <p class="text-xs text-gray-700" id="notes-content">
                @if(isset($tracking) && $tracking->movement_history)
                  @php
                    $movementHistory = $tracking->movement_history;
                    $lastMovement = is_array($movementHistory) && count($movementHistory) > 0 ? $movementHistory[0] : null;
                    $notes = $lastMovement['notes'] ?? 'No notes available';
                  @endphp
                  {{ $notes }}
                @else
                  No notes available
                @endif
              </p>
          </div>
        </div>
      </div>

      <!-- Footer -->
      <div class="pt-2 border-t border-gray-200">
        <div class="flex items-center justify-between text-xs">
          <div class="flex items-center gap-2">
            <div class="bg-gray-100 p-1 rounded-full">
              <i data-lucide="file-barcode" class="h-3 w-3 text-gray-500"></i>
            </div>
            <div>
              <p class="font-medium text-gray-700">KANO STATE LAND REGISTRY</p>
              <p class="text-gray-500">File Tracking System</p>
            </div>
          </div>
          <div class="text-right">
            <p class="text-gray-500">This tracking sheet should accompany the file at all times.</p>
            <p class="text-gray-500">For inquiries, contact File Management Office at ext. 2145.</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
// Get file details from server-side data
const fileDetails = {
  id: "{{ isset($tracking) ? 'TRK-' . str_pad($tracking->id, 6, '0', STR_PAD_LEFT) : 'TRK-000000' }}",
  fileName: "{{ isset($tracking) ? ($tracking->fileIndexing->file_title ?? 'No Title Available') : 'No File Selected' }}",
  fileNumber: "{{ isset($tracking) ? ($tracking->fileIndexing->file_number ?? 'N/A') : 'N/A' }}",
  mlsNumber: "{{ isset($mlsNumber) ? $mlsNumber : 'N/A' }}",
  kangisFileNo: "{{ isset($kangisNumber) ? $kangisNumber : 'N/A' }}",
  newKangisFileNo: "{{ isset($newKangisNumber) ? $newKangisNumber : 'N/A' }}",
  currentLocation: "{{ isset($tracking) ? ($tracking->current_location ?? 'Not Set') : 'Not Set' }}",
  currentHandler: "{{ isset($tracking) ? ($tracking->current_handler ?? 'Not Assigned') : 'Not Assigned' }}",
  status: "{{ isset($tracking) ? ucfirst($tracking->status) : 'Unknown' }}",
  priority: "{{ isset($tracking) && $tracking->status === 'overdue' ? 'High' : 'Normal' }}",
  dateReceived: "{{ isset($tracking) ? ($tracking->date_received ? $tracking->date_received->format('Y-m-d') : 'N/A') : 'N/A' }}",
  dueDate: "{{ isset($tracking) ? ($tracking->due_date ? $tracking->due_date->format('Y-m-d') : 'N/A') : 'N/A' }}",
  rfidTag: "{{ isset($tracking) && $tracking->rfid_tag ? $tracking->rfid_tag : 'Not Assigned' }}",
  lastScanned: "{{ isset($tracking) ? $tracking->updated_at->format('Y-m-d H:i A') : 'N/A' }}",
};

// DOM elements
const elements = {
  printBtn: document.getElementById('print-btn'),
  qrCodeCanvas: document.getElementById('qr-code-canvas'),
};

function generateQRCode() {
  console.log('Starting QR code generation...');
  
  const qrData = `TRK:${fileDetails.id}|FILE:${fileDetails.fileNumber}|KANGIS:${fileDetails.kangisFileNo}|LOC:${fileDetails.currentLocation}|RFID:${fileDetails.rfidTag}`;

  try {
    if (typeof QRious === 'undefined') {
      throw new Error('QRious library not loaded');
    }

    const qr = new QRious({
      element: elements.qrCodeCanvas,
      value: qrData,
      size: 100,
      level: 'M',
      background: '#ffffff',
      foreground: '#000000'
    });

    console.log('QR Code generated successfully');

  } catch (error) {
    console.error('QR Code generation error:', error);
    
    const ctx = elements.qrCodeCanvas.getContext('2d');
    ctx.fillStyle = '#f3f4f6';
    ctx.fillRect(0, 0, 100, 100);
    ctx.strokeStyle = '#d1d5db';
    ctx.strokeRect(0, 0, 100, 100);
    ctx.fillStyle = '#6b7280';
    ctx.font = '10px Arial';
    ctx.textAlign = 'center';
    ctx.fillText('QR Code', 50, 45);
    ctx.fillText('Placeholder', 50, 58);
  }
}

function handlePrint() {
  // Set a slight delay to ensure all elements are properly laid out
  setTimeout(() => {
    window.print();
  }, 200);
}

// Auto-print functionality
function autoPrint() {
  // Set a delay to ensure all content is fully rendered
  setTimeout(() => {
    console.log('Auto-printing document...');
    window.print();
  }, 1500); // 1.5-second delay before printing
}

// Event listeners
if (elements.printBtn) {
  elements.printBtn.addEventListener('click', handlePrint);
}

// Initialize when page loads
document.addEventListener('DOMContentLoaded', () => {
  console.log('Page loaded, initializing tracking sheet...');
  
  // Re-initialize Lucide icons
  lucide.createIcons();
  
  // Generate QR code
  setTimeout(() => {
    generateQRCode();
  }, 100);
  
  console.log('Tracking sheet initialization complete');
  
  // Trigger automatic printing after content is loaded
  autoPrint();
});
</script>
</body>
</html>