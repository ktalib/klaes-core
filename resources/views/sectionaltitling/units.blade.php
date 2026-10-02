@extends('layouts.app')
@section('page-title')
  {{ __('Parented Units') }}
@endsection
@php
  use Illuminate\Support\Str;
@endphp


@include('sectionaltitling.partials.assets.css')
@section('content')
  <div class="flex-1 overflow-auto">
    <!-- Header -->
    @include('admin.header')
    <!-- Dashboard Content -->
    <div class="p-6">
      <!-- Stats Cards -->
      <!-- <div class="mb-6">
                      <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 flex items-center space-x-3">
                        <div class="animate-spin">
                      <i data-lucide="refresh-cw" class="w-5 h-5 text-blue-600"></i>
                        </div>
                        <div>
                      <p class="text-blue-800 font-medium">Updating...</p>
                      <p class="text-blue-600 text-sm">Refreshing Parented Units data</p>
                        </div>
                      </div>
                    </div> -->

      {{-- @include('sectionaltitling.partials.statistic.statistic_card') --}}
      <!-- SecondaryApplications Overview  -->
      @include('sectionaltitling.partials.statistic.SecondaryApplications')
      <!-- Secondary Applications Table -->
      <div class="bg-white rounded-xl shadow-lg border border-gray-100 overflow-hidden">
        <!-- Header Section -->
        <div class="bg-gradient-to-r from-blue-50 to-indigo-50 px-8 py-6 border-b border-gray-100">
          <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4">
            <div>
              <h2 class="text-2xl font-bold text-gray-900">Parented Units</h2>
              <p class="text-sm text-gray-600 mt-1">Manage and track all Parented Units</p>
            </div>

            <div class="flex items-center space-x-3">
              <!-- Search Input -->
              <div class="relative">
                <i data-lucide="search"
                  class="absolute left-3 top-1/2 transform -translate-y-1/2 w-4 h-4 text-gray-400"></i>
                <input type="text" id="globalSearch" placeholder="Search applications..."
                  class="pl-10 pr-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm w-64">
              </div>

              <!-- Export Button -->
              <div class="relative">
                <button id="exportBtn"
                  class="flex items-center space-x-2 px-4 py-2 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors duration-200 shadow-sm">
                  <i data-lucide="download" class="w-4 h-4 text-gray-600"></i>
                  <span class="text-gray-700 font-medium">Export</span>
                  <i data-lucide="chevron-down" class="w-4 h-4 text-gray-400"></i>
                </button>
              </div>

              <!-- Filter Button -->
              <button id="filterBtn"
                class="flex items-center space-x-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors duration-200 shadow-sm">
                <i data-lucide="filter" class="w-4 h-4"></i>
                <span class="font-medium">Filter</span>
              </button>
            </div>
          </div>
        </div>

        <!-- Table Container -->
        <div class="overflow-x-auto">
          <table id="unitsTable" class="w-full">
            <thead class="bg-gray-50">
              <tr>

                <th
                  class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider border-b border-gray-200">
                  <div class="flex items-center space-x-1">
                    <span>Scheme No</span>

                  </div>
                </th>
                <th
                  class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider border-b border-gray-200">
                  <div class="flex items-center space-x-1">
                    <span>NP FileNo</span>

                  </div>
                </th>
                <th
                  class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider border-b border-gray-200">
                  <div class="flex items-center space-x-1">
                    <span>Unit FileNo</span>
                  </div>
                </th>
                <th
                  class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider border-b border-gray-200">
                  <div class="flex items-center space-x-1">
                    <span>Unit Type</span>
                  </div>
                </th>
                <th
                  class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider border-b border-gray-200">
                  <div class="flex items-center space-x-1">
                    <span>Land Use</span>

                  </div>
                </th>
                <th
                  class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider border-b border-gray-200">
                  <div class="flex items-center space-x-1">
                    <span>Original Owner</span>

                  </div>
                </th>
                <th
                  class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider border-b border-gray-200">
                  <div class="flex items-center space-x-1">
                    <span>Unit Owner</span>

                  </div>
                </th>
                <th
                  class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider border-b border-gray-200">
                  <div class="flex items-center space-x-1">
                    <span>Unit No</span>

                  </div>
                </th>
                <th
                  class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider border-b border-gray-200">
                  <div class="flex items-center space-x-1">
                    <span>Phone Number</span>

                  </div>
                </th>
                <th
                  class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider border-b border-gray-200">
                  <div class="flex items-center space-x-1">
                    <span>Application Date</span>

                  </div>
                </th>

                <th
                  class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider border-b border-gray-200">
                  <div class="flex items-center space-x-1">
                    <span>Date Captured</span>
                    <i data-lucide="arrow-up-down" class="w-3 h-3 text-gray-400"></i>
                  </div>
                </th>
                <th
                  class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider border-b border-gray-200">
                  <div class="flex items-center space-x-1">
                    <span>Created By</span>
                  </div>
                </th>
                <th
                  class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider border-b border-gray-200"
                  data-column="jsi-status">
                  <div class="flex items-center space-x-1">
                    <span>JSI Status</span>
                  </div>
                </th>
                <th
                  class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider border-b border-gray-200"
                  data-column="jsi-approval">
                  <div class="flex items-center space-x-1">
                    <span>JSI Approval</span>
                  </div>
                </th>
                <th
                  class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider border-b border-gray-200">
                  <div class="flex items-center space-x-1">
                    <span>Planning Recommendation</span>

                  </div>
                </th>
                <th
                  class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider border-b border-gray-200">
                  <div class="flex items-center space-x-1">
                    <span>Director's Approval</span>

                  </div>
                </th>
                <th
                  class="px-6 py-4 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider border-b border-gray-200">
                  Actions
                </th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-100">
              @foreach($SecondaryApplications as $app)
                @php
                  $isSua = (int) ($app->is_sua_unit ?? 0) === 1;
                  $unitType = $isSua ? 'SUA' : 'PUA';
                  $unitTypeTitle = $isSua ? 'Standalone Unit Application' : 'Parented Unit Application';
                @endphp
                <tr class="hover:bg-gray-50 transition-colors duration-150">

                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    <div class="font-medium">{{ $app->scheme_no ?? 'N/A' }}</div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">


                    <div class="font-mono text-xs bg-blue-100 px-2 py-1 rounded text-blue-800"
                      title="New Primary FileNo (NPFN)">

                      {{ $app->np_fileno ?? $app->mother_np_fileno ?? $app->mother_fileno ?? 'N/A' }}
                    </div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">

                    <div class="font-mono text-xs bg-green-100 px-2 py-1 rounded text-green-800"
                      title="Unit FileNo (NP FileNo + Serial)">{{ $app->fileno }}</div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    <span
                      class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border {{ $isSua ? 'bg-purple-100 text-purple-800 border-purple-200' : 'bg-blue-100 text-blue-800 border-blue-200' }}"
                      title="{{ $unitTypeTitle }}">
                      {{ $unitType }}
                    </span>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    @if($app->land_use)
                      @php
                        $landUse = $app->land_use ?? $app->mother_land_use ?? 'N/A';
                        $landUseIcon = '';
                        $landUseBadgeClass = '';
                        switch (strtolower($landUse)) {
                          case 'residential':
                            $landUseIcon = 'home';
                            $landUseBadgeClass = 'bg-blue-100 text-blue-800 border-blue-200';
                            break;
                          case 'commercial':
                            $landUseIcon = 'briefcase';
                            $landUseBadgeClass = 'bg-green-100 text-green-800 border-green-200';
                            break;
                          case 'industrial':
                            $landUseIcon = 'factory';
                            $landUseBadgeClass = 'bg-red-100 text-red-800 border-red-200';
                            break;
                          case 'mixed use':
                            $landUseIcon = 'layers';
                            $landUseBadgeClass = 'bg-yellow-100 text-yellow-800 border-yellow-200';
                            break;
                          default:
                            $landUseIcon = 'map-pin';
                            $landUseBadgeClass = 'bg-gray-100 text-gray-800 border-gray-200';
                        }
                      @endphp
                      <span
                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border {{ $landUseBadgeClass }}">
                        <i data-lucide="{{ $landUseIcon }}" class="w-3 h-3 mr-1"></i>
                        {{ $landUse }}
                      </span>
                    @else
                      <span
                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border bg-gray-100 text-gray-800 border-gray-200">
                        <i data-lucide="map-pin" class="w-3 h-3 mr-1"></i>
                        N/A
                      </span>
                    @endif
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
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
                          <span>{{ Str::of($app->mother_corporate_name)->title() }}</span>
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
                          <span>{{ !empty($decoded) && isset($decoded[0]) ? Str::of($decoded[0])->title() : '' }}</span>
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
                          <span>{{ Str::of(trim(($app->mother_applicant_title ?? '') . ' ' . ($app->mother_first_name ?? '') . ' ' . ($app->mother_middle_name ?? '') . ' ' . ($app->mother_surname ?? '')))->title() }}</span>
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
                          <span>{{ Str::of($app->corporate_name)->title() }}</span>
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
                          <span>{{ !empty($decoded) && isset($decoded[0]) ? Str::of($decoded[0])->title() : '' }}</span>
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
                          <span>{{ Str::of(trim(($app->applicant_title ?? '') . ' ' . ($app->first_name ?? '') . ' ' . ($app->middle_name ?? '') . ' ' . ($app->surname ?? '')))->title() }}</span>
                        @endif
                      </div>
                    </div>
                  </td>
                  <td class="table-cell px-1 py-1 truncate">{{ $app->unit_number ?? 'N/A' }}</td>
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
                  <td class="table-cell px-1 py-1 truncate">
                    {{ $app->application_date ? \Carbon\Carbon::parse($app->application_date)->format('M d, Y') : 'N/A' }}
                  </td>
                  <td class="table-cell px-1 py-1 truncate" data-order="{{ $app->sys_date }}">
                    {{ $app->sys_date ? \Carbon\Carbon::parse($app->sys_date)->format('M d, Y') : 'N/A' }}
                  </td>
                  <td class="table-cell px-1 py-1 truncate">
                    @php $stCreatedBy = trim((string) ($app->created_by_name ?? '')); @endphp
                    @if($stCreatedBy !== '')
                      <span class="upc-trigger" data-user-card
                            data-user-id="{{ $app->created_by }}"
                            data-user-name="{{ $stCreatedBy }}"
                            title="{{ __('View profile') }}">{{ $stCreatedBy }}</span>
                    @else
                      <span class="text-gray-400">&mdash;</span>
                    @endif
                  </td>
                  @php
                    $jsiApproved = (int) ($app->jsi_is_approved ?? 0) === 1;
                    $jsiSubmitted = (int) ($app->jsi_is_submitted ?? 0) === 1;
                    $jsiGenerated = (int) ($app->jsi_is_generated ?? 0) === 1;
                    $jsiHasRecord = $jsiApproved || $jsiSubmitted || $jsiGenerated;

                    $jsiCaptureLabel = $jsiHasRecord ? 'Captured' : 'Not Captured';
                    $jsiCaptureBadgeClass = $jsiHasRecord
                      ? 'bg-green-100 text-green-800 border-green-200'
                      : 'bg-red-100 text-red-800 border-red-200';
                    $jsiCaptureDate = $jsiHasRecord && $app->jsi_inspection_date
                      ? \Carbon\Carbon::parse($app->jsi_inspection_date)->format('M d, Y')
                      : null;

                    $jsiApprovalLabel = $jsiApproved ? 'Approved' : 'Pending';
                    $jsiApprovalBadgeClass = $jsiApproved
                      ? 'bg-green-100 text-green-800 border-green-200'
                      : 'bg-gray-100 text-gray-800 border-gray-200';
                    $jsiApprovalDate = $jsiApproved && $app->jsi_approved_at
                      ? \Carbon\Carbon::parse($app->jsi_approved_at)->format('M d, Y h:i A')
                      : null;
                  @endphp
                  <td class="table-cell px-1 py-1" data-column="jsi-status">
                    <div class="flex flex-col">
                      <span
                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border {{ $jsiCaptureBadgeClass }}">
                        {{ $jsiCaptureLabel }}
                      </span>
                      @if($jsiCaptureDate)
                        <span class="text-[10px] text-gray-500 mt-1">{{ $jsiCaptureDate }}</span>
                      @endif
                    </div>
                  </td>
                  <td class="table-cell px-1 py-1" data-column="jsi-approval">
                    <div class="flex flex-col">
                      <span
                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border {{ $jsiApprovalBadgeClass }}">
                        {{ $jsiApprovalLabel }}
                      </span>
                      @if($jsiApprovalDate)
                        <span class="text-[10px] text-gray-500 mt-1">{{ $jsiApprovalDate }}</span>
                      @endif
                    </div>
                  </td>
                  <td class="table-cell px-1 py-1 truncate" data-column="planning-status">
                    @php
                      $planningStatus = $app->planning_recommendation_status;
                      $app_status = $app->application_status;

                      // Auto-approval logic for PUA
                      if (!$isSua && strtolower($app->mother_planning_status ?? '') === 'approved') {
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
                    <span
                      class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border {{ $planningBadgeClass }}">
                      {{ $planningStatus ?? 'Pending' }}
                    </span>
                  </td>
                  <td class="table-cell px-1 py-1 truncate" data-column="director-status">
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
                    <span
                      class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border {{ $app_badgeClass }}">
                      {{ $app_status ?? 'Pending' }}
                    </span>
                  </td>
                  <td class="table-cell px-1 py-1">
                    @include('sectionaltitling.action_menu.unit_actions', ['app' => $app])
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
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



  <!-- DataTables CSS -->
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
  <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.dataTables.min.css">

  <!-- jQuery and DataTables JS -->
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
  <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
  <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>

  <script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script>
    $(document).ready(function () {
      // Initialize DataTable with proper buttons configuration
      const actionColumnIndex = $('#unitsTable thead th').length - 1;
      window.table = $('#unitsTable').DataTable({
        "pageLength": 25, // Changed from 10 to 25
        "lengthMenu": [[25, 50, 100, 250, -1], [25, 50, 100, 250, "All"]], // Updated length options
        "order": [[10, "desc"]], // Sort by Date Captured column (index 10 - sys_date) in descending order
        "columnDefs": [
          {
            "targets": [actionColumnIndex], // Actions column
            "orderable": false,
            "searchable": false
          }
        ],
        "dom": 'Bfrtip', // Include buttons in DOM
        "language": {
          "info": "Showing _START_ to _END_ of _TOTAL_ applications",
          "infoEmpty": "Showing 0 to 0 of 0 applications",
          "infoFiltered": "(filtered from _MAX_ total applications)",
          "lengthMenu": "Show _MENU_ applications per page",
          "search": "",
          "searchPlaceholder": "Search applications...",
          "paginate": {
            "first": "First",
            "last": "Last",
            "next": "Next",
            "previous": "Previous"
          },
          "emptyTable": "No Parented Units found",
          "zeroRecords": "No matching applications found"
        },
        "responsive": true,
        "processing": true,
        "autoWidth": false,
        "searching": true,
        "buttons": [
          {
            extend: 'excelHtml5',
            text: 'Export to Excel',
            className: 'btn btn-success d-none',
            exportOptions: {
              columns: ':not(:last-child)' // Exclude actions column
            }
          },
          {
            extend: 'csvHtml5',
            text: 'Export to CSV',
            className: 'btn btn-info d-none',
            exportOptions: {
              columns: ':not(:last-child)' // Exclude actions column
            }
          },
          {
            extend: 'pdfHtml5',
            text: 'Export to PDF',
            className: 'btn btn-danger d-none',
            exportOptions: {
              columns: ':not(:last-child)' // Exclude actions column
            },
            orientation: 'landscape',
            pageSize: 'A4'
          }
        ]
      });

      // Add pagination controls after the table
      $('#unitsTable').after(`
                      <div class="flex items-center justify-between mt-4 px-6">
                          <div class="flex-1 flex justify-between sm:hidden">
                              <button onclick="table.page('previous').draw('page')" class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">Previous</button>
                              <button onclick="table.page('next').draw('page')" class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">Next</button>
                          </div>
                          <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                              <div>
                                  <select onchange="table.page.len(this.value).draw();" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                                      <option value="25">25 per page</option>
                                      <option value="50">50 per page</option>
                                      <option value="100">100 per page</option>
                                      <option value="250">250 per page</option>
                                      <option value="-1">Show all</option>
                                  </select>
                              </div>
                              <div class="flex items-center">
                                  <div id="tableInfo" class="text-sm text-gray-700 mr-4"></div>
                                  <div class="flex items-center space-x-2" id="pagination"></div>
                              </div>
                          </div>
                      </div>
                  `);

      // Update info and pagination
      table.on('draw', function () {


        let paginationHtml = '';
        let info = table.page.info();

        // Previous button
        paginationHtml += `<button onclick="table.page('previous').draw('page')" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50" ${info.page === 0 ? 'disabled' : ''}>Previous</button>`;

        // Page numbers
        for (let i = 0; i < info.pages; i++) {
          if (i === info.page) {
            paginationHtml += `<button class="relative inline-flex items-center px-4 py-2 border border-blue-500 bg-blue-50 text-sm font-medium text-blue-600">${i + 1}</button>`;
          } else {
            paginationHtml += `<button onclick="table.page(${i}).draw('page')" class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">${i + 1}</button>`;
          }
        }

        // Next button
        paginationHtml += `<button onclick="table.page('next').draw('page')" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50" ${info.page === info.pages - 1 ? 'disabled' : ''}>Next</button>`;

        $('#pagination').html(paginationHtml);
      });

      // Show pagination on initial load
      table.draw();

      // Hide default DataTables controls
      $('.dataTables_filter').hide();
      $('.dataTables_length').hide();
      $('.dataTables_info').hide();
      $('.dataTables_paginate').hide();
      $('.dt-buttons').hide(); // Hide the default buttons

      // Connect custom search input
      $('#globalSearch').on('keyup', function () {
        table.search(this.value).draw();
      });

      // Add visual feedback to search
      $('#globalSearch').on('keyup', function () {
        const searchTerm = this.value;
        if (searchTerm.length > 0) {
          $(this).addClass('border-blue-500 ring-1 ring-blue-500');
        } else {
          $(this).removeClass('border-blue-500 ring-1 ring-blue-500');
        }
      });

      // Filter functionality
      $('#filterBtn').on('click', function () {
        Swal.fire({
          title: 'Filter Options',
          html: `
                              <div class="text-left space-y-4">
                                  <div>
                                      <label class="block text-sm font-medium text-gray-700 mb-2">Filter by Land Use:</label>
                                      <select id="landUseFilter" class="w-full p-2 border border-gray-300 rounded-md">
                                          <option value="">All Land Uses</option>
                                          <option value="Residential">Residential</option>
                                          <option value="Commercial">Commercial</option>
                                          <option value="Industrial">Industrial</option>
                                          <option value="Mixed">Mixed</option>
                                      </select>
                                  </div>
                                  <div>
                                      <label class="block text-sm font-medium text-gray-700 mb-2">Filter by Owner Type:</label>
                                      <select id="ownerTypeFilter" class="w-full p-2 border border-gray-300 rounded-md">
                                          <option value="">All Owner Types</option>
                                          <option value="Individual">Individual</option>
                                          <option value="Corporate">Corporate</option>
                                          <option value="Multiple">Multiple Owners</option>
                                      </select>
                                  </div>
                              </div>
                          `,
          showCancelButton: true,
          confirmButtonText: 'Apply Filter',
          cancelButtonText: 'Clear Filter',
          preConfirm: () => {
            const landUse = document.getElementById('landUseFilter').value;
            const ownerType = document.getElementById('ownerTypeFilter').value;
            return { landUse, ownerType };
          }
        }).then((result) => {
          if (result.isConfirmed) {
            // Apply filters
            let searchTerm = '';
            if (result.value.landUse) {
              searchTerm += result.value.landUse + ' ';
            }
            if (result.value.ownerType) {
              searchTerm += result.value.ownerType + ' ';
            }
            table.search(searchTerm.trim()).draw();
          } else if (result.dismiss === Swal.DismissReason.cancel) {
            // Clear filter
            table.search('').draw();
            $('#globalSearch').val('');
          }
        });
      });

      // Custom export button functionality
      $('#exportBtn').on('click', function (e) {
        e.preventDefault();
        e.stopPropagation();

        // Remove any existing menu
        $('.export-menu').remove();

        // Create export menu
        var exportMenu = $(`
                          <div class="export-menu" style="position: fixed; background: white; border: 1px solid #e5e7eb; border-radius: 8px; padding: 8px; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); z-index: 9999; min-width: 160px;">
                              <button onclick="exportToExcel()" class="block w-full text-left px-3 py-2 hover:bg-gray-50 rounded flex items-center text-sm">
                                  <svg class="w-4 h-4 mr-2 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                  </svg>
                                  Export to Excel
                              </button>
                              <button onclick="exportToCSV()" class="block w-full text-left px-3 py-2 hover:bg-gray-50 rounded flex items-center text-sm">
                                  <svg class="w-4 h-4 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                  </svg>
                                  Export to CSV
                              </button>
                              <button onclick="exportToPDF()" class="block w-full text-left px-3 py-2 hover:bg-gray-50 rounded flex items-center text-sm">
                                  <svg class="w-4 h-4 mr-2 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                  </svg>
                                  Export to PDF
                              </button>
                          </div>
                      `);

        // Add menu to body
        $('body').append(exportMenu);

        // Position menu relative to button
        var buttonOffset = $(this).offset();
        var buttonHeight = $(this).outerHeight();

        exportMenu.css({
          'top': buttonOffset.top + buttonHeight + 5,
          'left': buttonOffset.left
        });

        // Close menu when clicking outside
        $(document).on('click.exportMenu', function (e) {
          if (!$(e.target).closest('.export-menu, #exportBtn').length) {
            $('.export-menu').remove();
            $(document).off('click.exportMenu');
          }
        });
      });

      // Export functions
      window.exportToExcel = function () {
        table.button(0).trigger(); // Trigger first button (Excel)
        $('.export-menu').remove();
        $(document).off('click.exportMenu');
      };

      window.exportToCSV = function () {
        table.button(1).trigger(); // Trigger second button (CSV)
        $('.export-menu').remove();
        $(document).off('click.exportMenu');
      };

      window.exportToPDF = function () {
        table.button(2).trigger(); // Trigger third button (PDF)
        $('.export-menu').remove();
        $(document).off('click.exportMenu');
      };
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

    window.showMultipleOwners = function (names, passports) {
      if (!Array.isArray(names)) {
        names = [];
      }
      if (!Array.isArray(passports)) {
        passports = [];
      }

      let html = '<div class="space-y-2">';
      names.forEach((name, index) => {
        html += `<div class="flex items-center space-x-2">`;
        if (passports[index]) {
          html += `<img src="{{ asset('storage/') }}${passports[index]}" alt="Passport" class="w-8 h-8 rounded-full object-cover">`;
        }
        html += `<span>${name}</span></div>`;
      });
      html += '</div>';

      Swal.fire({
        title: 'Multiple Owners',
        html: html,
        icon: 'info',
        confirmButtonText: 'Close',
        width: '500px'
      });
    }

    window.showPassportPreview = function (imageSrc, title) {
      Swal.fire({
        title: title,
        imageUrl: imageSrc,
        imageWidth: 300,
        imageHeight: 400,
        imageAlt: title,
        confirmButtonText: 'Close'
      });
    }
  </script>

  <style>
    /* Custom DataTables styling to match your design */
    .dataTables_wrapper {
      font-family: inherit;
    }

    .dataTables_length select,
    .dataTables_filter input {
      border: 1px solid #d1d5db;
      border-radius: 0.375rem;
      padding: 0.5rem;
      font-size: 0.875rem;
    }

    .dataTables_info {
      color: #6b7280;
      font-size: 0.875rem;
    }

    .dataTables_paginate .paginate_button {
      border: 1px solid #d1d5db;
      border-radius: 0.375rem;
      padding: 0.25rem 0.75rem;
      margin: 0 0.125rem;
      color: #374151;
      text-decoration: none;
    }

    .dataTables_paginate .paginate_button:hover {
      background-color: #f3f4f6;
      border-color: #9ca3af;
    }

    .dataTables_paginate .paginate_button.current {
      background-color: #3b82f6;
      border-color: #3b82f6;
      color: white;
    }

    .dataTables_paginate .paginate_button.disabled {
      color: #9ca3af;
      cursor: not-allowed;
    }

    .dataTables_paginate .paginate_button.disabled:hover {
      background-color: transparent;
      border-color: #d1d5db;
    }

    /* Hide default buttons container */
    .dt-buttons {
      display: none;
    }

    .export-menu button {
      border: none;
      background: none;
      cursor: pointer;
      border-radius: 4px;
    }
  </style>

@endsection