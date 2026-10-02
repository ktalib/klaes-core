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
        // The rest will be handled by the main JS file
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
                        <span>{{ $totalCount ?? 0 }} Total Records</span>
                    </div>
                </div>
                 
                <!-- Search -->
                <div class="relative">
                    <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                    <input id="searchInput" type="search" placeholder="Search by File No..." 
                           class="search-input pl-10 pr-4 py-2.5 text-sm w-80 rounded-lg">
                </div>
            </div>
        
            <!-- Table -->
            <div class="overflow-x-auto">
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
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer" onclick="sortTable(12)">
                  Date
                  <span class="inline-block align-middle" id="sortIcon-12"></span>
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
                  <input type="checkbox" class="rounded main-table-checkbox" 
                         data-id="{{ $app->id }}" 
                         data-status="{{ $app->status }}"
                         {{ $app->status === 'registered' ? 'disabled' : '' }}
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
                    <span class="text-gray-400 text-xs">Not Registered</span>
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
                <!-- 12. Date -->
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                  @if($app->deeds_date)
                    <div class="flex items-center">
                      <i class="fas fa-calendar-alt text-gray-400 mr-2"></i>
                      {{ date('M d, Y', strtotime($app->deeds_date)) }}
                    </div>
                  @else
                    <span class="text-gray-400">N/A</span>
                  @endif
                </td>
                <!-- 13. Action -->
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
                <td colspan="14" class="px-6 py-10 text-center text-gray-500">
                  No instrument registrations available.
                </td>
                </tr>
                @endforelse
              </tbody>
              </table>
            </div>

            <script>
            let sortDirections = {1: true}; // Default sort direction for column 1 is ascending
            function sortTable(colIndex) {
              const table = document.getElementById('instrumentTable');
              const tbody = table.tBodies[0];
              const rows = Array.from(tbody.querySelectorAll('tr')).filter(row => !row.querySelector('td[colspan]'));
              const isNumeric = [11].includes(colIndex); // Plot Size column (index 11) is numeric
              const isDate = [12].includes(colIndex); // Date column (index 12) is date
              sortDirections[colIndex] = !sortDirections[colIndex];
              rows.sort((a, b) => {
              let aText = a.children[colIndex]?.innerText.trim() || '';
              let bText = b.children[colIndex]?.innerText.trim() || '';
              if (isNumeric) {
                aText = parseFloat(aText.replace(/[^0-9.]/g, '')) || 0;
                bText = parseFloat(bText.replace(/[^0-9.]/g, '')) || 0;
              } else if (isDate) {
                aText = new Date(aText);
                bText = new Date(bText);
              }
              if (aText < bText) return sortDirections[colIndex] ? -1 : 1;
              if (aText > bText) return sortDirections[colIndex] ? 1 : -1;
              return 0;
              });
              // Remove all rows and re-append sorted
              rows.forEach(row => tbody.appendChild(row));
              // Update sort icons
              for (let i = 1; i <= 12; i++) {
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
  // Pass PHP data to JavaScript
  const serverCofoData = @json($approvedApplications);
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
    
    const registerClass = app.status === 'pending' ? 'text-gray-700 hover:bg-gray-100' : 'text-gray-400 cursor-not-allowed';
    const registerIcon = app.status === 'pending' ? 'text-green-500' : 'text-gray-300';
    const registerClick = app.status === 'pending' ? `onclick="openSingleRegisterModalWithData('${app.id}'); return false;"` : 'onclick="return false;"';
    
    const deleteClass = app.status === 'pending' ? 'text-red-600 hover:bg-gray-100' : 'text-gray-400 cursor-not-allowed';
    const deleteIcon = app.status === 'pending' ? '' : 'text-gray-300';
    const deleteClick = app.status === 'pending' ? `onclick="deleteInstrument('${app.id}'); return false;"` : 'onclick="return false;"';
    
    const viewCorContent = app.status === 'registered' && app.STM_Ref 
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

@endsection