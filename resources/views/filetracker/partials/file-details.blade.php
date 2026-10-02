<div class="bg-white rounded-lg shadow-sm border file-details">
  <div class="px-6 py-4 border-b">
    <div class="flex justify-between items-center">
      <h2 class="text-lg font-semibold">File Details</h2>
      @if($selectedFile)
        @php
          $priorityBadge = match($selectedFile->status) {
            'overdue' => ['badge-destructive', 'High Priority'],
            'checked_out' => ['badge-warning', 'Medium Priority'],
            default => ['badge-default', 'Normal Priority']
          };
        @endphp
        <span class="badge {{ $priorityBadge[0] }}">{{ $priorityBadge[1] }}</span>
      @else
        <span class="badge badge-default">Normal Priority</span>
      @endif
    </div>
    <p class="text-sm text-gray-500">
      @if($selectedFile)
        TRK-{{ str_pad($selectedFile->id, 6, '0', STR_PAD_LEFT) }}
      @else
        No file selected
      @endif
    </p>
  </div>
  
  @if($selectedFile)
    <div class="p-6 space-y-4">
      <div class="flex justify-center">
        <div class="h-16 w-16 rounded-full bg-gray-100 flex items-center justify-center">
          @if($selectedFile->is_overdue)
            <svg class="h-10 w-10 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
            </svg>
          @else
            <svg class="h-10 w-10 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
          @endif
        </div>
      </div>
      
      <div class="pt-2">
        <h3 class="text-lg font-semibold text-center">{{ $selectedFile->fileIndexing->file_title ?? 'File Title Not Available' }}</h3>
        <div class="flex justify-center gap-2 mt-1">
          @php
            $statusBadge = match($selectedFile->status) {
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
              default => ['badge-secondary', ucfirst(str_replace('_', ' ', $selectedFile->status))]
            };
          @endphp
          <span class="badge {{ $statusBadge[0] }}">{{ $statusBadge[1] }}</span>
        </div>
      </div>
      
      <div class="space-y-3 pt-2">
        <!-- File Numbers -->
        <div class="flex items-start gap-2">
          <svg class="h-4 w-4 mt-0.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
          </svg>
          <div class="w-full">
            <p class="text-sm font-medium">File Numbers</p>
            <div class="space-y-1 mt-1">
              <div class="flex items-center justify-between">
                <p class="text-xs text-gray-500">File Number:</p>
                <p class="text-xs font-medium">{{ $selectedFile->fileIndexing->file_number ?? 'N/A' }}</p>
              </div>
              @if($selectedFile->fileIndexing && $selectedFile->fileIndexing->old_file_number)
                <div class="flex items-center justify-between">
                  <p class="text-xs text-gray-500">Old File Number:</p>
                  <p class="text-xs font-medium">{{ $selectedFile->fileIndexing->old_file_number }}</p>
                </div>
              @endif
              @if($selectedFile->fileIndexing && $selectedFile->fileIndexing->survey_plan_number)
                <div class="flex items-center justify-between">
                  <p class="text-xs text-gray-500">Survey Plan:</p>
                  <p class="text-xs font-medium">{{ $selectedFile->fileIndexing->survey_plan_number }}</p>
                </div>
              @endif
            </div>
          </div>
        </div>
        
        <!-- RFID Tag -->
        <div class="flex items-start gap-2">
          <svg class="h-4 w-4 mt-0.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"></path>
          </svg>
          <div>
            <p class="text-sm font-medium">RFID Tag</p>
            @if($selectedFile->rfid_tag)
              <p class="text-sm">{{ $selectedFile->rfid_tag }}</p>
              <p class="text-xs text-gray-500">Last updated: {{ $selectedFile->updated_at->format('Y-m-d H:i A') }}</p>
            @else
              <p class="text-sm text-gray-400">Not assigned</p>
              <p class="text-xs text-gray-500">No RFID tag assigned to this file</p>
            @endif
          </div>
        </div>
        
        <!-- QR Code -->
        <div class="flex items-start gap-2">
          <svg class="h-4 w-4 mt-0.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
          </svg>
          <div>
            <p class="text-sm font-medium">QR Code</p>
            <div class="mt-1 flex justify-center">
              @php
                $qrData = 'TRK-' . str_pad($selectedFile->id, 6, '0', STR_PAD_LEFT) . '|' . 
                         ($selectedFile->fileIndexing->file_number ?? 'N/A') . '|' . 
                         ($selectedFile->current_location ?? 'Not Set') . '|' . 
                         ($selectedFile->rfid_tag ?? 'No RFID');
                $qrApiUrl = qr_data_uri($qrData, 96);
              @endphp
              <img src="{{ $qrApiUrl }}" 
                   alt="QR Code for {{ $selectedFile->fileIndexing->file_number ?? 'N/A' }}" 
                   class="h-24 w-24 border border-gray-300 rounded"
                   onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
              <div class="h-24 w-24 bg-gray-200 flex items-center justify-center border border-gray-300 rounded" style="display:none;">
                <span class="text-xs text-gray-500">QR Code</span>
              </div>
            </div>
            <p class="text-xs text-center text-gray-500 mt-1">Contains tracking information</p>
          </div>
        </div>
        
        <!-- Current Location -->
        <div class="flex items-start gap-2">
          <svg class="h-4 w-4 mt-0.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
          </svg>
          <div>
            <p class="text-sm font-medium">Current Location</p>
            <p class="text-sm">{{ $selectedFile->current_location ?? 'Not Set' }}</p>
          </div>
        </div>
        
        <!-- Current Handler -->
        <div class="flex items-start gap-2">
          <svg class="h-4 w-4 mt-0.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
          </svg>
          <div>
            <p class="text-sm font-medium">Current Handler</p>
            <p class="text-sm">{{ $selectedFile->current_handler ?? 'Not Assigned' }}</p>
          </div>
        </div>
        
        <!-- Date Received -->
        <div class="flex items-start gap-2">
          <svg class="h-4 w-4 mt-0.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
          </svg>
          <div>
            <p class="text-sm font-medium">Date Received</p>
            <p class="text-sm">{{ $selectedFile->date_received ? $selectedFile->date_received->format('Y-m-d') : 'Not Set' }}</p>
          </div>
        </div>
        
        <!-- Due Date -->
        <div class="flex items-start gap-2">
          <svg class="h-4 w-4 mt-0.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
          </svg>
          <div>
            <p class="text-sm font-medium">Due Date</p>
            @if($selectedFile->due_date)
              <p class="text-sm">{{ $selectedFile->due_date->format('Y-m-d') }}</p>
              @if($selectedFile->is_overdue)
                <p class="text-xs text-red-600">{{ abs($selectedFile->days_until_due) }} days overdue</p>
              @elseif($selectedFile->days_until_due <= 3 && $selectedFile->days_until_due > 0)
                <p class="text-xs text-yellow-600">{{ $selectedFile->days_until_due }} days remaining</p>
              @endif
            @else
              <p class="text-sm text-gray-400">Not Set</p>
            @endif
          </div>
        </div>
      </div>
      
      <!-- Movement History -->
      <div class="pt-2">
        <h4 class="text-sm font-medium mb-2">Movement History</h4>
        <div class="space-y-3 max-h-[200px] overflow-y-auto pr-2">
          @if($selectedFile->movement_history && count($selectedFile->movement_history) > 0)
            @foreach(array_slice($selectedFile->movement_history, 0, 5) as $index => $movement)
              <div class="relative pl-5 pb-3">
                @if(!$loop->last)
                  <div class="absolute top-0 left-[9px] h-full w-0.5 bg-gray-200"></div>
                @endif
                <div class="absolute top-0 left-0 h-5 w-5 rounded-full bg-blue-600 flex items-center justify-center">
                  <div class="h-2 w-2 rounded-full bg-white"></div>
                </div>
                <div class="ml-2">
                  <div class="flex items-center text-xs text-gray-500">
                    <svg class="h-3 w-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                    {{ \Carbon\Carbon::parse($movement['timestamp'])->format('Y-m-d H:i A') }}
                  </div>
                  <p class="text-sm font-medium mt-1">{{ ucfirst(str_replace('_', ' ', $movement['action'] ?? 'Unknown action')) }}</p>
                  @if(isset($movement['from_location']) || isset($movement['to_location']))
                    <div class="flex items-center text-xs mt-1">
                      <svg class="h-3 w-3 mr-1 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                      </svg>
                      @if(isset($movement['from_location']))
                        {{ $movement['from_location'] }} →
                      @endif
                      {{ $movement['to_location'] ?? $movement['initial_location'] ?? 'Unknown location' }}
                    </div>
                  @endif
                  @if(isset($movement['user_name']))
                    <div class="flex items-center text-xs mt-1">
                      <svg class="h-3 w-3 mr-1 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                      </svg>
                      {{ $movement['user_name'] }}
                    </div>
                  @endif
                  @if(isset($movement['reason']))
                    <p class="text-xs text-gray-500 mt-1">{{ $movement['reason'] }}</p>
                  @endif
                  @if(isset($movement['rfid_tag']))
                    <div class="flex items-center text-xs mt-1">
                      <svg class="h-3 w-3 mr-1 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H9a2 2 0 00-2 2v14a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                      </svg>
                      <span class="text-blue-600">RFID: {{ $movement['rfid_tag'] }}</span>
                    </div>
                  @endif
                </div>
              </div>
            @endforeach
          @else
            <div class="text-center py-4">
              <p class="text-sm text-gray-500">No movement history available</p>
            </div>
          @endif
        </div>
      </div>
    </div>
  @else
    <div class="p-6 space-y-4">
      <div class="flex justify-center">
        <div class="h-16 w-16 rounded-full bg-gray-100 flex items-center justify-center">
          <svg class="h-10 w-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
          </svg>
        </div>
      </div>
      
      <div class="pt-2 text-center">
        <h3 class="text-lg font-semibold text-gray-400">No File Selected</h3>
        <p class="text-sm text-gray-500 mt-2">Click on a file from the table to view its details</p>
      </div>
    </div>
  @endif
  
  <!-- Card Footer -->
  <div class="px-6 py-4 border-t flex justify-between">
    <button class="border rounded-md px-3 py-1 text-sm flex items-center">
      <svg class="h-3.5 w-3.5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path>
      </svg>
      Move File
    </button>
    
    <button class="border rounded-md px-3 py-1 text-sm flex items-center">
      <svg class="h-3.5 w-3.5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
      </svg>
      Print
    </button>
  </div>
  
  <div class="px-6 pb-4">
    <button class="print-tracking-btn w-full bg-blue-600 text-white px-4 py-2 rounded-md text-sm flex items-center justify-center" 
        data-print-url="{{ route('filetracker.print') }}"
        onclick="printSelectedFile()">
      <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
      </svg>
      Print Tracking Sheet
    </button>
  </div>
</div>
{{-- @include('filetracker.print') --}}