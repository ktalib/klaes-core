@extends('layouts.app')

@section('page-title')
    {{ $PageTitle ?? __('KLAES') }}
@endsection

@section('styles')

@endsection

@section('content')
<style>
    .badge {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 0.25rem 0.5rem;
      border-radius: 0.25rem;
      font-size: 0.75rem;
      font-weight: 500;
    }
    .badge-approved {
      background-color: #d1fae5;
      color: #059669;
    }
    .badge-pending {
      background-color: #fef3c7;
      color: #d97706;
    }
    .badge-declined {
      background-color: #fee2e2;
      color: #dc2626;
    }
    .table-header {
      background-color: #f9fafb;
      font-weight: 500;
      color: rgb(13, 136, 13);
      text-align: left;
      padding: 0.75rem 1rem;
      border-bottom: 1px solid #e5e7eb;
    }
    .table-cell {
      padding: 0.75rem 1rem;
      border-bottom: 1px solid #e5e7eb;
    }
</style>

<!-- Add the script at the beginning of the content section to ensure it's loaded before the buttons -->
<script>
  function showTab(tabId) {
    // Hide all tab contents
    document.getElementById('primary-survey').classList.add('hidden');
    document.getElementById('unit-survey').classList.add('hidden');
    
    // Reset all tab buttons
    document.getElementById('primary-survey-tab').classList.remove('bg-green-600', 'text-white');
    document.getElementById('primary-survey-tab').classList.add('bg-white', 'text-gray-700', 'border', 'border-gray-200');
    document.getElementById('unit-survey-tab').classList.remove('bg-green-600', 'text-white');
    document.getElementById('unit-survey-tab').classList.add('bg-white', 'text-gray-700', 'border', 'border-gray-200');
    
    // Show selected tab content
    document.getElementById(tabId).classList.remove('hidden');
    
    // Highlight active tab button
    document.getElementById(tabId + '-tab').classList.remove('bg-white', 'text-gray-700', 'border', 'border-gray-200');
    document.getElementById(tabId + '-tab').classList.add('bg-green-600', 'text-white');
  }
  
  // Add dropdown toggle functionality
  function customToggleDropdown(button, event) {
    event.stopPropagation();
    
    // Close all other open dropdowns first
    const allMenus = document.querySelectorAll('.action-menu');
    allMenus.forEach(menu => {
      if (menu !== button.nextElementSibling && !menu.classList.contains('hidden')) {
        menu.classList.add('hidden');
      }
    });
    
    // Toggle the clicked dropdown
    const menu = button.nextElementSibling;
    menu.classList.toggle('hidden');
    
    // Position the dropdown near the button
    if (!menu.classList.contains('hidden')) {
      const rect = button.getBoundingClientRect();
      menu.style.top = rect.bottom + 'px';
      menu.style.left = (rect.left - menu.offsetWidth + rect.width) + 'px';
    }
  }
  
  // Close dropdowns when clicking outside
  document.addEventListener('click', function(event) {
    const allMenus = document.querySelectorAll('.action-menu');
    allMenus.forEach(menu => {
      menu.classList.add('hidden');
    });
  });
</script>

<div class="flex-1 overflow-auto">
    <!-- Header -->
    @include($headerPartial ?? 'admin.header')
    
    <!-- Main Content -->
    <div class="p-6">
    <!-- Payments Overview -->
   @include('programmes.partials.other_tabs')
      <!-- Payments Table -->
  
      <!-- Primary Application Surveys -->
    <div class="bg-white rounded-md shadow-sm border border-gray-200 p-6">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-xl font-bold">Lands</h2>
        </div>
        
        <!-- Search Filters -->
        <div class="mb-6 bg-white p-5 rounded-lg border border-gray-200 shadow-sm">
          <h3 class="text-lg font-semibold mb-4 text-gray-800 flex items-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
            </svg>
            Smart Search
          </h3>
          
          <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
            <!-- ST File No Search -->
            <div class="relative">
              <label for="st-filter" class="block text-sm font-medium text-gray-700 mb-1">ST File No</label>
              <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                  <svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                  </svg>
                </div>
                <input type="text" id="st-filter" class="w-full pl-10 pr-10 py-2 rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring focus:ring-green-200 focus:ring-opacity-50 transition" 
                     placeholder="Enter ST File No" oninput="instantFilter()">
                <button type="button" class="clear-input absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600" data-input="st-filter">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                  </svg>
                </button>
              </div>
            </div>
            
            <!-- Approval Status Filter -->
            <div class="relative">
              <label for="approval-filter" class="block text-sm font-medium text-gray-700 mb-1">Approval Status</label>
              <select id="approval-filter" class="w-full py-2 rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring focus:ring-green-200 focus:ring-opacity-50 transition" onchange="instantFilter()">
                <option value="">All Statuses</option>
                <option value="approved">Approved</option>
                <option value="pending">Pending</option>
                <option value="declined">Declined</option>
              </select>
            </div>
            
            <!-- Date Range Filter -->
            <div class="relative">
              <label for="date-filter" class="block text-sm font-medium text-gray-700 mb-1">Commissioning Date</label>
              <input type="date" id="date-filter" class="w-full py-2 rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring focus:ring-green-200 focus:ring-opacity-50 transition" onchange="instantFilter()">
            </div>
          </div>
          
          <div class="mt-5 flex flex-wrap items-center justify-between">
            <div class="flex items-center space-x-2 mb-2 sm:mb-0">
              <span id="results-count" class="text-sm text-gray-600">Showing all records</span>
              <div id="active-filters" class="flex flex-wrap gap-2"></div>
            </div>
            
            <div class="flex space-x-3">
              <button id="reset-filters" class="flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                Reset
              </button>
              <button id="apply-filters" class="flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                Apply Filters
              </button>
            </div>
          </div>
        </div>

        <script>
          function instantFilter() {
            // Get filter values
            const stValue = document.getElementById('st-filter').value.toLowerCase();
            const approvalValue = document.getElementById('approval-filter').value.toLowerCase();
            const dateValue = document.getElementById('date-filter').value;
            
            // Clear active filters display
            const activeFilters = document.getElementById('active-filters');
            activeFilters.innerHTML = '';
            
            // Count visible rows
            let visibleRows = 0;
            let totalRows = 0;
            
            // Show active filters as badges
            if (stValue) {
              addFilterBadge('ST: ' + stValue, 'st-filter');
            }
            if (approvalValue) {
              addFilterBadge('Status: ' + approvalValue, 'approval-filter');
            }
            if (dateValue) {
              addFilterBadge('Date: ' + dateValue, 'date-filter');
            }
            
            // Filter the table rows
            const rows = document.querySelectorAll('#lands-table tbody tr');
            rows.forEach(row => {
              totalRows++;
              const stMatch = !stValue || row.cells[0].textContent.toLowerCase().includes(stValue);
              
              // Date filtering
              let dateMatch = true;
              if (dateValue) {
                const rowDate = row.cells[1].textContent.trim();
                if (rowDate !== 'N/A') {
                  // Convert date format if needed
                  dateMatch = rowDate.includes(dateValue);
                } else {
                  dateMatch = false;
                }
              }
              
              // Approval status filtering
              let approvalMatch = true;
              if (approvalValue) {
                const sitePlanStatus = row.cells[3].textContent.toLowerCase();
                const surveyPlanStatus = row.cells[4].textContent.toLowerCase();
                approvalMatch = sitePlanStatus.includes(approvalValue) || 
                        surveyPlanStatus.includes(approvalValue);
              }
              
              if (stMatch && dateMatch && approvalMatch) {
                row.style.display = '';
                visibleRows++;
              } else {
                row.style.display = 'none';
              }
            });
            
            // Update results count
            document.getElementById('results-count').textContent = 
              `Showing ${visibleRows} of ${totalRows} records`;
          }
          
          function addFilterBadge(text, filterId) {
            const activeFilters = document.getElementById('active-filters');
            const badge = document.createElement('span');
            badge.className = 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800';
            badge.innerHTML = `
              ${text}
              <button type="button" class="ml-1.5 inline-flex items-center justify-center h-4 w-4 rounded-full bg-green-200 text-green-500 hover:bg-green-300" onclick="clearFilter('${filterId}')">
                <svg class="h-2 w-2" stroke="currentColor" fill="none" viewBox="0 0 8 8">
                  <path stroke-linecap="round" stroke-width="1.5" d="M1 1l6 6m0-6L1 7" />
                </svg>
              </button>
            `;
            activeFilters.appendChild(badge);
          }
          
          function clearFilter(filterId) {
            const element = document.getElementById(filterId);
            if (element.tagName === 'SELECT') {
              element.selectedIndex = 0;
            } else {
              element.value = '';
            }
            instantFilter();
          }
          
          // Initialize when content loads
          document.addEventListener('DOMContentLoaded', function() {
            // Connect reset button
            document.getElementById('reset-filters').addEventListener('click', function() {
              document.getElementById('st-filter').value = '';
              document.getElementById('approval-filter').selectedIndex = 0;
              document.getElementById('date-filter').value = '';
              instantFilter();
            });
            
            // Connect apply button (for backward compatibility)
            document.getElementById('apply-filters').addEventListener('click', instantFilter);
            
            // Add individual clear button functionality
            const clearButtons = document.querySelectorAll('.clear-input');
            clearButtons.forEach(button => {
              button.addEventListener('click', function() {
                const inputId = this.getAttribute('data-input');
                document.getElementById(inputId).value = '';
                instantFilter();
              });
            });
          });
        </script>
        
        <script>
            // Add individual clear button functionality
            document.addEventListener('DOMContentLoaded', function() {
          const clearButtons = document.querySelectorAll('.clear-input');
          clearButtons.forEach(button => {
              button.addEventListener('click', function() {
            const inputId = this.getAttribute('data-input');
            document.getElementById(inputId).value = '';
            document.getElementById(inputId).focus();
              });
          });
          
          // Add "Enter" key functionality
          const inputs = document.querySelectorAll('#mls-filter, #kangis-filter, #st-filter');
          inputs.forEach(input => {
              input.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                document.getElementById('apply-filters').click();
            }
              });
          });
            });
        </script>
        <div class="overflow-x-auto">
            <table id="lands-table" class="min-w-full divide-y divide-gray-200">
                <thead>
                  <tr class="text-xs">
                  
                        <th class="table-header">ST FileNo</th>
                        <th class="table-header">Commissioning Date</th>
                        <th class="table-header">Decommissioning Date</th>
                       
                       
                        <th class="table-header">Current Locaction</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @if(count($lands) > 0)
                        @foreach($lands as $land)

                                <td class="table-cell">{{ $land->Sectional_Title_File_No ?? 'N/A' }}</td>
                                <td class="table-cell">{{ $land->Commissioning_Date ?? 'N/A' }}</td>
                                <td class="table-cell">{{ $land->Decommissioning_Date ?? 'N/A' }}</td>
                            
                            
                                
                                <td class="table-cell">{{ $land->Current_Office ?? 'N/A' }}</td>
                            </tr>
                        @endforeach
                    @else
                        <tr>
                            <td colspan="10" class="table-cell text-center py-4">No lands data available</td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>
    <!-- Page Footer -->
    @include($footerPartial ?? 'admin.footer')
</div>
@endsection

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const table = document.getElementById('lands-table');
        const mlsFilter = document.getElementById('mls-filter');
        const kangisFilter = document.getElementById('kangis-filter');
        const stFilter = document.getElementById('st-filter');
        const applyFiltersBtn = document.getElementById('apply-filters');
        const resetFiltersBtn = document.getElementById('reset-filters');
        
        // Function to filter the table
        function filterTable() {
            const mlsValue = mlsFilter.value.toLowerCase();
            const kangisValue = kangisFilter.value.toLowerCase();
            const stValue = stFilter.value.toLowerCase();
            
            const rows = table.querySelectorAll('tbody tr');
            
            rows.forEach(row => {
                const mlsCell = row.cells[0].textContent.toLowerCase();
                const kangisCell = row.cells[1].textContent.toLowerCase();
                const stCell = row.cells[3].textContent.toLowerCase();
                
                const matchesMls = mlsValue === '' || mlsCell.includes(mlsValue);
                const matchesKangis = kangisValue === '' || kangisCell.includes(kangisValue);
                const matchesSt = stValue === '' || stCell.includes(stValue);
                
                if (matchesMls && matchesKangis && matchesSt) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }
        
        // Apply filters when the button is clicked
        applyFiltersBtn.addEventListener('click', filterTable);
        
        // Reset filters when the reset button is clicked
        resetFiltersBtn.addEventListener('click', function() {
            mlsFilter.value = '';
            kangisFilter.value = '';
            stFilter.value = '';
            
            // Show all rows
            const rows = table.querySelectorAll('tbody tr');
            rows.forEach(row => {
                row.style.display = '';
            });
        });
        
        // Also filter when pressing Enter in the input fields
        [mlsFilter, kangisFilter, stFilter].forEach(input => {
            input.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    filterTable();
                }
            });
        });
    });
</script>
@endsection


