@extends('layouts.app')

@section('page-title')
    {{ $PageTitle }}
@endsection

@section('content')
    <div class="flex-1 overflow-auto">
        @include('admin.header')
    @include('exports.consolidated_report_action', ['reportKey' => 'st-applications'])

        <div class="p-6">
            <div class="bg-white rounded-md shadow-sm border border-gray-200">
                <div class="p-6">
                    <!-- Header -->
                    <div class="flex justify-between items-center mb-6">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-800">Standalone Unit Applications (SUA)</h1>
                            <p class="text-gray-600">Manage standalone unit applications without mother applications</p>
                        </div>
                        <div class="flex space-x-3">
                            <!-- Create SUA Button with Dropdown -->
                            <div class="relative" x-data="{ open: false }">
                                <button @click="open = !open" @click.away="open = false"
                                    class="flex items-center space-x-2 px-4 py-2 bg-gray-900 text-white rounded-md">
                                    <i data-lucide="file-plus" class="w-4 h-4"></i>
                                    <span>Create SUA</span>
                                    <i data-lucide="chevron-down" class="w-4 h-4 ml-2"></i>
                                </button>

                                <!-- Dropdown Menu -->
                                <div x-show="open" x-cloak style="display: none;"
                                    x-transition:enter="transition ease-out duration-100"
                                    x-transition:enter-start="transform opacity-0 scale-95"
                                    x-transition:enter-end="transform opacity-100 scale-100"
                                    x-transition:leave="transition ease-in duration-75"
                                    x-transition:leave-start="transform opacity-100 scale-100"
                                    x-transition:leave-end="transform opacity-0 scale-95"
                                    class="absolute right-0 mt-2 w-56 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-50">
                                    <div class="py-1" role="menu">


                                        <a href="{{ url('/sua/create?landuse=Residential') }}"
                                            class="group flex items-center px-4 py-3 text-sm text-gray-700 hover:bg-gray-50 hover:text-gray-900">
                                            <div class="p-1.5 bg-green-100 rounded-md mr-3 group-hover:bg-green-200">
                                                <i data-lucide="home" class="w-4 h-4 text-green-600"></i>
                                            </div>
                                            <div>
                                                <div class="font-medium">Residential</div>

                                            </div>
                                        </a>

                                        <a href="{{ url('/sua/create?landuse=Commercial') }}"
                                            class="group flex items-center px-4 py-3 text-sm text-gray-700 hover:bg-gray-50 hover:text-gray-900">
                                            <div class="p-1.5 bg-blue-100 rounded-md mr-3 group-hover:bg-blue-200">
                                                <i data-lucide="building-2" class="w-4 h-4 text-blue-600"></i>
                                            </div>
                                            <div>
                                                <div class="font-medium">Commercial</div>

                                            </div>
                                        </a>

                                        <a href="{{ url('/sua/create?landuse=Industrial') }}"
                                            class="group flex items-center px-4 py-3 text-sm text-gray-700 hover:bg-gray-50 hover:text-gray-900">
                                            <div class="p-1.5 bg-yellow-100 rounded-md mr-3 group-hover:bg-yellow-200">
                                                <i data-lucide="factory" class="w-4 h-4 text-yellow-600"></i>
                                            </div>
                                            <div>
                                                <div class="font-medium">Industrial</div>

                                            </div>
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <button onclick="exportSUA()"
                                class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 flex items-center">
                                <i data-lucide="download" class="w-4 h-4 mr-2"></i>
                                Export
                            </button>
                        </div>
                    </div>

                    <!-- Stats Cards -->
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                            <div class="flex items-center">
                                <div class="p-2 bg-blue-100 rounded-lg">
                                    <i data-lucide="file-text" class="w-6 h-6 text-blue-600"></i>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm font-medium text-blue-600">Total SUA</p>
                                    <p class="text-2xl font-bold text-blue-900" id="total-sua">{{ $stats['total'] ?? 0 }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                            <div class="flex items-center">
                                <div class="p-2 bg-green-100 rounded-lg">
                                    <i data-lucide="check-circle" class="w-6 h-6 text-green-600"></i>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm font-medium text-green-600">Approved</p>
                                    <p class="text-2xl font-bold text-green-900">{{ $stats['approved'] ?? 0 }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                            <div class="flex items-center">
                                <div class="p-2 bg-yellow-100 rounded-lg">
                                    <i data-lucide="clock" class="w-6 h-6 text-yellow-600"></i>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm font-medium text-yellow-600">Pending</p>
                                    <p class="text-2xl font-bold text-yellow-900">{{ $stats['pending'] ?? 0 }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                            <div class="flex items-center">
                                <div class="p-2 bg-red-100 rounded-lg">
                                    <i data-lucide="x-circle" class="w-6 h-6 text-red-600"></i>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm font-medium text-red-600">Rejected</p>
                                    <p class="text-2xl font-bold text-red-900">{{ $stats['rejected'] ?? 0 }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Filters -->
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Filter by Status</label>
                            <select id="statusFilter" class="w-full p-2 border border-gray-300 rounded-md"
                                onchange="filterSUA()">
                                <option value="">All Status</option>
                                <option value="Pending">Pending</option>
                                <option value="Approved">Approved</option>
                                <option value="Rejected">Rejected</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Filter by Land Use</label>
                            <select id="landUseFilter" class="w-full p-2 border border-gray-300 rounded-md"
                                onchange="filterSUA()">
                                <option value="">All Land Use</option>
                                <option value="Residential">Residential</option>
                                <option value="Commercial">Commercial</option>
                                <option value="Industrial">Industrial</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Filter by Allocation Source</label>
                            <select id="allocationFilter" class="w-full p-2 border border-gray-300 rounded-md"
                                onchange="filterSUA()">
                                <option value="">All Sources</option>
                                <option value="State Government">State Government</option>
                                <option value="Local Government">Local Government</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                            <input type="text" id="searchFilter" placeholder="Search by file no or name..."
                                class="w-full p-2 border border-gray-300 rounded-md" onkeyup="filterSUA()">
                        </div>
                    </div>

                    <!-- SUA Applications Table -->
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Scheme No</th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        NP FileNo</th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Unit FileNo</th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Land Use</th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Original Owner</th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Unit Owner</th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Unit No</th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Phone Number</th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Application date</th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Date Captured</th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Created By</th>

                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                                        data-column="jsi-status">
                                        JSI Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                                        data-column="jsi-approval">
                                        JSI Approval</th>

                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Planning Recommendation</th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Director's Approval</th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Actions</th>
                                </tr>
                            </thead>
                            <tbody id="suaTableBody" class="bg-white divide-y divide-gray-200">
                                @forelse($suaApplications as $sua)
                                                        <tr class="hover:bg-gray-50">
                                                            <!-- Scheme No -->
                                                            <td class="px-6 py-4 whitespace-nowrap">
                                                                <div class="text-sm font-medium text-gray-900">{{ $sua->scheme_no ?? 'N/A' }}
                                                                </div>
                                                            </td>

                                                            <!-- NP FileNo (Primary FileNo) -->
                                                            <td class="px-6 py-4 whitespace-nowrap" data-column="np-fileno">
                                                                <div class="text-sm font-medium text-gray-900">{{ $sua->np_fileno }}</div>
                                                                {{-- @if ($sua->mls_fileno && $sua->mls_fileno !== $sua->np_fileno)
                                                                <div class="text-xs text-gray-500">MLPP: {{ $sua->mls_fileno }}</div>
                                                                @endif --}}
                                                            </td>

                                                            <!-- Unit FileNo -->
                                                            <td class="px-6 py-4 whitespace-nowrap">
                                                                <div class="text-sm text-gray-900">{{ $sua->fileno ?? '-' }}</div>
                                                            </td>

                                                            <!-- Land Use -->
                                                            <td class="px-6 py-4 whitespace-nowrap" data-column="land-use">
                                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                                        {{ $sua->land_use === 'Residential'
                                    ? 'bg-green-100 text-green-800'
                                    : ($sua->land_use === 'Commercial'
                                        ? 'bg-blue-100 text-blue-800'
                                        : 'bg-yellow-100 text-yellow-800') }}">
                                                                    {{ $sua->land_use }}
                                                                </span>
                                                            </td>

                                                            <!-- Original Owner (Allocation Source / Allocation Entity) -->
                                                            <td class="px-6 py-4 whitespace-nowrap" data-column="allocation-source">
                                                                <div class="text-sm text-gray-900">{{ $sua->allocation_source }}</div>
                                                                <div class="text-xs text-gray-500">{{ $sua->allocation_entity }}</div>
                                                            </td>

                                                            <!-- Unit Owner -->
                                                            <td class="px-6 py-4 whitespace-nowrap" data-column="unit-owner">
                                                                <div class="flex items-center space-x-3">
                                                                    @php
                                                                        $passportFile = null;
                                                                        if (
                                                                            $sua->applicant_type === 'multiple' &&
                                                                            $sua->multiple_owners_passport
                                                                        ) {
                                                                            $passportFile = $sua->multiple_owners_passport;
                                                                        } elseif (
                                                                            in_array($sua->applicant_type, ['individual', 'corporate']) &&
                                                                            $sua->passport
                                                                        ) {
                                                                            $passportFile = $sua->passport;
                                                                        }
                                                                    @endphp

                                                                    <!-- Passport/Image -->
                                                                    <div class="flex-shrink-0">
                                                                        @if ($passportFile)
                                                                            @php
                                                                                $imgUrl = filter_var($passportFile, FILTER_VALIDATE_URL)
                                                                                    ? $passportFile
                                                                                    : asset(
                                                                                        'storage/app/public/' . ltrim($passportFile, '/'),
                                                                                    );
                                                                            @endphp
                                                                            <a href="{{ $imgUrl }}" target="_blank" class="block">
                                                                                <img src="{{ $imgUrl }}" alt="Applicant Photo"
                                                                                    class="w-8 h-8 rounded-full object-cover border" />
                                                                            </a>
                                                                        @else
                                                                            <div
                                                                                class="flex items-center justify-center w-8 h-8 bg-gray-200 rounded-full">
                                                                                <i class="fas fa-user text-gray-400 text-xs"></i>
                                                                            </div>
                                                                        @endif
                                                                    </div>

                                                                    <!-- Name and Details -->
                                                                    <div class="text-sm text-gray-900">
                                                                        @if (isset($sua->applicant_type) && $sua->applicant_type == 'corporate' && !empty($sua->corporate_name))
                                                                            {{ $sua->corporate_name }}
                                                                            @if (!empty($sua->rc_number))
                                                                                <div class="text-xs text-gray-500">RC: {{ $sua->rc_number }}
                                                                                </div>
                                                                            @endif
                                                                        @elseif(isset($sua->applicant_type) && $sua->applicant_type == 'multiple' && !empty($sua->multiple_owners_names))
                                                                            @php
                                                                                $names = is_string($sua->multiple_owners_names)
                                                                                    ? json_decode($sua->multiple_owners_names, true)
                                                                                    : $sua->multiple_owners_names;
                                                                            @endphp
                                                                            @if (is_array($names) && count($names) > 0)
                                                                                {{ $names[0] }}
                                                                                @if (count($names) > 1)
                                                                                    <div class="text-xs text-blue-600">
                                                                                        +{{ count($names) - 1 }} more owners</div>
                                                                                @endif
                                                                            @else
                                                                                Multiple Owners
                                                                            @endif
                                                                        @elseif(isset($sua->applicant_type) && $sua->applicant_type == 'individual')
                                                                            @php
                                                                                $fullName = trim(
                                                                                    ($sua->applicant_title ?? '') .
                                                                                    ' ' .
                                                                                    ($sua->first_name ?? '') .
                                                                                    ' ' .
                                                                                    ($sua->middle_name ?? '') .
                                                                                    ' ' .
                                                                                    ($sua->surname ?? ''),
                                                                                );
                                                                            @endphp
                                                                            {{ $fullName ?: 'Individual Applicant' }}
                                                                        @else
                                                                            @php
                                                                                $fallbackName = trim(
                                                                                    ($sua->first_name ?? '') . ' ' . ($sua->surname ?? ''),
                                                                                );
                                                                            @endphp
                                                                            {{ $fallbackName ?: 'SUA Applicant' }}
                                                                        @endif
                                                                        @if ($sua->email)
                                                                            <div class="text-xs text-gray-500">{{ $sua->email }}</div>
                                                                        @endif
                                                                    </div>
                                                                </div>
                                                            </td>

                                                            <!-- Unit No -->
                                                            <td class="px-6 py-4 whitespace-nowrap">
                                                                <div class="text-sm text-gray-900">{{ $sua->unit_number ?? 'N/A' }}</div>

                                                            </td>

                                                            <!-- Phone Number -->
                                                            <td class="px-6 py-4 whitespace-nowrap">
                                                                <div class="text-sm text-gray-900">{{ $sua->phone_number ?? 'N/A' }}</div>
                                                            </td>



                                                            <!-- Application Date -->
                                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                                {{ $sua->created_at ? \Carbon\Carbon::parse($sua->created_at)->format('d/m/Y') : 'N/A' }}
                                                            </td>

                                                            <!-- Date Captured -->
                                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                                @if ($sua->date_captured)
                                                                    {{ \Carbon\Carbon::parse($sua->date_captured)->format('d/m/Y') }}
                                                                @else
                                                                    <span class="text-gray-400">Not captured</span>
                                                                @endif
                                                            </td>

                                                            <!-- Created By -->
                                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                                @php $stCreatedBy = trim((string) ($sua->created_by_name ?? '')); @endphp
                                                                @if($stCreatedBy !== '')
                                                                    <span class="upc-trigger" data-user-card
                                                                          data-user-id="{{ $sua->created_by }}"
                                                                          data-user-name="{{ $stCreatedBy }}"
                                                                          title="{{ __('View profile') }}">{{ $stCreatedBy }}</span>
                                                                @else
                                                                    <span class="text-gray-400">&mdash;</span>
                                                                @endif
                                                            </td>

                                                            <!-- JSI Status -->
                                                            @php
                                                                $jsiApproved = (int) ($sua->jsi_is_approved ?? 0) === 1;
                                                                $jsiSubmitted = (int) ($sua->jsi_is_submitted ?? 0) === 1;
                                                                $jsiGenerated = (int) ($sua->jsi_is_generated ?? 0) === 1;
                                                                $jsiHasRecord = $jsiApproved || $jsiSubmitted || $jsiGenerated;

                                                                $jsiCaptureLabel = $jsiHasRecord ? 'Captured' : 'Not Captured';
                                                                $jsiCaptureBadgeClass = $jsiHasRecord
                                                                    ? 'bg-green-100 text-green-800 border border-green-200'
                                                                    : 'bg-red-100 text-red-800 border border-red-200';
                                                                $jsiCaptureDate = $jsiHasRecord && $sua->jsi_inspection_date
                                                                    ? \Carbon\Carbon::parse($sua->jsi_inspection_date)->format('d/m/Y')
                                                                    : null;

                                                                $jsiApprovalLabel = $jsiApproved ? 'Approved' : 'Pending';
                                                                $jsiApprovalBadgeClass = $jsiApproved
                                                                    ? 'bg-green-100 text-green-800 border border-green-200'
                                                                    : 'bg-gray-100 text-gray-800 border border-gray-200';
                                                                $jsiApprovalDate = $jsiApproved && $sua->jsi_approved_at
                                                                    ? \Carbon\Carbon::parse($sua->jsi_approved_at)->format('d/m/Y h:i A')
                                                                    : null;
                                                            @endphp
                                                            <td class="px-6 py-4 whitespace-nowrap" data-column="jsi-status">
                                                                <div class="flex flex-col">
                                                                    <span
                                                                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $jsiCaptureBadgeClass }}">
                                                                        {{ $jsiCaptureLabel }}
                                                                    </span>
                                                                    @if ($jsiCaptureDate)
                                                                        <span class="text-[10px] text-gray-500 mt-1">{{ $jsiCaptureDate }}</span>
                                                                    @endif
                                                                </div>
                                                            </td>
                                                            <!-- JSI Approval -->
                                                            <td class="px-6 py-4 whitespace-nowrap" data-column="jsi-approval">
                                                                <div class="flex flex-col">
                                                                    <span
                                                                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $jsiApprovalBadgeClass }}">
                                                                        {{ $jsiApprovalLabel }}
                                                                    </span>
                                                                    @if ($jsiApprovalDate)
                                                                        <span class="text-[10px] text-gray-500 mt-1">{{ $jsiApprovalDate }}</span>
                                                                    @endif
                                                                </div>
                                                            </td>

                                                            <!-- Planning Recommendation -->
                                                            <td class="px-6 py-4 whitespace-nowrap" data-column="planning-status">
                                                                <div class="flex flex-col">
                                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                                            {{ $sua->planning_recommendation_status === 'Approved'
                                    ? 'bg-green-100 text-green-800'
                                    : ($sua->planning_recommendation_status === 'Rejected'
                                        ? 'bg-red-100 text-red-800'
                                        : 'bg-yellow-100 text-yellow-800') }}">
                                                                        {{ $sua->planning_recommendation_status ?? 'Pending' }}
                                                                    </span>
                                                                    <span class="text-[10px] text-gray-500 mt-1">
                                                                        @if(!empty($sua->planning_approval_date))
                                                                            {{ \Carbon\Carbon::parse($sua->planning_approval_date)->format('Y-m-d') }}
                                                                        @else
                                                                            --
                                                                        @endif
                                                                    </span>
                                                                </div>
                                                            </td>

                                                            <!-- Director's Approval -->
                                                            <td class="px-6 py-4 whitespace-nowrap" data-column="director-status">
                                                                <div class="flex flex-col">
                                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                                            {{ $sua->application_status === 'Approved'
                                    ? 'bg-green-100 text-green-800'
                                    : ($sua->application_status === 'Rejected'
                                        ? 'bg-red-100 text-red-800'
                                        : 'bg-yellow-100 text-yellow-800') }}">
                                                                        {{ $sua->application_status }}
                                                                    </span>
                                                                    <span class="text-[10px] text-gray-500 mt-1">
                                                                        @if(!empty($sua->approval_date))
                                                                            {{ \Carbon\Carbon::parse($sua->approval_date)->format('Y-m-d') }}
                                                                        @else
                                                                            --
                                                                        @endif
                                                                    </span>
                                                                </div>
                                                            </td>

                                                            <!-- Actions -->
                                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                                @include('sua.action_menu', ['sua' => $sua])
                                                            </td>
                                                        </tr>
                                @empty
                                    <tr>
                                        <td colspan="13" class="px-6 py-8 text-center">
                                            <div class="flex flex-col items-center">
                                                <i data-lucide="file-x" class="w-12 h-12 text-gray-400 mb-4"></i>
                                                <h3 class="text-lg font-medium text-gray-900 mb-2">No SUA Applications
                                                    Found</h3>
                                                <p class="text-gray-500 mb-4">Get started by creating your first Standalone
                                                    Unit Application.</p>

                                                <!-- Create First SUA Dropdown -->
                                                <div class="relative" x-data="{ open: false }">
                                                    <button @click="open = !open" @click.away="open = false"
                                                        class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 flex items-center">
                                                        Create First SUA
                                                        <i data-lucide="chevron-down" class="w-4 h-4 ml-2"></i>
                                                    </button>

                                                    <!-- Dropdown Menu -->
                                                    <div x-show="open" x-cloak style="display: none;"
                                                        x-transition:enter="transition ease-out duration-100"
                                                        x-transition:enter-start="transform opacity-0 scale-95"
                                                        x-transition:enter-end="transform opacity-100 scale-100"
                                                        x-transition:leave="transition ease-in duration-75"
                                                        x-transition:leave-start="transform opacity-100 scale-100"
                                                        x-transition:leave-end="transform opacity-0 scale-95"
                                                        class="absolute left-1/2 transform -translate-x-1/2 mt-2 w-56 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-50">
                                                        <div class="py-1" role="menu">


                                                            <a href="{{ url('/sua/create?landuse=Residential') }}"
                                                                class="group flex items-center px-4 py-3 text-sm text-gray-700 hover:bg-gray-50 hover:text-gray-900">
                                                                <div
                                                                    class="p-1.5 bg-green-100 rounded-md mr-3 group-hover:bg-green-200">
                                                                    <i data-lucide="home" class="w-4 h-4 text-green-600"></i>
                                                                </div>
                                                                <div>
                                                                    <div class="font-medium">Residential</div>
                                                                    <div class="text-xs text-gray-500">Apartment blocks,
                                                                        residential units</div>
                                                                </div>
                                                            </a>

                                                            <a href="{{ url('/sua/create?landuse=Commercial') }}"
                                                                class="group flex items-center px-4 py-3 text-sm text-gray-700 hover:bg-gray-50 hover:text-gray-900">
                                                                <div
                                                                    class="p-1.5 bg-blue-100 rounded-md mr-3 group-hover:bg-blue-200">
                                                                    <i data-lucide="building-2"
                                                                        class="w-4 h-4 text-blue-600"></i>
                                                                </div>
                                                                <div>
                                                                    <div class="font-medium">Commercial</div>
                                                                    <div class="text-xs text-gray-500">Shops, offices,
                                                                        commercial spaces</div>
                                                                </div>
                                                            </a>

                                                            <a href="{{ url('/sua/create?landuse=Industrial') }}"
                                                                class="group flex items-center px-4 py-3 text-sm text-gray-700 hover:bg-gray-50 hover:text-gray-900">
                                                                <div
                                                                    class="p-1.5 bg-yellow-100 rounded-md mr-3 group-hover:bg-yellow-200">
                                                                    <i data-lucide="factory"
                                                                        class="w-4 h-4 text-yellow-600"></i>
                                                                </div>
                                                                <div>
                                                                    <div class="font-medium">Industrial</div>
                                                                    <div class="text-xs text-gray-500">Factories,
                                                                        warehouses, industrial units</div>
                                                                </div>
                                                            </a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    @if ($suaApplications->hasPages())
                        <div class="px-6 py-3 border-t border-gray-200">
                            {{ $suaApplications->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Initialize Lucide icons
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        });

        // Filter functionality
        function filterSUA() {
            const status = document.getElementById('statusFilter').value;
            const landUse = document.getElementById('landUseFilter').value;
            const allocation = document.getElementById('allocationFilter').value;
            const search = document.getElementById('searchFilter').value.toLowerCase();

            const rows = document.querySelectorAll('#suaTableBody tr');

            rows.forEach(row => {
                // Skip if this is the empty state row
                if (row.querySelector('td[colspan]')) {
                    return;
                }

                const directorApprovalCell = row.querySelector('[data-column="director-status"] span');
                const landUseCell = row.querySelector('[data-column="land-use"] span');
                const allocationCell = row.querySelector('[data-column="allocation-source"]');
                const npFileNoCell = row.querySelector('[data-column="np-fileno"]');
                const unitOwnerCell = row.querySelector('[data-column="unit-owner"]');

                if (!directorApprovalCell || !landUseCell || !allocationCell || !npFileNoCell || !unitOwnerCell) {
                    return;
                }

                const statusMatch = !status || directorApprovalCell.textContent.trim() === status;
                const landUseMatch = !landUse || landUseCell.textContent.trim() === landUse;
                const allocationMatch = !allocation || allocationCell.textContent.trim() === allocation;
                const searchMatch = !search ||
                    npFileNoCell.textContent.toLowerCase().includes(search) ||
                    unitOwnerCell.textContent.toLowerCase().includes(search);

                if (statusMatch && landUseMatch && allocationMatch && searchMatch) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        // Export functionality
        function exportSUA() {
            // Implement export logic here
            alert('Export functionality to be implemented');
        }

        // Handle SweetAlert messages from session
        @if (session('swal_success') && session('success'))
            document.addEventListener('DOMContentLoaded', function () {
                Swal.fire({
                    title: 'Success!',
                    text: '{{ session('success') }}',
                    icon: 'success',
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#10B981',
                    showClass: {
                        popup: 'animate__animated animate__fadeInDown'
                    },
                    hideClass: {
                        popup: 'animate__animated animate__fadeOutUp'
                    }
                });
            });
        @endif

        @if (session('swal_error') && session('error'))
            document.addEventListener('DOMContentLoaded', function () {
                Swal.fire({
                    title: 'Error!',
                    text: '{{ session('error') }}',
                    icon: 'error',
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#EF4444'
                });
            });
        @endif
    </script>

    <!-- SweetAlert2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.min.css">
    <!-- Animate.css for SweetAlert animations -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>

@endsection