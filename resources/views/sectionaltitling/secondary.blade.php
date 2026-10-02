@extends('layouts.app')
@section('page-title')
  {{$PageTitle}}
@endsection


@include('sectionaltitling.partials.assets.css')
@section('content')
  <div class="flex-1 overflow-auto">
    <!-- Header -->
    @include('admin.header')
    <!-- Dashboard Content -->
    <div class="p-6">
      <style>
        /* ...existing styles... */
        .badge-approved {
          background-color: #22c55e;
          /* green */
          color: #fff;
        }

        .badge-pending {
          background-color: #fbbf24;
          /* yellow */
          color: #fff;
        }

        .badge-rejected {
          background-color: #ef4444;
          /* red */
          color: #fff;
        }

        .badge-progress {
          background-color: #3b82f6;
          /* blue */
          color: #fff;
        }

        .badge-declined {
          background-color: #a50b0b;
          /* purple */
          color: #fff;
        }

        .badge {
          padding: 0.25em 0.75em;
          border-radius: 0.375rem;
          font-size: 0.85em;
          font-weight: 600;
          display: inline-block;
        }
      </style>
      @if(!request()->has('survey') && (!request()->has('url') || (request()->get('url') !== 'phy_planning' && request()->get('url') !== 'recommendation')))
        @include('sectionaltitling.partials.statistic.SecondaryApplications2')
      @endif
      <!-- Secondary Applications Table - Screenshot 135 -->
      <div class="bg-white rounded-md shadow-sm border border-gray-200 p-6">
        <div class="flex justify-between items-center mb-6">
          <h2 class="text-xl font-bold">Secondary Applications</h2>

          @php
            $specialUrls = ['phy_planning', 'recommendation'];
            $isSurveyOrSpecialUrl = request()->has('survey') || (request()->has('url') && in_array(request()->get('url'), $specialUrls));
            $urlParam = request()->get('url');
            // Only add 'survey' if it exists in the request
            $query = [];
            if ($urlParam) {
              $query['url'] = $urlParam;
            }
            if (request()->has('survey')) {
              $query['survey'] = true;
            }
            $routeUrl = route('sectionaltitling.primary', $query);
          @endphp

          @if($isSurveyOrSpecialUrl)
            <a href="{{ $routeUrl }}"
              class="flex items-center space-x-2 px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">
              <i data-lucide="clipboard-list" class="w-4 h-4"></i>
              <span>View Primary Applications</span>
            </a>
          @endif





          <div class="flex items-center space-x-4">


            <div class="relative">
              <select id="statusFilter"
                class="pl-4 pr-8 py-2 border border-gray-200 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 appearance-none">
                <option value="All...">All...</option>
                <option value="Approved">Approved</option>
                <option value="Pending">Pending</option>
                <option value="Declined">Declined</option>
              </select>
              <i data-lucide="chevron-down"
                class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-4 h-4"></i>
            </div>



            <style>
              button:hover {
                background-color: #fed7aa;
              }
            </style>
            <button class="flex items-center space-x-2 px-4 py-2 border border-gray-200 rounded-md">
              <i data-lucide="download" class="w-4 h-4 text-gray-600"></i>
              <span>Export</span>
            </button>


          </div>
        </div>
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead>
              <tr class="text-xs">

                <th class="table-header text-green-500">SchemeNo</th>
                <th class="table-header text-green-500">Mother FileNo</th>
                <th class="table-header text-green-500">STFileNo</th>



                <th class="table-header text-green-500">Land Use</th>
                <th class="table-header text-green-500">Original Owner</th>
                <th class="table-header text-green-500">Unit Owner</th>
                <th class="table-header text-green-500">Unit</th>
                <th class="table-header text-green-500">Unit Type</th>
                <th class="table-header text-green-500">Phone Number</th>
                <th class="table-header text-green-500">Planning Recommendation</th>

                @if(!request()->has('survey') && (!request()->has('url') || (request()->get('url') !== 'phy_planning' && request()->get('url') !== 'recommendation')))
                  <th class="table-header text-green-500">Director's Approval</th>
                @endif
                <th class="table-header text-green-500">Actions</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              @foreach($SecondaryApplications as $app)
                <tr class="text-xs">

                  <td class="table-cell px-1 py-1 truncate">{{ $app->scheme_no ?? 'N/A' }}</td>
                  <td class="table-cell px-1 py-1 truncate">
                    @if($app->is_sua_unit == 1)
                      <span
                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border bg-purple-100 text-purple-800 border-purple-200">
                        {{ $app->fileno ?? 'N/A' }}
                      </span>
                    @else
                      <span
                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border bg-blue-100 text-blue-800 border-blue-200">
                        {{ $app->np_fileno ?? 'N/A' }}
                      </span>
                    @endif
                  </td>
                  <td class="table-cell px-1 py-1 truncate">
                    <span
                      class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border bg-green-100 text-green-800 border-green-200">
                      {{ $app->fileno ?? 'N/A' }}
                    </span>
                  </td>

                  <td class="table-cell px-1 py-1">
                    @php
                      $landUse = $app->is_sua_unit == 1 ? $app->sub_land_use : $app->land_use;
                    @endphp
                    @if($landUse)
                      @php
                        $landUseBadgeClass = '';
                        switch (strtolower($landUse)) {
                          case 'residential':
                            $landUseBadgeClass = 'bg-blue-100 text-blue-800 border-blue-200';
                            break;
                          case 'commercial':
                            $landUseBadgeClass = 'bg-green-100 text-green-800 border-green-200';
                            break;
                          case 'industrial':
                            $landUseBadgeClass = 'bg-red-100 text-red-800 border-red-200';
                            break;
                          default:
                            $landUseBadgeClass = 'bg-gray-100 text-gray-800 border-gray-200';
                        }
                      @endphp
                      <span
                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border {{ $landUseBadgeClass }}">
                        {{ $landUse }}
                      </span>
                    @else
                      <span
                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border bg-gray-100 text-gray-800 border-gray-200">
                        N/A
                      </span>
                    @endif
                  </td>
                  <td class="table-cell px-1 py-1">
                    <div class="flex items-center">
                      <div class="w-10 h-10 rounded-full bg-gray-200 flex items-center justify-center mr-2">
                        @if(!empty($app->mother_passport))
                          <img src="{{ asset('storage/' . $app->mother_passport) }}" alt="Original Owner Passport"
                            class="w-full h-full rounded-full object-cover cursor-pointer"
                            onclick="showPassportPreview('{{ asset('storage/' . $app->mother_passport) }}', 'Original Owner Passport')">
                        @elseif(!empty($app->mother_multiple_owners_passport))
                          @php
                            $passports = is_array($app->mother_multiple_owners_passport) ?
                              $app->mother_multiple_owners_passport :
                              json_decode($app->mother_multiple_owners_passport, true);
                            $firstPassport = !empty($passports) && isset($passports[0]) ? $passports[0] : null;
                          @endphp
                          @if($firstPassport)
                            <img src="{{ asset('storage/' . $firstPassport) }}" alt="Original Owner Passport"
                              class="w-full h-full rounded-full object-cover cursor-pointer" onclick="showMultipleOwners(
                                                             @json(is_array($app->mother_multiple_owners_names) ? $app->mother_multiple_owners_names : json_decode($app->mother_multiple_owners_names, true)), 
                                                             @json($passports)
                                                           )">
                          @else
                            <i data-lucide="{{ !empty($app->mother_corporate_name) ? 'building' : (!empty($app->mother_multiple_owners_names) ? 'users' : 'user') }}"
                              class="w-3 h-3 text-gray-500"></i>
                          @endif
                        @else
                          <i data-lucide="{{ !empty($app->mother_corporate_name) ? 'building' : (!empty($app->mother_multiple_owners_names) ? 'users' : 'user') }}"
                            class="w-3 h-3 text-gray-500"></i>
                        @endif
                      </div>
                      <div>
                        @if(!empty($app->mother_corporate_name))
                          <span>{{ $app->mother_corporate_name }}</span>
                        @elseif(!empty($app->mother_multiple_owners_names))
                          @php
                            $names = $app->mother_multiple_owners_names;
                            $decoded = [];
                            if (!empty($names)) {
                              $decoded = is_array($names) ? $names : json_decode($names, true);
                              if (!is_array($decoded))
                                $decoded = [];
                            }
                          @endphp
                          <span>{{ !empty($decoded) && isset($decoded[0]) ? $decoded[0] : '' }}</span>
                          @if(!empty($decoded))
                            <span class="ml-1 cursor-pointer text-blue-500" onclick="showMultipleOwners(
                                                              @json($decoded), 
                                                              @json(is_array($app->mother_multiple_owners_passport) ? $app->mother_multiple_owners_passport : json_decode($app->mother_multiple_owners_passport, true))
                                                            )">
                              <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 inline" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                              </svg>
                            </span>
                          @endif
                        @else
                          <span>{{ $app->mother_applicant_title ?? '' }} {{ $app->mother_first_name ?? '' }}
                            {{ $app->mother_middle_name ?? '' }} {{ $app->mother_surname ?? '' }}</span>
                        @endif
                      </div>
                    </div>
                  </td>
                  <td class="table-cell px-1 py-1">
                    <div class="flex items-center">
                      <div class="w-10 h-10 rounded-full bg-gray-200 flex items-center justify-center mr-2">
                        @if(!empty($app->passport))
                          <img src="{{ asset('storage/' . $app->passport) }}" alt="Unit Owner Passport"
                            class="w-full h-full rounded-full object-cover cursor-pointer"
                            onclick="showPassportPreview('{{ asset('storage/' . $app->passport) }}', 'Unit Owner Passport')">
                        @elseif(!empty($app->multiple_owners_passport))
                          @php
                            $passports = is_array($app->multiple_owners_passport) ?
                              $app->multiple_owners_passport :
                              json_decode($app->multiple_owners_passport, true);
                            $firstPassport = !empty($passports) && isset($passports[0]) ? $passports[0] : null;
                          @endphp
                          @if($firstPassport)
                            <img src="{{ asset('storage/' . $firstPassport) }}" alt="Unit Owner Passport"
                              class="w-full h-full rounded-full object-cover cursor-pointer" onclick="showMultipleOwners(
                                                             @json(is_array($app->multiple_owners_names) ? $app->multiple_owners_names : json_decode($app->multiple_owners_names, true)), 
                                                             @json($passports)
                                                           )">
                          @else
                            <i data-lucide="{{ !empty($app->corporate_name) ? 'building' : (!empty($app->multiple_owners_names) ? 'users' : 'user') }}"
                              class="w-3 h-3 text-gray-500"></i>
                          @endif
                        @else
                          <i data-lucide="{{ !empty($app->corporate_name) ? 'building' : (!empty($app->multiple_owners_names) ? 'users' : 'user') }}"
                            class="w-3 h-3 text-gray-500"></i>
                        @endif
                      </div>
                      <div>
                        @if(!empty($app->corporate_name))
                          <span>{{ $app->corporate_name }}</span>
                        @elseif(!empty($app->multiple_owners_names))
                          @php
                            $names = $app->multiple_owners_names;
                            $decoded = [];
                            if (!empty($names)) {
                              if (is_array($names)) {
                                $decoded = $names;
                              } else {
                                $tryJson = json_decode($names, true);
                                if (is_array($tryJson)) {
                                  $decoded = $tryJson;
                                } else {
                                  $decoded = array_map('trim', str_getcsv($names));
                                }
                              }
                            }
                          @endphp
                          <span>{{ !empty($decoded) && isset($decoded[0]) ? $decoded[0] : '' }}</span>
                          @if(!empty($decoded))
                            <span class="ml-1 cursor-pointer text-blue-500" onclick="showMultipleOwners(
                                                              @json($decoded), 
                                                              @json(is_array($app->multiple_owners_passport) ? $app->multiple_owners_passport : json_decode($app->multiple_owners_passport, true))
                                                            )">
                              <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 inline" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                              </svg>
                            </span>
                          @endif
                        @else
                          <span>{{ $app->applicant_title ?? '' }} {{ $app->first_name ?? '' }}
                            {{ $app->middle_name ?? '' }} {{ $app->surname ?? '' }}</span>
                        @endif
                      </div>
                    </div>
                  </td>
                  <td class="table-cell px-1 py-1 truncate">{{ $app->unit_number ?? 'N/A' }}</td>
                  <td class="table-cell px-1 py-1 truncate">
                    @php
                      // Determine if this is SUA or PUA
                      $isSUA = ($app->is_sua_unit == 1) || (strtoupper($app->unit_type ?? '') === 'SUA');
                      $displayUnitType = $isSUA ? 'SUA' : 'PUA';
                      $unitTypeTitle = $isSUA ? 'Standalone Unit Application' : 'Parented Unit Application';
                      $unitTypeBadgeClass = $isSUA
                        ? 'bg-purple-100 text-purple-800 border-purple-200'
                        : 'bg-blue-100 text-blue-800 border-blue-200';
                    @endphp
                    <span
                      class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border {{ $unitTypeBadgeClass }}"
                      title="{{ $unitTypeTitle }}">
                      {{ $displayUnitType }}
                    </span>
                  </td>
                  <td class="table-cell px-1 py-1 truncate">
                    @if(!empty($app->phone_number) && str_contains($app->phone_number, ','))
                      @php
                        $phones = array_map('trim', explode(',', $app->phone_number));
                        $firstPhone = $phones[0];
                        $allPhones = implode('<br>', $phones);
                      @endphp
                      <div class="relative group">
                        <span>{{ $firstPhone }}</span>
                        <i data-lucide="more-horizontal" class="inline-block w-3 h-3 text-gray-500 ml-1"></i>
                        <div
                          class="absolute hidden group-hover:block bg-white border border-gray-200 shadow-lg rounded-md p-2 z-10 text-xs">
                          {!! $allPhones !!}
                        </div>
                      </div>
                    @else
                      {{ $app->phone_number ?? 'N/A' }}
                    @endif
                  </td>
                  <td class="table-cell px-1 py-1">
                    @php
                      $planningStatus = $app->planning_recommendation_status;
                      $app_status = $app->application_status;

                      // Auto-approval logic for PUA
                      $isSUA_calculated = ($app->is_sua_unit == 1) || (strtoupper($app->unit_type ?? '') === 'SUA');
                      if (!$isSUA_calculated && strtolower($app->mother_planning_status ?? '') === 'approved') {
                        $planningStatus = 'Approved';
                      }

                      $planningStatusLower = strtolower($planningStatus ?? '');
                      $planningBadgeClass = match ($planningStatusLower) {
                        'approved' => 'bg-green-100 text-green-800 border-green-200',
                        'pending' => 'bg-yellow-100 text-yellow-800 border-yellow-200',
                        'declined' => 'bg-red-100 text-red-800 border-red-200',
                        'rejected' => 'bg-red-100 text-red-800 border-red-200',
                        'in progress' => 'bg-blue-100 text-blue-800 border-blue-200',
                        default => 'bg-gray-100 text-gray-800 border-gray-200'
                      };
                    @endphp
                    <div class="flex flex-col">
                      <span
                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border {{ $planningBadgeClass }}">
                        {{ $planningStatus ?? 'Pending' }}
                      </span>
                      <span class="text-[10px] text-gray-500 mt-1">
                        @if(!empty($app->planning_approval_date))
                          {{ \Carbon\Carbon::parse($app->planning_approval_date)->format('Y-m-d') }}
                        @else
                          --
                        @endif
                      </span>
                    </div>
                  </td>

                  @if(!request()->has('survey') && (!request()->has('url') || (request()->get('url') !== 'phy_planning' && request()->get('url') !== 'recommendation')))
                    <td class="table-cell px-1 py-1">
                      @php
                        $appStatusLower = strtolower($app_status ?? '');
                        $app_badgeClass = match ($appStatusLower) {
                          'approved' => 'bg-green-100 text-green-800 border-green-200',
                          'pending' => 'bg-yellow-100 text-yellow-800 border-yellow-200',
                          'declined' => 'bg-red-100 text-red-800 border-red-200',
                          'rejected' => 'bg-red-100 text-red-800 border-red-200',
                          'in progress' => 'bg-blue-100 text-blue-800 border-blue-200',
                          default => 'bg-gray-100 text-gray-800 border-gray-200'
                        };
                      @endphp
                      <div class="flex flex-col">
                        <span
                          class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border {{ $app_badgeClass }}">
                          {{ $app_status ?? 'Pending' }}
                        </span>
                        <span class="text-[10px] text-gray-500 mt-1">
                          @if(!empty($app->approval_date))
                            {{ \Carbon\Carbon::parse($app->approval_date)->format('Y-m-d') }}
                          @else
                            --
                          @endif
                        </span>
                      </div>
                    </td>
                  @endif
                  <td class="table-cell px-1 py-1">
                    @include('sectionaltitling.action_menu.sub_action')
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
        <div class="flex justify-between items-center mt-6 text-sm">
          <div class="text-gray-500" id="showingCount">Showing 0 of 0 applications</div>
          <div class="flex items-center space-x-2">
            <button id="prevPageBtn" class="px-3 py-1 border border-gray-200 rounded-md flex items-center" disabled>
              <i data-lucide="chevron-left" class="w-4 h-4 mr-1"></i>
              <span>Previous</span>
            </button>
            <button id="nextPageBtn" class="px-3 py-1 border border-gray-200 rounded-md flex items-center" disabled>
              <span>Next</span>
              <i data-lucide="chevron-right" class="w-4 h-4 ml-1"></i>
            </button>
          </div>
        </div>
      </div>

    </div>
    <!-- Footer -->
    @include('admin.footer')
  </div>
  @include('sectionaltitling.sub_action_modals.payment_modal')
  @include('sectionaltitling.sub_action_modals.other_departments')
  @include('sectionaltitling.sub_action_modals.eRegistry_modal')
  @include('sectionaltitling.sub_action_modals.recommendation')
  @include('sectionaltitling.sub_action_modals.directorApproval')

@endsection

<script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
  document.addEventListener('DOMContentLoaded', function () {
    // Add ID to the filter select if it doesn't have one
    const filterSelect = document.querySelector('select');
    if (filterSelect && !filterSelect.id) {
      filterSelect.id = 'statusFilter';
    }

    // Pagination variables
    window.secondaryTablePagination = {
      currentPage: 1,
      rowsPerPage: 10,
      filteredRows: [],
      allRows: []
    };

    function paginateTable(page = 1) {
      const { rowsPerPage, filteredRows } = window.secondaryTablePagination;
      const totalRows = filteredRows.length;
      const startIdx = (page - 1) * rowsPerPage;
      const endIdx = Math.min(startIdx + rowsPerPage, totalRows);

      // Hide all rows first
      filteredRows.forEach(row => {
        row.style.display = 'none';
      });

      // Show only the rows for current page
      for (let i = startIdx; i < endIdx; i++) {
        if (filteredRows[i]) {
          filteredRows[i].style.display = '';
        }
      }

      // Update showing count
      const showingCount = document.getElementById('showingCount');
      const showing = Math.min(rowsPerPage, totalRows - startIdx);
      showingCount.textContent = `Showing ${showing} of ${totalRows} applications`;

      // Enable/disable buttons
      document.getElementById('prevPageBtn').disabled = page === 1;
      document.getElementById('nextPageBtn').disabled = endIdx >= totalRows;

      window.secondaryTablePagination.currentPage = page;
    }

    function filterTable(selectedStatus) {
      const allRows = window.secondaryTablePagination.allRows;
      let filteredRows = [];

      allRows.forEach(row => {
        let showRow = false;
        if (selectedStatus === 'All...') {
          showRow = true;
        } else {
          // No badge columns in this table, so just show all for now or implement your own logic
          showRow = row.innerText.includes(selectedStatus);
        }
        row.style.display = showRow ? '' : 'none';
        if (showRow) filteredRows.push(row);
      });

      window.secondaryTablePagination.filteredRows = filteredRows;
      paginateTable(1);
    }

    // Initial setup - only count actual data rows, not empty or template rows
    window.secondaryTablePagination.allRows = Array.from(document.querySelectorAll('tbody tr')).filter(row => {
      // Filter out empty rows or rows without actual data
      const cells = row.querySelectorAll('td');
      return cells.length > 0 && row.textContent.trim() !== '';
    });
    window.secondaryTablePagination.filteredRows = window.secondaryTablePagination.allRows;

    // Debug: Log the actual count
    console.log('Total rows found:', window.secondaryTablePagination.allRows.length);
    console.log('Filtered rows:', window.secondaryTablePagination.filteredRows.length);

    paginateTable(1);

    // Filter event
    const statusFilter = document.getElementById('statusFilter');
    if (statusFilter) {
      statusFilter.addEventListener('change', function () {
        filterTable(this.value);
      });
    }

    // Pagination events
    document.getElementById('prevPageBtn').addEventListener('click', function () {
      const { currentPage } = window.secondaryTablePagination;
      if (currentPage > 1) paginateTable(currentPage - 1);
    });
    document.getElementById('nextPageBtn').addEventListener('click', function () {
      const { currentPage, filteredRows, rowsPerPage } = window.secondaryTablePagination;
      if (currentPage * rowsPerPage < filteredRows.length) paginateTable(currentPage + 1);
    });

    // Export to CSV
    document.querySelector('button.flex.items-center.space-x-2.px-4.py-2.border.border-gray-200.rounded-md').addEventListener('click', function () {
      exportVisibleTableToCSV();
    });

    function exportVisibleTableToCSV() {
      const table = document.querySelector('table');
      const headers = Array.from(table.querySelectorAll('thead th')).map(th => th.innerText.trim());
      const { filteredRows, currentPage, rowsPerPage } = window.secondaryTablePagination;
      const startIdx = (currentPage - 1) * rowsPerPage;
      const endIdx = startIdx + rowsPerPage;
      const visibleRows = filteredRows.slice(startIdx, endIdx);

      let csvContent = '';
      csvContent += headers.join(',') + '\n';

      visibleRows.forEach(row => {
        const cells = Array.from(row.querySelectorAll('td')).map(td => {
          return '"' + td.innerText.replace(/"/g, '""').replace(/\n/g, ' ').replace(/,/g, ' ') + '"';
        });
        csvContent += cells.join(',') + '\n';
      });

      // Download CSV
      const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = 'secondary_applications.csv';
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      URL.revokeObjectURL(url);
    }

    // Re-filter and paginate on load
    filterTable(statusFilter ? statusFilter.value : 'All...');
  });

  window.showFullNames = function (owners) {
    if (!Array.isArray(owners)) {
      owners = [];
    }
    if (owners.length > 0) {
      Swal.fire({
        title: 'Full Names of Multiple Owners',
        html: '<ul>' + owners.map(name => `<li>${name}</li>`).join('') + '</ul>',
        icon: 'info',
        confirmButtonText: 'Close'
      });
    } else {
      Swal.fire({
        title: 'Full Names of Multiple Owners',
        text: 'No owners available',
        icon: 'info',
        confirmButtonText: 'Close'
      });
    }
  }

  window.showPassportPreview = function (imageSrc, title) {
    Swal.fire({
      title: title,
      html: `<img src="${imageSrc}" class="img-fluid" style="max-height: 400px;">`,
      width: 'auto',
      showCloseButton: true,
      showConfirmButton: false
    });
  }

  window.showMultipleOwners = function (owners, passports) {
    if (Array.isArray(owners) && owners.length > 0) {
      let htmlContent = '<div class="grid grid-cols-3 gap-4" style="max-width: 600px;">';

      owners.forEach((name, index) => {
        const passport = Array.isArray(passports) && passports[index]
          ? `<img src="{{ asset('storage/') }}${passports[index]}" 
             class="w-24 h-32 object-cover mx-auto border-2 border-gray-300" 
             style="object-position: center top;">`
          : '<div class="w-24 h-32 bg-gray-300 mx-auto flex items-center justify-center"><span>No Image</span></div>';

        htmlContent += `
        <div class="flex flex-col items-center">
          <div class="passport-container bg-blue-50 p-2 rounded">
            ${passport}
            <p class="text-center text-sm font-medium mt-1">${name}</p>
          </div>
        </div>
      `;
      });

      htmlContent += '</div>';

      Swal.fire({
        title: 'Multiple Owners',
        html: htmlContent,
        width: 'auto',
        showCloseButton: true,
        showConfirmButton: false
      });
    } else {
      Swal.fire({
        title: 'Multiple Owners',
        text: 'No owners available',
        icon: 'info',
        confirmButtonText: 'Close'
      });
    }
  }
</script>