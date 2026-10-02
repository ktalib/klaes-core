@extends('layouts.app')

@section('page-title')
    {{ $PageTitle ?? __('Instrument Registration (New Registration)') }}
@endsection

 

@section('content')
<meta name="csrf-token" content="{{ csrf_token() }}">
<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
<script src="https://cdn.jsdelivr.net/npm/lucide@latest"></script>

<!-- Inline script to make sure critical functions are defined early -->
<script>
    // Base URL for instrument registration AJAX endpoints
    window.baseUrl = "{{ url('') }}";
    
    // Define critical functions in the global scope first
    function openBatchRegisterModal() {
        console.log("Opening batch registration modal from inline script");
        
        // Check if there are multiple instruments selected
        const checkedBoxes = document.querySelectorAll('.main-table-checkbox:checked:not([disabled])');
        const checkedCount = checkedBoxes.length;
        
        if (checkedCount >= 2) {
            // Multiple instruments selected - open quick batch modal
            console.log("Opening quick batch modal for", checkedCount, "selected instruments");
            
            // Get the selected instrument data
            const selectedInstruments = Array.from(checkedBoxes).map(checkbox => {
                const id = checkbox.getAttribute('data-id');
                const status = checkbox.getAttribute('data-status');
                
                // Find the instrument data from serverCofoData
                const instrumentData = serverCofoData.find(item => String(item.id) === String(id));
                
                if (instrumentData) {
                    return {
                        id: instrumentData.id,
                        fileNo: instrumentData.fileno,
                        grantor: instrumentData.Grantor || '',
                        grantee: instrumentData.Grantee || '',
                        status: status,
                        instrumentType: instrumentData.instrument_type || '',
                        lga: instrumentData.lga || '',
                        district: instrumentData.district || '',
                        plotNumber: instrumentData.plotNumber || '',
                        plotSize: instrumentData.size || '',
                        plotDescription: instrumentData.propertyDescription || '',
                        duration: instrumentData.duration || instrumentData.leasePeriod || '',
                        deeds_date: instrumentData.deeds_date || instrumentData.instrumentDate || '',
                        deeds_time: instrumentData.deeds_time || '',
                        rootRegistrationNumber: instrumentData.rootRegistrationNumber || instrumentData.Deeds_Serial_No || '',
                        solicitorName: instrumentData.solicitorName || '',
                        solicitorAddress: instrumentData.solicitorAddress || '',
                        landUseType: instrumentData.landUseType || instrumentData.land_use || ''
                    };
                }
                return null;
            }).filter(item => item !== null);
            
            // Open quick batch modal with selected instruments
            if (typeof window.openQuickBatchModal === 'function') {
                window.openQuickBatchModal(selectedInstruments);
            } else {
                console.error("Quick batch modal function not available");
            }
        } else {
            // No selection or single selection - open normal batch modal
            console.log("Opening normal batch modal");
            if (typeof window.openBatchRegisterModalImplementation === 'function') {
                window.openBatchRegisterModalImplementation();
            } else {
                // Fallback implementation if main JS hasn't loaded yet
                document.getElementById('batchRegisterModal').style.display = 'block';
                // We'll reload the page after a slight delay to ensure JS is properly loaded
                setTimeout(() => {
                    location.reload();
                }, 500);
            }
        }
    }
</script>
@include('instrument_registration.partials.css')

<div class="flex-1 overflow-auto">
    <!-- Header -->
    @include($headerPartial ?? 'admin.header')
    
    <!-- Main Content -->
    <div class="container mx-auto py-6 space-y-6 px-4">
        <!-- Page Header -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <h1 class="text-2xl font-bold">Instrument Registration</h1>
            <div>
                <button id="batchRegisterBtn" onclick="openBatchRegisterModal()" 
                        class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg flex items-center gap-2">
                    <i class="fas fa-layer-group"></i> 
                    <span id="batchBtnText">Registration</span>
                </button>
            </div>
        </div>
    
        <!-- Stats Cards -->
        @include('instrument_registration.partials.statistic_card')
    
        <!-- Main Content Table -->
        <div class="table-container">
            <!-- Table tabs & controls -->
            <div class="table-header px-6 py-4 flex justify-between items-center flex-wrap gap-4">
                <div class="flex items-center gap-4">
                    <h2 class="text-lg font-semibold text-gray-900">Instrument Registry</h2>
                    <div class="flex items-center gap-2 text-sm text-gray-600">
                        <i class="fas fa-database text-blue-500"></i>
                        <span id="totalRecordsCount">{{ $totalCount ?? 0 }} Total Records</span>
                    </div>
                </div>
                 
                <!-- Search and Pagination Controls -->
                <div class="flex items-center gap-4">
                    <!-- Records per page selector -->
                    <div class="flex items-center gap-2">
                        <label for="recordsPerPage" class="text-sm text-gray-600">Show:</label>
                        <select id="recordsPerPage" class="border border-gray-300 rounded px-2 py-1 text-sm">
                            <option value="10">10</option>
                            <option value="25" selected>25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                    </div>
                    
                    <!-- Search -->
                    <div class="relative">
                        <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        <input id="searchInput" type="search" placeholder="Search by File No..." 
                               class="search-input pl-10 pr-4 py-2.5 text-sm w-80 rounded-lg">
                    </div>
                </div>
            </div>
        
            <!-- Table with Fixed Header -->
            <div class="table-wrapper" style="max-height: 600px; overflow-y: auto;">
              <table class="min-w-full enhanced-table" id="instrumentTable">
              <thead class="bg-gray-50">
                <tr>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  <input type="checkbox" class="rounded" id="selectAll" onchange="toggleSelectAll(this)">
                </th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer" onclick="sortTable(1)">
                  Reg Particulars
                  <span class="inline-block align-middle" id="sortIcon-1">▲</span>
                </th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer" onclick="sortTable(12)">
                  Captured Date
                  <span class="inline-block align-middle" id="sortIcon-12"></span>
                </th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer" onclick="sortTable(13)">
                  REG DATE
                  <span class="inline-block align-middle" id="sortIcon-13"></span>
                </th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer" onclick="sortTable(2)">
                  FileNo
                  <span class="inline-block align-middle" id="sortIcon-2"></span>
                </th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer" onclick="sortTable(3)">
                  Parent FileNo
                  <span class="inline-block align-middle" id="sortIcon-3"></span>
                </th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer" onclick="sortTable(4)">
                  Status
                  <span class="inline-block align-middle" id="sortIcon-4"></span>
                </th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer" onclick="sortTable(5)">
                  Instrument Type
                  <span class="inline-block align-middle" id="sortIcon-5"></span>
                </th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer" onclick="sortTable(6)">
                  Grantor
                  <span class="inline-block align-middle" id="sortIcon-6"></span>
                </th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer" onclick="sortTable(7)">
                  Grantee
                  <span class="inline-block align-middle" id="sortIcon-7"></span>
                </th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer" onclick="sortTable(8)">
                  LGA
                  <span class="inline-block align-middle" id="sortIcon-8"></span>
                </th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer" onclick="sortTable(9)">
                  District
                  <span class="inline-block align-middle" id="sortIcon-9"></span>
                </th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer" onclick="sortTable(10)">
                  Plot Number
                  <span class="inline-block align-middle" id="sortIcon-10"></span>
                </th> 
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer" onclick="sortTable(11)">
                  Plot Size
                  <span class="inline-block align-middle" id="sortIcon-11"></span>
                </th>
               
                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Action
                </th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200" id="cofoTableBody">
                @forelse($approvedApplications as $app)
                <tr class="cofo-row" data-status="{{ $app->status }}" data-id="{{ $app->id }}">
                <td class="px-6 py-4 whitespace-nowrap">
                  @php
                    $isDisabled = $app->status === 'registered';
                    // For ST CofO, check if corresponding ST Assignment is registered
                    if ($app->status === 'pending' && $app->instrument_type === 'Sectional Titling CofO') {
                        // This should be dynamically determined based on whether ST Assignment is registered
                        // For now, we'll let JavaScript handle this logic
                        $isDisabled = false; // Let JavaScript determine the actual state
                    }
                  @endphp
                  <input type="checkbox" class="rounded main-table-checkbox" 
                         data-id="{{ $app->id }}" 
                         data-status="{{ $app->status }}"
                         data-instrument-type="{{ $app->instrument_type }}"
                         data-fileno="{{ $app->fileno }}"
                         {{ $isDisabled ? 'disabled' : '' }}
                         onchange="handleMainTableCheckboxChange()">
                </td>
                <!-- 1. Reg Particulars - FIXED: Only show for registered instruments -->
                <td class="px-6 py-4 whitespace-nowrap text-sm">
                  @if($app->status === 'registered')
                    @if($app->instrument_type === 'ST Fragmentation')
                      <span class="px-2 py-1 bg-purple-100 text-purple-800 rounded-md font-mono text-xs">0/0/0</span>
                    @else
                      <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded-md font-mono text-xs">{{ $app->Deeds_Serial_No ?? 'N/A' }}</span>
                    @endif
                  @else
                    <span class="text-gray-400 text-xs">N/A</span>
                  @endif
                </td>

                    <!-- 12. Captured Date -->
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                  @if($app->captured_date ?? null)
                    <div class="flex items-center">
                      <i class="fas fa-calendar-plus text-blue-400 mr-2"></i>
                      {{ date('M d, Y', strtotime($app->captured_date)) }}
                    </div>
                  @else
                    <span class="text-gray-400">N/A</span>
                  @endif
                </td>
                <!-- 13. Reg Date -->
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                  @if($app->reg_date ?? null)
                    <div class="flex items-center">
                      <i class="fas fa-calendar-check text-green-400 mr-2"></i>
                      {{ date('M d, Y', strtotime($app->reg_date)) }}
                    </div>
                  @else
                    <span class="text-gray-400">N/A</span>
                  @endif
                </td>
                <!-- 2. FileNo -->
                <td class="px-6 py-4 whitespace-nowrap text-sm">
                  <span class="file-number">{{ $app->fileno ?? 'N/A' }}</span>
                </td>
                <!-- 3. Parent FileNo -->
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                  @if($app->instrument_type === 'ST Fragmentation')
                    <span class="file-number">{{ $app->parent_fileNo ?? 'N/A' }}</span>
                  @elseif(in_array($app->instrument_type, ['ST Assignment (Transfer of Title)', 'Sectional Titling CofO']))
                    <span class="file-number">{{ $app->parent_fileNo ?? $app->fileno ?? 'N/A' }}</span>
                  @else
                    <span class="text-gray-400">N/A</span>
                  @endif
                </td>
                <!-- 4. Status -->
                <td class="px-6 py-4 whitespace-nowrap text-sm">
                  <span class="status-badge badge-{{ $app->status }}">{{ ucfirst($app->status) }}</span>
                </td>
                <!-- 5. Instrument Type -->
                <td class="px-6 py-4 whitespace-nowrap text-sm">
                  @if($app->instrument_type === 'ST Fragmentation')
                    <span class="badge badge-st-fragmentation">
                      <i class="fas fa-puzzle-piece mr-1"></i>
                      ST Fragmentation
                    </span>
                  @elseif($app->instrument_type === 'ST Assignment (Transfer of Title)')
                    <span class="badge badge-st-assignment">
                      <i class="fas fa-exchange-alt mr-1"></i>
                      ST Assignment (Transfer of Title )
                    </span>
                  @elseif($app->instrument_type === 'Sectional Titling CofO')
                    <span class="badge badge-sectional-titling">
                      <i class="fas fa-building mr-1"></i>
                      ST CofO
                    </span>
                  @else
                    <span class="badge badge-other-instrument">
                      <i class="fas fa-file-alt mr-1"></i>
                      {{ $app->instrument_type ?? 'Other' }}
                    </span>
                  @endif
                </td>
                <!-- 6. Grantor -->
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-medium">
                  @php
                    $grantor = $app->Grantor ?? 'N/A';
                    // Try to decode JSON if it's a string and looks like an array
                    if (is_string($grantor) && str_starts_with(trim($grantor), '[')) {
                      $grantorArr = json_decode($grantor, true);
                      if (json_last_error() !== JSON_ERROR_NONE) {
                        $grantorArr = [$grantor];
                      }
                    } elseif (is_array($grantor)) {
                      $grantorArr = $grantor;
                    } else {
                      $grantorArr = [$grantor];
                    }
                  @endphp
                  @if(is_array($grantorArr) && count($grantorArr) > 1)
                    <span 
                      class="cursor-pointer underline decoration-dotted"
                      tabindex="0"
                      onclick="Swal.fire({title: 'Grantors', html: `{!! implode('<br>', array_map('e', $grantorArr)) !!}` , icon: 'info'})"
                      onkeydown="if(event.key==='Enter'){Swal.fire({title: 'Grantors', html: `{!! implode('<br>', array_map('e', $grantorArr)) !!}` , icon: 'info'})}"
                    >
                      {{ $grantorArr[0] ?? 'N/A' }} +{{ count($grantorArr)-1 }} more
                    </span>
                  @else
                    {{ $grantorArr[0] ?? 'N/A' }}
                  @endif
                </td>
                <!-- 7. Grantee -->
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-medium">
                  @php
                    $grantee = $app->Grantee ?? 'N/A';
                    if (is_string($grantee) && str_starts_with(trim($grantee), '[')) {
                      $granteeArr = json_decode($grantee, true);
                      if (json_last_error() !== JSON_ERROR_NONE) {
                        $granteeArr = [$grantee];
                      }
                    } elseif (is_array($grantee)) {
                      $granteeArr = $grantee;
                    } else {
                      $granteeArr = [$grantee];
                    }
                  @endphp
                  @if(is_array($granteeArr) && count($granteeArr) > 1)
                    <span 
                      class="cursor-pointer underline decoration-dotted"
                      tabindex="0"
                      onclick="Swal.fire({title: 'Grantees', html: `{!! implode('<br>', array_map('e', $granteeArr)) !!}` , icon: 'info'})"
                      onkeydown="if(event.key==='Enter'){Swal.fire({title: 'Grantees', html: `{!! implode('<br>', array_map('e', $granteeArr)) !!}` , icon: 'info'})}"
                    >
                      {{ $granteeArr[0] ?? 'N/A' }} +{{ count($granteeArr)-1 }} more
                    </span>
                  @else
                    {{ $granteeArr[0] ?? 'N/A' }}
                  @endif
                </td>
                <!-- 8. LGA -->
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $app->lga ?? 'N/A' }}</td>
                <!-- 9. District -->
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $app->district ?? 'N/A' }}</td>
                <!-- 10. Plot Number -->
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $app->plotNumber ?? 'N/A' }}</td>
                <!-- 11. Plot Size -->
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $app->size ?? 'N/A' }}</td>
            
                <!-- 14. Action -->
                <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                  <div class="dropdown-wrapper">
                    <button 
                      class="action-button text-gray-500 hover:text-gray-700 p-2 rounded-md transition-colors duration-200"
                      onclick="toggleDropdown(this, '{{ $app->id }}')" 
                      type="button">
                      <i data-lucide="more-vertical" class="w-4 h-4"></i>
                    </button>
                  </div>
                </td>
                </tr>
                @empty
                <tr>
                <td colspan="15" class="px-6 py-10 text-center text-gray-500">
                  No instrument registrations available.
                </td>
                </tr>
                @endforelse
              </tbody>
              </table>
            </div>

            <!-- Pagination Controls -->
            <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex items-center justify-between">
                <div class="flex items-center text-sm text-gray-700">
                    <span>Showing</span>
                    <span class="font-medium mx-1" id="showingStart">1</span>
                    <span>to</span>
                    <span class="font-medium mx-1" id="showingEnd">25</span>
                    <span>of</span>
                    <span class="font-medium mx-1" id="showingTotal">{{ $totalCount ?? 0 }}</span>
                    <span>results</span>
                </div>
                
                <div class="flex items-center space-x-2">
                    <button id="prevPage" class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed">
                        <i class="fas fa-chevron-left mr-1"></i>
                        Previous
                    </button>
                    
                    <div id="pageNumbers" class="flex items-center space-x-1">
                        <!-- Page numbers will be dynamically generated -->
                    </div>
                    
                    <button id="nextPage" class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed">
                        Next
                        <i class="fas fa-chevron-right ml-1"></i>
                    </button>
                </div>
            </div>

            <script>
            let sortDirections = {1: true}; // Default sort direction for column 1 is ascending
            function sortTable(colIndex) {
              const table = document.getElementById('instrumentTable');
              const tbody = table.tBodies[0];
              const rows = Array.from(tbody.querySelectorAll('tr')).filter(row => !row.querySelector('td[colspan]'));
              const isNumeric = [11].includes(colIndex); // Plot Size column (index 11) is numeric
              const isDate = [12, 13].includes(colIndex); // Date columns (index 12 and 13) are dates
              sortDirections[colIndex] = !sortDirections[colIndex];
              rows.sort((a, b) => {
              let aText = a.children[colIndex]?.innerText.trim() || '';
              let bText = b.children[colIndex]?.innerText.trim() || '';
              if (isNumeric) {
                aText = parseFloat(aText.replace(/[^0-9.]/g, '')) || 0;
                bText = parseFloat(bText.replace(/[^0-9.]/g, '')) || 0;
              } else if (isDate) {
                // Handle N/A values for dates
                if (aText === 'N/A') aText = new Date(0);
                else aText = new Date(aText);
                if (bText === 'N/A') bText = new Date(0);
                else bText = new Date(bText);
              }
              if (aText < bText) return sortDirections[colIndex] ? -1 : 1;
              if (aText > bText) return sortDirections[colIndex] ? 1 : -1;
              return 0;
              });
              // Remove all rows and re-append sorted
              rows.forEach(row => tbody.appendChild(row));
              // Update sort icons
              for (let i = 1; i <= 13; i++) {
              const icon = document.getElementById('sortIcon-' + i);
              if (icon) icon.innerHTML = '';
              }
              const icon = document.getElementById('sortIcon-' + colIndex);
              if (icon) icon.innerHTML = sortDirections[colIndex] ? '▲' : '▼';
            }
            </script>
        </div>
    </div>
    
    <!-- Dropdown Menu Container -->
    <div id="dropdown-menu" class="dropdown-menu hidden">
        <!-- Dynamic content will be populated here -->
    </div>

    <!-- Include Modals -->
    @include('instrument_registration.partials.singleregistermodal')
    @include('instrument_registration.partials.batchregistermodal')
    @include('instrument_registration.partials.quickbatchmodal')
    
    <!-- Page Footer -->
    @include($footerPartial ?? 'admin.footer')
</div>

<!-- Debug section -->
<div id="debugInfo" class="fixed bottom-0 right-0 bg-black bg-opacity-75 text-white p-4 rounded-tl-lg max-w-lg max-h-48 overflow-auto hidden">
  <h3 class="font-bold">Debug Information</h3>
  <div id="debugContent" class="text-xs font-mono"></div>
  <button onclick="document.getElementById('debugInfo').classList.add('hidden')" class="text-xs bg-red-500 text-white px-2 py-1 rounded mt-2">Close</button>
</div>

<script>
  // Helper function to format multiple owner names
  function formatMultipleOwners(ownerString) {
    if (!ownerString) return 'N/A';
    
    // Check if it's a JSON array string
    if (typeof ownerString === 'string' && ownerString.trim().startsWith('[')) {
      try {
        const owners = JSON.parse(ownerString);
        if (Array.isArray(owners) && owners.length > 0) {
          // Return first name with count if multiple
          return owners.length > 1 
            ? `${owners[0]} +${owners.length - 1} more`
            : owners[0];
        }
      } catch (e) {
        // If JSON parsing fails, return the original string
        return ownerString;
      }
    }
    
    return ownerString;
  }

  // Pass PHP data to JavaScript and format multiple owners
  const serverCofoData = @json($approvedApplications).map(item => {
    // Format Grantor and Grantee if they contain JSON arrays
    if (item.Grantor) {
      item.Grantor = formatMultipleOwners(item.Grantor);
    }
    if (item.Grantee) {
      item.Grantee = formatMultipleOwners(item.Grantee);
    }
    return item;
  });
  console.log("Server data loaded:", serverCofoData.length, "records");
  
  // Add error tracking
  window.addEventListener('error', function(e) {
    // Log to console
    console.error("JavaScript error:", e.message, "at", e.filename, "line", e.lineno);
    
    // Add to debug info if available
    const debugContent = document.getElementById('debugContent');
    if (debugContent) {
      const errorMsg = `${e.message} at ${e.filename}:${e.lineno}`;
      debugContent.innerHTML += `<div class="text-red-400">${errorMsg}</div>`;
      document.getElementById('debugInfo').classList.remove('hidden');
    }
  });
  
  // Show debug panel with Ctrl+D
  document.addEventListener('keydown', function(e) {
    if (e.ctrlKey && e.key === 'd') {
      e.preventDefault();
      const debugInfo = document.getElementById('debugInfo');
      debugInfo.classList.toggle('hidden');
      
      // Add some debug info
      const debugContent = document.getElementById('debugContent');
      if (!debugContent.hasChildNodes()) {
        try {
          debugContent.innerHTML = `
            <div>Records from server: ${serverCofoData.length}</div>
            <div>Processed records: ${cofoData ? cofoData.length : '(cofoData not defined)'}</div>
            <div>populateAvailablePropertiesTable defined: ${typeof window.populateAvailablePropertiesTable === 'function' ? 'Yes' : 'No'}</div>
            <div>First record sample: ${JSON.stringify(serverCofoData[0], null, 2).substring(0, 300)}...</div>
          `;
        } catch (err) {
          debugContent.innerHTML = `<div class="text-red-400">Error generating debug info: ${err.message}</div>`;
        }
      }
    }
  });
</script>

<!-- Define base URL for instrument registration routes -->
<script>
    window.instrumentRegistrationBase = "{{ url('') }}";
</script>

<!-- Include SweetAlert -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- Include the external JavaScript file -->
<script src="{{ asset('js/instrument_registration.js') }}?v={{ time() }}"></script>

<!-- Include the updated JavaScript file for single registration modal -->
<script src="{{ asset('js/instrument_registration_updated.js') }}?v={{ time() }}"></script>

<!-- Include the FINAL batch registration handler -->
<script src="{{ asset('js/batch_registration_handler_final.js') }}?v={{ time() }}"></script>

<!-- Include the batch fix to handle empty selectedBatchProperties -->

<!-- Include the quick batch handler -->
<script src="{{ asset('js/quick_batch_handler.js') }}?v={{ time() }}"></script>


@if(session('success'))
<script>
    Swal.fire({
        title: 'Success!',
        text: "{{ session('success') }}",
        icon: 'success',
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000
    });
</script>
@endif

@if(session('error'))
<script>
    Swal.fire({
        title: 'Error!',
        text: "{{ session('error') }}",
        icon: 'error',
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000
    });
</script>
@endif

<!-- Floating UI CDN -->
<script src="https://cdn.jsdelivr.net/npm/@floating-ui/dom@1.5.3/dist/floating-ui.dom.min.js"></script>

<script>
let currentDropdown = null;
let currentButton = null;
let currentAppData = null;

// Store app data for easy access
const appData = @json($approvedApplications->keyBy('id'));

async function toggleDropdown(button, appId) {
    const dropdown = document.getElementById('dropdown-menu');
    
    // If clicking the same button, close dropdown
    if (currentButton === button && !dropdown.classList.contains('hidden')) {
        closeDropdown();
        return;
    }
    
    // Close any existing dropdown
    closeDropdown();
    
    // Set current references
    currentButton = button;
    currentDropdown = dropdown;
    currentAppData = appData[appId];
    
    // Populate dropdown content
    populateDropdownContent(currentAppData);
    
    // Position dropdown using Floating UI BEFORE showing it
    try {
        const {x, y} = await FloatingUIDOM.computePosition(button, dropdown, {
            placement: 'bottom-end',
            middleware: [
                FloatingUIDOM.offset(4),
                FloatingUIDOM.flip(),
                FloatingUIDOM.shift({ padding: 8 })
            ],
        });
        
        // Set position first
        Object.assign(dropdown.style, {
            left: `${x}px`,
            top: `${y}px`,
        });
        
        // Then show dropdown
        dropdown.classList.remove('hidden');
        
    } catch (error) {
        console.error('Error positioning dropdown:', error);
        // Fallback positioning if Floating UI fails
        const rect = button.getBoundingClientRect();
        dropdown.style.left = `${rect.right - 160}px`;
        dropdown.style.top = `${rect.bottom + 4}px`;
        dropdown.classList.remove('hidden');
    }
}

function populateDropdownContent(app) {
    const dropdown = document.getElementById('dropdown-menu');
    
    const editClass = app.status === 'pending' ? 'text-gray-700 hover:bg-gray-100' : 'text-gray-400 cursor-not-allowed';
    const editIcon = app.status === 'pending' ? 'text-blue-500' : 'text-gray-300';
    const editHref = app.status === 'pending' ? `{{ url('instrument_registration') }}/${app.id}/edit` : '#';
    const editClick = app.status !== 'pending' ? 'onclick="return false;"' : '';
    
    // Check if ST Assignment is registered for ST CofO instruments
    const canRegister = checkCanRegisterInstrument(app);
    const registerClass = canRegister ? 'text-gray-700 hover:bg-gray-100' : 'text-gray-400 cursor-not-allowed';
    const registerIcon = canRegister ? 'text-green-500' : 'text-gray-300';
    const registerClick = canRegister ? `onclick="openSingleRegisterModalWithData('${app.id}'); return false;"` : 'onclick="showSTCofoRestrictionMessage(); return false;"';
    
    const deleteClass = app.status === 'pending' ? 'text-red-600 hover:bg-gray-100' : 'text-gray-400 cursor-not-allowed';
    const deleteIcon = app.status === 'pending' ? '' : 'text-gray-300';
    const deleteClick = app.status === 'pending' ? `onclick="deleteInstrument('${app.id}'); return false;"` : 'onclick="return false;"';
    
    // Updated View CoR logic to include ST Fragmentation check
    const viewCorContent = app.status === 'registered' && app.STM_Ref && app.instrument_type !== 'ST Fragmentation'
        ? `<a href="{{ route('coroi.index') }}?url=registered_instruments?STM_Ref=${app.STM_Ref}" class="dropdown-item">
             <i class="fas fa-eye w-4 h-4 text-blue-500"></i>
             <span>View CoR</span>
           </a>`
        : `<a href="#" onclick="return false;" class="dropdown-item text-gray-400 cursor-not-allowed">
             <i class="fas fa-eye w-4 h-4 text-gray-300"></i>
             <span>View CoR</span>
           </a>`;
    
    dropdown.innerHTML = `
        <a href="${editHref}" ${editClick} class="dropdown-item ${editClass}">
            <i class="fas fa-edit w-4 h-4 ${editIcon}"></i>
            <span>Edit Record</span>
        </a>
        <a href="#" ${registerClick} class="dropdown-item ${registerClass}">
            <i class="fas fa-file-signature w-4 h-4 ${registerIcon}"></i>
            <span>Register Instrument</span>
        </a>
        ${viewCorContent}
        <a href="#" ${deleteClick} class="dropdown-item ${deleteClass}">
            <i class="fas fa-trash w-4 h-4 ${deleteIcon}"></i>
            <span>Delete Record</span>
        </a>
    `;
}

function closeDropdown() {
    const dropdown = document.getElementById('dropdown-menu');
    dropdown.classList.add('hidden');
    currentDropdown = null;
    currentButton = null;
    currentAppData = null;
}

function deleteInstrument(id) {
    Swal.fire({
        title: 'Are you sure?',
        text: "You won't be able to revert this!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            // Send DELETE request
            fetch(`{{ url('instrument_registration/delete') }}/${id}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire(
                        'Deleted!',
                        data.message,
                        'success'
                    ).then(() => {
                        // Reload the page to refresh the table
                        location.reload();
                    });
                } else {
                    Swal.fire(
                        'Error!',
                        data.error || 'Failed to delete instrument',
                        'error'
                    );
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire(
                    'Error!',
                    'An error occurred while deleting the instrument',
                    'error'
                );
            });
        }
    });
}

// Function to check if an instrument can be registered
function checkCanRegisterInstrument(app) {
    // For non-pending instruments, they cannot be registered
    if (app.status !== 'pending') {
        return false;
    }
    
    // For ST CofO, check if corresponding ST Assignment is registered
    if (app.instrument_type === 'Sectional Titling CofO') {
        try {
            // Check if there's a registered ST Assignment for the same file number
            const stAssignmentRegistered = serverCofoData.find(item => 
                item.fileno === app.fileno && 
                item.instrument_type === 'ST Assignment (Transfer of Title)' && 
                item.status === 'registered'
            );
            
            return !!stAssignmentRegistered;
        } catch (error) {
            console.error('Error checking ST Assignment registration:', error);
            return false;
        }
    }
    
    // For all other instrument types, allow registration if pending
    return true;
}

// Function to show ST CofO restriction message
function showSTCofoRestrictionMessage() {
    Swal.fire({
        title: 'Registration Restriction',
        html: `
            <div class="text-left">
                <p class="mb-3"><strong>ST CofO (Sectional Titling Certificate of Occupancy)</strong> cannot be registered directly.</p>
                <p class="mb-3">To register an ST CofO, you must first ensure that the corresponding <strong>ST Assignment (Transfer of Title)</strong> has been registered.</p>
                <div class="bg-blue-50 p-3 rounded-lg mt-4">
                    <p class="text-sm text-blue-800"><i class="fas fa-info-circle mr-2"></i><strong>Registration Process:</strong></p>
                    <ol class="text-sm text-blue-700 mt-2 ml-4">
                        <li>1. Register the ST Assignment (Transfer of Title) first</li>
                        <li>2. Once registered, the ST CofO will become available for registration</li>
                    </ol>
                </div>
            </div>
        `,
        icon: 'info',
        confirmButtonText: 'I Understand',
        confirmButtonColor: '#3085d6',
        width: '500px'
    });
}

// Close dropdown when clicking outside
document.addEventListener('click', function(e) {
    if (!e.target.closest('.dropdown-wrapper') && !e.target.closest('#dropdown-menu')) {
        closeDropdown();
    }
});

// Close dropdown on scroll and resize
window.addEventListener('scroll', closeDropdown);
window.addEventListener('resize', closeDropdown);

// Close dropdown on table scroll
document.querySelector('.table-container')?.addEventListener('scroll', closeDropdown);

// Close dropdown on escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeDropdown();
    }
});

// Function to update checkbox states based on ST Assignment registration status
function updateCheckboxStates() {
    console.log('updateCheckboxStates called');
    const checkboxes = document.querySelectorAll('.main-table-checkbox');
    console.log('Found checkboxes:', checkboxes.length);
    
    // Debug: Log all available ST Assignments
    const stAssignments = serverCofoData.filter(item => 
        item.instrument_type === 'ST Assignment (Transfer of Title)' && 
        item.status === 'registered'
    );
    console.log('Available registered ST Assignments:', stAssignments.map(item => ({
        fileno: item.fileno,
        id: item.id,
        status: item.status
    })));
    
    let stCofoCount = 0;
    let enabledCount = 0;
    let stAssignmentCount = 0;
    
    checkboxes.forEach((checkbox, index) => {
        const instrumentType = checkbox.getAttribute('data-instrument-type');
        const status = checkbox.getAttribute('data-status');
        const fileno = checkbox.getAttribute('data-fileno');
        const id = checkbox.getAttribute('data-id');
        
        console.log(`Checkbox ${index}: ID=${id}, Type=${instrumentType}, Status=${status}, FileNo=${fileno}`);
        
        // Handle ST Assignment (Transfer of Title) - should be enabled when pending
        if (status === 'pending' && instrumentType === 'ST Assignment (Transfer of Title)') {
            stAssignmentCount++;
            console.log(`Processing ST Assignment: ID=${id}, FileNo=${fileno}, Status=${status}`);
            
            // ST Assignment should always be enabled when pending (not registered yet)
            checkbox.disabled = false;
            enabledCount++;
            console.log(`✅ Enabled ST Assignment checkbox for ${fileno}`);
        }
        
        // Handle ST CofO (Sectional Titling CofO) - should be enabled only if corresponding ST Assignment is registered
        else if (status === 'pending' && instrumentType === 'Sectional Titling CofO') {
            stCofoCount++;
            console.log(`Processing ST CofO: ID=${id}, FileNo=${fileno}, Status=${status}`);
            
            // Check if there's a registered ST Assignment for the same file number
            const stAssignmentRegistered = serverCofoData.find(item => {
                const match = item.fileno === fileno && 
                             item.instrument_type === 'ST Assignment (Transfer of Title)' && 
                             item.status === 'registered';
                
                if (item.fileno === fileno && item.instrument_type === 'ST Assignment (Transfer of Title)') {
                    console.log(`Found matching fileno ${fileno}: Type=${item.instrument_type}, Status=${item.status}, Match=${match}`);
                }
                
                return match;
            });
            
            console.log(`ST Assignment found for ${fileno}:`, !!stAssignmentRegistered);
            if (stAssignmentRegistered) {
                console.log('ST Assignment details:', {
                    id: stAssignmentRegistered.id,
                    fileno: stAssignmentRegistered.fileno,
                    type: stAssignmentRegistered.instrument_type,
                    status: stAssignmentRegistered.status
                });
            }
            
            // Enable/disable checkbox based on ST Assignment registration status
            const shouldEnable = !!stAssignmentRegistered;
            checkbox.disabled = !shouldEnable;
            
            // Add visual indicator for disabled ST CofO checkboxes
            const row = checkbox.closest('tr');
            if (row) {
                if (!shouldEnable) {
                    row.classList.add('st-cofo-disabled');
                    // Add a tooltip or visual indicator
                    checkbox.title = 'This ST CofO cannot be registered until the corresponding ST Assignment is registered first';
                } else {
                    row.classList.remove('st-cofo-disabled');
                    checkbox.title = '';
                }
            }
            
            if (shouldEnable) {
                enabledCount++;
                console.log(`✅ Enabled ST CofO checkbox for ${fileno}`);
            } else {
                console.log(`❌ Disabled ST CofO checkbox for ${fileno} - no registered ST Assignment found`);
            }
            
            // If checkbox becomes disabled and was checked, uncheck it
            if (checkbox.disabled && checkbox.checked) {
                checkbox.checked = false;
                // Trigger change event to update batch registration state
                handleMainTableCheckboxChange();
            }
        }
        
        // For registered instruments, disable checkboxes (already registered)
        else if (status === 'registered') {
            checkbox.disabled = true;
            console.log(`Disabled registered instrument checkbox for ${fileno}`);
        }
        
        // For other pending instruments (not ST Assignment or ST CofO), enable them
        else if (status === 'pending') {
            checkbox.disabled = false;
            console.log(`✅ Enabled other pending instrument checkbox for ${fileno}`);
        }
    });
    
    console.log(`ST Assignment checkboxes processed: ${stAssignmentCount}`);
    console.log(`ST CofO checkboxes processed: ${stCofoCount}, enabled: ${enabledCount}`);
    
    // Debug: Log all serverCofoData for inspection
    console.log('All serverCofoData:', serverCofoData.map(item => ({
        id: item.id,
        fileno: item.fileno,
        type: item.instrument_type,
        status: item.status
    })));
}

// Enhanced batch registration functionality - FINAL VERSION
function handleMainTableCheckboxChange() {
    const checkedBoxes = document.querySelectorAll('.main-table-checkbox:checked:not([disabled])');
    const checkedCount = checkedBoxes.length;
    const batchBtn = document.getElementById('batchRegisterBtn');
    const batchBtnText = document.getElementById('batchBtnText');
    
    console.log(`${checkedCount} instruments selected`);
    
    // Update button state and text based on selection
    if (checkedCount === 0) {
        // No selection - show default "Registration" button (enabled)
        batchBtnText.textContent = 'Registration';
        // Button stays enabled for normal batch registration
    } else if (checkedCount === 1) {
        // Single selection - show "Registration" button (enabled)
        batchBtnText.textContent = 'Registration';
        // Button stays enabled for normal batch registration
    } else {
        // Multiple selection - show "Batch Registration" button (enabled)
        batchBtnText.textContent = 'Batch Registration';
        
        // Get the selected instrument data
        const selectedInstruments = Array.from(checkedBoxes).map(checkbox => {
            const id = checkbox.getAttribute('data-id');
            const status = checkbox.getAttribute('data-status');
            
            // Find the instrument data from serverCofoData
            const instrumentData = serverCofoData.find(item => String(item.id) === String(id));
            
            if (instrumentData) {
                return {
                    id: instrumentData.id,
                    fileNo: instrumentData.fileno,
                    grantor: instrumentData.Grantor || '',
                    grantee: instrumentData.Grantee || '',
                    status: status,
                    instrumentType: instrumentData.instrument_type || '',
                    lga: instrumentData.lga || '',
                    district: instrumentData.district || '',
                    plotNumber: instrumentData.plotNumber || '',
                    plotSize: instrumentData.size || '',
                    plotDescription: instrumentData.propertyDescription || '',
                    duration: instrumentData.duration || instrumentData.leasePeriod || '',
                    deeds_date: instrumentData.deeds_date || instrumentData.instrumentDate || '',
                    deeds_time: instrumentData.deeds_time || '',
                    rootRegistrationNumber: instrumentData.rootRegistrationNumber || instrumentData.Deeds_Serial_No || '',
                    solicitorName: instrumentData.solicitorName || '',
                    solicitorAddress: instrumentData.solicitorAddress || '',
                    landUseType: instrumentData.landUseType || instrumentData.land_use || ''
                };
            }
            return null;
        }).filter(item => item !== null);
        
        // Store selected instruments for the batch modal
        if (typeof window.setSelectedInstrumentsForBatch === 'function') {
            window.setSelectedInstrumentsForBatch(selectedInstruments);
        }
    }
    
    // Reset batch modal if needed (when checkboxes are unchecked)
    if (typeof window.resetBatchModalIfNeeded === 'function') {
        window.resetBatchModalIfNeeded();
    }
}

// Update the toggleSelectAll function to work with the new checkbox class
function toggleSelectAll(checkbox) {
    const checkboxes = document.querySelectorAll('.main-table-checkbox:not([disabled])');
    checkboxes.forEach(cb => {
        cb.checked = checkbox.checked;
    });
    
    // Trigger the batch registration check
    handleMainTableCheckboxChange();
}
</script>

<!-- Pagination and Search Functionality -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Pagination variables
    let currentPage = 1;
    let recordsPerPage = 25;
    let filteredData = [...serverCofoData];
    let totalRecords = filteredData.length;
    
    // Get DOM elements
    const recordsPerPageSelect = document.getElementById('recordsPerPage');
    const searchInput = document.getElementById('searchInput');
    const prevPageBtn = document.getElementById('prevPage');
    const nextPageBtn = document.getElementById('nextPage');
    const pageNumbers = document.getElementById('pageNumbers');
    const showingStart = document.getElementById('showingStart');
    const showingEnd = document.getElementById('showingEnd');
    const showingTotal = document.getElementById('showingTotal');
    const tableBody = document.getElementById('cofoTableBody');
    
    // Initialize pagination
    function initializePagination() {
        recordsPerPage = parseInt(recordsPerPageSelect.value);
        updateTable();
        updatePaginationControls();
    }
    
    // Filter data based on search
    function filterData() {
        const searchTerm = searchInput.value.toLowerCase().trim();
        
        if (searchTerm === '') {
            filteredData = [...serverCofoData];
        } else {
            filteredData = serverCofoData.filter(item => {
                return (item.fileno && item.fileno.toLowerCase().includes(searchTerm)) ||
                       (item.Grantor && item.Grantor.toLowerCase().includes(searchTerm)) ||
                       (item.Grantee && item.Grantee.toLowerCase().includes(searchTerm)) ||
                       (item.lga && item.lga.toLowerCase().includes(searchTerm)) ||
                       (item.district && item.district.toLowerCase().includes(searchTerm)) ||
                       (item.plotNumber && item.plotNumber.toLowerCase().includes(searchTerm)) ||
                       (item.instrument_type && item.instrument_type.toLowerCase().includes(searchTerm));
            });
        }
        
        totalRecords = filteredData.length;
        currentPage = 1; // Reset to first page when filtering
        updateTable();
        updatePaginationControls();
    }
    
    // Update table with current page data
    function updateTable() {
        const startIndex = (currentPage - 1) * recordsPerPage;
        const endIndex = startIndex + recordsPerPage;
        const pageData = filteredData.slice(startIndex, endIndex);
        
        // Clear existing table body
        tableBody.innerHTML = '';
        
        if (pageData.length === 0) {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="15" class="px-6 py-10 text-center text-gray-500">
                        No instrument registrations found.
                    </td>
                </tr>
            `;
            return;
        }
        
        // Populate table with page data
        pageData.forEach(app => {
            // Use the same logic as the checkCanRegisterInstrument function for checkboxes
            let isDisabled = app.status === 'registered';
            
            // For ST CofO, check if corresponding ST Assignment is registered
            if (app.status === 'pending' && app.instrument_type === 'Sectional Titling CofO') {
                // Check if there's a registered ST Assignment for the same file number
                const stAssignmentRegistered = serverCofoData.find(item => 
                    item.fileno === app.fileno && 
                    item.instrument_type === 'ST Assignment (Transfer of Title)' && 
                    item.status === 'registered'
                );
                
                // Disable checkbox if ST Assignment is not registered
                isDisabled = !stAssignmentRegistered;
            }
            
            // Format dates
            const capturedDate = app.captured_date ? 
                `<div class="flex items-center">
                    <i class="fas fa-calendar-plus text-blue-400 mr-2"></i>
                    ${new Date(app.captured_date).toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' })}
                </div>` : 
                '<span class="text-gray-400">N/A</span>';
                
            const regDate = app.reg_date ? 
                `<div class="flex items-center">
                    <i class="fas fa-calendar-check text-green-400 mr-2"></i>
                    ${new Date(app.reg_date).toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' })}
                </div>` : 
                '<span class="text-gray-400">N/A</span>';
            
            // Format reg particulars
            let regParticulars = '<span class="text-gray-400 text-xs">N/A</span>';
            if (app.status === 'registered') {
                if (app.instrument_type === 'ST Fragmentation') {
                    regParticulars = '<span class="px-2 py-1 bg-purple-100 text-purple-800 rounded-md font-mono text-xs">0/0/0</span>';
                } else {
                    regParticulars = `<span class="px-2 py-1 bg-blue-100 text-blue-800 rounded-md font-mono text-xs">${app.Deeds_Serial_No || 'N/A'}</span>`;
                }
            }
            
            // Format parent file number
            let parentFileNo = '<span class="text-gray-400">N/A</span>';
            if (app.instrument_type === 'ST Fragmentation') {
                parentFileNo = `<span class="file-number">${app.parent_fileNo || 'N/A'}</span>`;
            } else if (['ST Assignment (Transfer of Title)', 'Sectional Titling CofO'].includes(app.instrument_type)) {
                parentFileNo = `<span class="file-number">${app.parent_fileNo || app.fileno || 'N/A'}</span>`;
            }
            
            // Format instrument type badge
            let instrumentTypeBadge = '';
            if (app.instrument_type === 'ST Fragmentation') {
                instrumentTypeBadge = '<span class="badge badge-st-fragmentation"><i class="fas fa-puzzle-piece mr-1"></i>ST Fragmentation</span>';
            } else if (app.instrument_type === 'ST Assignment (Transfer of Title)') {
                instrumentTypeBadge = '<span class="badge badge-st-assignment"><i class="fas fa-exchange-alt mr-1"></i>ST Assignment (Transfer of Title)</span>';
            } else if (app.instrument_type === 'Sectional Titling CofO') {
                instrumentTypeBadge = '<span class="badge badge-sectional-titling"><i class="fas fa-building mr-1"></i>ST CofO</span>';
            } else {
                instrumentTypeBadge = `<span class="badge badge-other-instrument"><i class="fas fa-file-alt mr-1"></i>${app.instrument_type || 'Other'}</span>`;
            }
            
            const row = `
                <tr class="cofo-row" data-status="${app.status}" data-id="${app.id}">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <input type="checkbox" class="rounded main-table-checkbox" 
                               data-id="${app.id}" 
                               data-status="${app.status}"
                               data-instrument-type="${app.instrument_type}"
                               data-fileno="${app.fileno}"
                               ${isDisabled ? 'disabled' : ''}
                               onchange="handleMainTableCheckboxChange()">
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">${regParticulars}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">${capturedDate}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">${regDate}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                        <span class="file-number">${app.fileno || 'N/A'}</span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">${parentFileNo}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                        <span class="status-badge badge-${app.status}">${app.status.charAt(0).toUpperCase() + app.status.slice(1)}</span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">${instrumentTypeBadge}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-medium">${app.Grantor || 'N/A'}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-medium">${app.Grantee || 'N/A'}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">${app.lga || 'N/A'}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">${app.district || 'N/A'}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">${app.plotNumber || 'N/A'}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">${app.size || 'N/A'}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                        <div class="dropdown-wrapper">
                            <button class="action-button text-gray-500 hover:text-gray-700 p-2 rounded-md transition-colors duration-200"
                                    onclick="toggleDropdown(this, '${app.id}')" type="button">
                                <i data-lucide="more-vertical" class="w-4 h-4"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
            
            tableBody.insertAdjacentHTML('beforeend', row);
        });
        
        // Re-initialize Lucide icons for new content
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
        
        // Update checkbox states after rendering the table
        setTimeout(() => {
            updateCheckboxStates();
        }, 50);
        
        // Update showing information
        const start = totalRecords === 0 ? 0 : startIndex + 1;
        const end = Math.min(endIndex, totalRecords);
        
        showingStart.textContent = start;
        showingEnd.textContent = end;
        showingTotal.textContent = totalRecords;
        
        // Update total records count in header
        document.getElementById('totalRecordsCount').textContent = `${totalRecords} Total Records`;
    }
    
    // Update pagination controls
    function updatePaginationControls() {
        const totalPages = Math.ceil(totalRecords / recordsPerPage);
        
        // Update previous/next buttons
        prevPageBtn.disabled = currentPage === 1;
        nextPageBtn.disabled = currentPage === totalPages || totalPages === 0;
        
        // Generate page numbers
        pageNumbers.innerHTML = '';
        
        if (totalPages <= 1) return;
        
        const maxVisiblePages = 5;
        let startPage = Math.max(1, currentPage - Math.floor(maxVisiblePages / 2));
        let endPage = Math.min(totalPages, startPage + maxVisiblePages - 1);
        
        // Adjust start page if we're near the end
        if (endPage - startPage < maxVisiblePages - 1) {
            startPage = Math.max(1, endPage - maxVisiblePages + 1);
        }
        
        // Add first page and ellipsis if needed
        if (startPage > 1) {
            addPageButton(1);
            if (startPage > 2) {
                pageNumbers.insertAdjacentHTML('beforeend', '<span class="px-2 py-1 text-gray-500">...</span>');
            }
        }
        
        // Add visible page numbers
        for (let i = startPage; i <= endPage; i++) {
            addPageButton(i);
        }
        
        // Add ellipsis and last page if needed
        if (endPage < totalPages) {
            if (endPage < totalPages - 1) {
                pageNumbers.insertAdjacentHTML('beforeend', '<span class="px-2 py-1 text-gray-500">...</span>');
            }
            addPageButton(totalPages);
        }
    }
    
    // Add page button
    function addPageButton(pageNum) {
        const isActive = pageNum === currentPage;
        const button = document.createElement('button');
        button.className = `px-3 py-2 text-sm font-medium rounded-md ${
            isActive 
                ? 'bg-blue-600 text-white' 
                : 'text-gray-500 bg-white border border-gray-300 hover:bg-gray-50'
        }`;
        button.textContent = pageNum;
        button.onclick = () => goToPage(pageNum);
        pageNumbers.appendChild(button);
    }
    
    // Go to specific page
    function goToPage(page) {
        const totalPages = Math.ceil(totalRecords / recordsPerPage);
        if (page >= 1 && page <= totalPages) {
            currentPage = page;
            updateTable();
            updatePaginationControls();
        }
    }
    
    // Event listeners
    recordsPerPageSelect.addEventListener('change', initializePagination);
    searchInput.addEventListener('input', filterData);
    prevPageBtn.addEventListener('click', () => goToPage(currentPage - 1));
    nextPageBtn.addEventListener('click', () => goToPage(currentPage + 1));
    
    // Initialize
    initializePagination();
    
    // Update checkbox states after page loads
    setTimeout(() => {
        updateCheckboxStates();
    }, 100);
});

// Also call updateCheckboxStates when the page is fully loaded
window.addEventListener('load', function() {
    setTimeout(() => {
        updateCheckboxStates();
    }, 200);
});
</script>

@endsection

