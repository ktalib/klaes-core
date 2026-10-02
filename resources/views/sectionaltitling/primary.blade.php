@extends('layouts.app')
@php
    use Illuminate\Support\Str;
@endphp
@section('page-title')
    {{$PageTitle}}
@endsection

{{-- Add DataTables CSS --}}
@push('styles')
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.2.2/css/buttons.dataTables.min.css">
@endpush


@include('sectionaltitling.partials.assets.css')
@section('content')
    <!-- Main Content -->
    <div class="flex-1 overflow-auto">
        <!-- Header -->
        @include('admin.header')
        <!-- Dashboard Content -->
        <div class="p-6">


            <!-- Primary Applications Overview - Screenshot 129 -->

            @if(!request()->has('survey') && (!request()->has('url') || (request()->get('url') !== 'phy_planning' && request()->get('url') !== 'recommendation')))
                @include('sectionaltitling.partials.statistic.PrimaryApplications')
            @endif

            <!-- Primary Applications Table -->
            <div class="bg-white rounded-md shadow-sm border border-gray-200 p-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-xl font-bold">Primary Applications</h2>

                    <div class="flex items-center space-x-4">

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
                            $routeUrl = route('sectionaltitling.secondary', $query);
                        @endphp

                        @if($isSurveyOrSpecialUrl)
                            <a href="{{ $routeUrl }}"
                                class="flex items-center space-x-2 px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">
                                <i data-lucide="clipboard-list" class="w-4 h-4"></i>
                                <span>View Secondary Applications</span>
                            </a>
                        @endif

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
                        {{-- <button
                            style="display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1rem; background-color: #fff8f1; border: 2px solid #f97316; border-radius: 0.375rem; cursor: pointer; transition: background-color 0.2s ease;">
                            <i data-lucide="upload" style="width: 1rem; height: 1rem; color: #ea580c;"></i>
                            <span style="font-weight: 500; color: #ea580c;">Import Field Data</span>
                        </button> --}}

                        <style>
                            button:hover {
                                background-color: #fed7aa;
                            }
                        </style>

                        {{-- Replace the export button with export dropdown --}}
                        <div class="relative inline-block">
                            <button onclick="toggleExportDropdown(event)"
                                class="flex items-center space-x-2 px-4 py-2 border border-gray-200 rounded-md">
                                <i data-lucide="download" class="w-4 h-4 text-gray-600"></i>
                                <span>Export</span>
                                <i data-lucide="chevron-down" class="w-4 h-4 ml-2"></i>
                            </button>
                            <div id="exportDropdown"
                                class="hidden absolute right-0 mt-2 w-48 bg-white border border-gray-200 rounded-md shadow-lg z-50">
                                <button onclick="exportTable('excel')"
                                    class="w-full px-4 py-2 text-left hover:bg-gray-100 flex items-center">
                                    <i data-lucide="file-text" class="w-4 h-4 mr-2 text-green-600"></i>
                                    Export to Excel
                                </button>
                                <button onclick="exportTable('csv')"
                                    class="w-full px-4 py-2 text-left hover:bg-gray-100 flex items-center">
                                    <i data-lucide="file" class="w-4 h-4 mr-2 text-blue-600"></i>
                                    Export to CSV
                                </button>
                                <button onclick="exportTable('pdf')"
                                    class="w-full px-4 py-2 text-left hover:bg-gray-100 flex items-center">
                                    <i data-lucide="file-text" class="w-4 h-4 mr-2 text-red-600"></i>
                                    Export to PDF
                                </button>
                            </div>
                        </div>
                        @if(
                                !request()->has('survey') &&
                                (
                                    !request()->has('url') ||
                                    (
                                        request()->get('url') !== 'phy_planning' &&
                                        request()->get('url') !== 'recommendation'

                                    )

                                )
                            )
                            @if(request()->has('url') && request()->get('url') === 'infopro')
                                <div class="relative z-10">
                                    <button type="button" onclick="toggleDropdown(event)"
                                        class="flex items-center space-x-2 px-4 py-2 bg-gray-900 text-white rounded-md">
                                        <i data-lucide="file-plus" class="w-4 h-4"></i>
                                        <span>New Primary Application</span>
                                        <i data-lucide="chevron-down" class="w-4 h-4 ml-2"></i>
                                    </button>
                                    <div
                                        class="absolute mt-2 w-48 bg-white border border-gray-200 rounded-md shadow-lg hidden dropdown-menu">
                                        <a href="{{ route('primaryform.index') }}?landuse=Residential"
                                            class="flex items-center px-4 py-2 hover:bg-gray-100">
                                            <i data-lucide="home" class="w-4 h-4 text-blue-500 mr-2"></i>
                                            Residential
                                        </a>
                                        <a href="{{ route('primaryform.index') }}?landuse=Commercial"
                                            class="flex items-center px-4 py-2 hover:bg-gray-100">
                                            <i data-lucide="briefcase" class="w-4 h-4 text-green-500 mr-2"></i>
                                            Commercial
                                        </a>
                                        <a href="{{ route('primaryform.index') }}?landuse=Industrial"
                                            class="flex items-center px-4 py-2 hover:bg-gray-100">
                                            <i data-lucide="factory" class="w-4 h-4 text-red-500 mr-2"></i>
                                            Industrial
                                        </a>
                                        <a href="{{ route('primaryform.index') }}?landuse=Mixed"
                                            class="flex items-center px-4 py-2 hover:bg-gray-100">
                                            <i data-lucide="layers" class="w-4 h-4 text-purple-500 mr-2"></i>
                                            Mixed
                                        </a>
                                    </div>
                                </div>
                            @endif
                        @endif

                    </div>
                </div>

                <div class="overflow-x-auto">
                    {{-- Replace the existing table structure with DataTables --}}
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr class="text-xs">
                                <th class="table-header text-green-500">ST FileNo</th>
                                <th class="table-header text-green-500">MLSFileNo</th>
                                <th class="table-header text-green-500">Property</th>
                                <th class="table-header text-green-500">Type</th>
                                <th class="table-header text-green-500">Land Use</th>
                                <th class="table-header text-green-500">Owner</th>
                                <th class="table-header text-green-500">Units</th>
                                <th class="table-header text-green-500">Application Date</th>
                                <th class="table-header text-green-500">Date Created</th>
                                <th class="table-header text-green-500">Created By</th>
                                <th class="table-header text-green-500" data-column="jsi-status">JSI Status</th>
                                <th class="table-header text-green-500" data-column="jsi-approval">JSI Approval</th>
                                <th class="table-header text-green-500" data-column="planning-status">Planning
                                    Recommendation </th>
                                @if(!request()->has('survey') && (!request()->has('url') || (request()->get('url') !== 'phy_planning' && request()->get('url') !== 'recommendation')))
                                    <th class="table-header text-green-500" data-column="director-status">Director's Approval
                                    </th>
                                @endif
                                <th class="table-header text-green-500">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach ($PrimaryApplications as $PrimaryApplication)
                                <tr class="text-xs">

                                    <td class="table-cell">
                                        <div class="truncate max-w-[120px]" title="{{ $PrimaryApplication->fileno }}">
                                            {{ $PrimaryApplication->np_fileno }}
                                        </div>
                                    </td>
                                    <td class="table-cell">
                                        <div class="truncate max-w-[120px]" title="{{ $PrimaryApplication->fileno }}">
                                            {{ $PrimaryApplication->fileno }}
                                        </div>
                                    </td>
                                    <td class="table-cell">
                                        @php
                                            $streetLine = collect([
                                                $PrimaryApplication->property_plot_no,
                                                $PrimaryApplication->property_street_name,
                                            ])->filter(fn($value) => filled($value))->implode(' ');

                                            $locationLine = filled($PrimaryApplication->property_lga)
                                                ? trim($PrimaryApplication->property_lga)
                                                : null;

                                            $propertySegments = collect([$streetLine, $locationLine])->filter(fn($value) => filled($value));
                                            $rawPropertyText = $propertySegments->implode(', ');
                                            $normalizedPropertyText = $rawPropertyText !== ''
                                                ? Str::of($rawPropertyText)->lower()->title()
                                                : 'N/A';
                                        @endphp
                                        <div class="truncate max-w-[150px]" title="{{ $normalizedPropertyText }}">
                                            {{ $normalizedPropertyText }}
                                        </div>
                                    </td>
                                    <td class="table-cell">
                                        @php
                                            // The building type is free text typed on the primary form, so the
                                            // column held "Housing Estate" beside "SEMI DETACHED FLATS" and
                                            // "shopping mall and shops" — same kind of value, three casings.
                                            // Normalised for display the way the Property cell above already
                                            // is; the stored value is left untouched.
                                            $buildingType = collect([
                                                $PrimaryApplication->residential_type,
                                                $PrimaryApplication->commercial_type,
                                                $PrimaryApplication->industrial_type,
                                                $PrimaryApplication->mixed_type,
                                            ])->first(fn($value) => filled($value));
                                        @endphp
                                        {{ filled($buildingType) ? Str::of($buildingType)->lower()->title() : 'N/A' }}
                                    </td>
                                    <td class="table-cell">
                                        @if($PrimaryApplication->land_use)
                                            @php
                                                // "Mixed" and "Mixed Use" are the same land use entered two ways.
                                                // Only the longer spelling was listed here, so a "Mixed" scheme
                                                // fell through to the grey default beside its purple twin.
                                                $landUseBadgeClass = match (strtolower(trim($PrimaryApplication->land_use))) {
                                                    'residential' => 'bg-blue-100 text-blue-800 border-blue-200',
                                                    'commercial' => 'bg-green-100 text-green-800 border-green-200',
                                                    'industrial' => 'bg-red-100 text-red-800 border-red-200',
                                                    'mixed', 'mixed use', 'mixed-use' => 'bg-purple-100 text-purple-800 border-purple-200',
                                                    default => 'bg-gray-100 text-gray-800 border-gray-200'
                                                };
                                            @endphp
                                            <span
                                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border {{ $landUseBadgeClass }}">
                                                {{ Str::of($PrimaryApplication->land_use)->lower()->title() }}
                                            </span>
                                        @else
                                            <span
                                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border bg-gray-100 text-gray-800 border-gray-200">
                                                N/A
                                            </span>
                                        @endif
                                    </td>
                                    <td class="table-cell">
                                        <div class="flex items-center">
                                            <div
                                                class="w-10 h-10 rounded-full bg-gray-200 flex items-center justify-center mr-2">
                                                @if ($PrimaryApplication->passport)
                                                    <img src="{{ asset('storage/' . $PrimaryApplication->passport) }}"
                                                        alt="Passport"
                                                        class="w-full h-full rounded-full object-cover cursor-pointer"
                                                        onclick="showPassportPreview('{{ asset('storage/' . $PrimaryApplication->passport) }}', 'Owner Passport')">
                                                @elseif ($PrimaryApplication->multiple_owners_passport)
                                                    @php
                                                        $passports = json_decode($PrimaryApplication->multiple_owners_passport, true);
                                                        $firstPassport = $passports[0] ?? null;
                                                    @endphp
                                                    @if ($firstPassport)
                                                        <img src="{{ asset('storage/' . $firstPassport) }}" alt="Passport"
                                                            class="w-full h-full rounded-full object-cover cursor-pointer"
                                                            onclick="showMultipleOwners({{ $PrimaryApplication->multiple_owners_names }}, {{ $PrimaryApplication->multiple_owners_passport }})">
                                                    @endif
                                                @endif
                                            </div>
                                            <span class="truncate max-w-[120px]">
                                                @if ($PrimaryApplication->corporate_name)
                                                    {{ Str::of($PrimaryApplication->corporate_name)->lower()->title() }}
                                                @elseif($PrimaryApplication->multiple_owners_names)
                                                    @php
                                                        $ownerNames = json_decode(
                                                            $PrimaryApplication->multiple_owners_names,
                                                            true,
                                                        );
                                                        $firstOwner = $ownerNames[0] ?? '-';
                                                        $normalizedFirstOwner = Str::of($firstOwner)->lower()->title();
                                                    @endphp
                                                    {{ $normalizedFirstOwner }}
                                                    <span class="ml-1 cursor-pointer text-blue-500"
                                                        onclick="showMultipleOwners({{ $PrimaryApplication->multiple_owners_names }}, {{ $PrimaryApplication->multiple_owners_passport }})">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 inline" fill="none"
                                                            viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                        </svg>
                                                    </span>
                                                @elseif($PrimaryApplication->first_name || $PrimaryApplication->surname)
                                                    @php
                                                        // Normalize titles to match name formatting (title case)
                                                        $applicantTitle = filled($PrimaryApplication->applicant_title)
                                                            ? Str::of($PrimaryApplication->applicant_title)->lower()->title()
                                                            : null;

                                                        $normalizedName = collect([
                                                            $PrimaryApplication->first_name,
                                                            $PrimaryApplication->middle_name,
                                                            $PrimaryApplication->surname,
                                                        ])
                                                            ->filter(fn($value) => filled($value))
                                                            ->map(fn($value) => Str::of($value)->lower()->title())
                                                            ->implode(' ');

                                                        $displayOwner = trim(($applicantTitle ? $applicantTitle . ' ' : '') . $normalizedName);
                                                    @endphp
                                                    {{ $displayOwner }}
                                                @else
                                                    Unknown Owner
                                                @endif
                                            </span>
                                        </div>

                                    </td>
                                    <td class="table-cell">
                                        @php
                                            $enteredUnitsCount = \DB::connection('sqlsrv')
                                                ->table('subapplications')
                                                ->where('main_application_id', $PrimaryApplication->id)
                                                ->count();
                                            $buyerCount = \DB::connection('sqlsrv')
                                                ->table('buyer_list')
                                                ->where('application_id', $PrimaryApplication->id)
                                                ->count();
                                            $totalUnits = $PrimaryApplication->NoOfUnits ?? 0;
                                            $isFull = ($buyerCount > 0 && $enteredUnitsCount >= $buyerCount);
                                        @endphp
                                        @if($totalUnits > 0)
                                            <span class="badge {{ $isFull ? 'badge-units-full' : 'badge-units' }}">
                                                {{ $enteredUnitsCount }} of {{ $totalUnits }} Units
                                            </span>
                                        @else
                                            <span class="badge badge-primary">0 Units</span>
                                        @endif
                                    </td>
                                    <td class="table-cell"
                                        data-order="{{ $PrimaryApplication->application_date ? $PrimaryApplication->application_date : $PrimaryApplication->sys_date }}">
                                        @if(property_exists($PrimaryApplication, 'application_date') && $PrimaryApplication->application_date)
                                            {{ \Carbon\Carbon::parse($PrimaryApplication->application_date)->format('Y-m-d') }}
                                        @else
                                            {{ \Carbon\Carbon::parse($PrimaryApplication->sys_date)->format('Y-m-d') }}
                                        @endif
                                    </td>
                                    <td class="table-cell" data-order="{{ $PrimaryApplication->sys_date }}">
                                        {{ \Carbon\Carbon::parse($PrimaryApplication->sys_date)->format('Y-m-d') }}
                                    </td>
                                    <td class="table-cell">
                                        @php $stCreatedBy = trim((string) ($PrimaryApplication->created_by_name ?? '')); @endphp
                                        @if($stCreatedBy !== '')
                                            <span class="upc-trigger" data-user-card
                                                  data-user-id="{{ $PrimaryApplication->created_by }}"
                                                  data-user-name="{{ $stCreatedBy }}"
                                                  title="{{ __('View profile') }}">{{ $stCreatedBy }}</span>
                                        @else
                                            <span class="text-gray-400">&mdash;</span>
                                        @endif
                                    </td>
                                    @php
                                        $jsiApproved = (int) ($PrimaryApplication->jsi_is_approved ?? 0) === 1;
                                        $jsiSubmitted = (int) ($PrimaryApplication->jsi_is_submitted ?? 0) === 1;
                                        $jsiGenerated = (int) ($PrimaryApplication->jsi_is_generated ?? 0) === 1;
                                        $jsiRecordExists = $jsiApproved || $jsiSubmitted || $jsiGenerated;

                                        $jsiCaptureLabel = $jsiRecordExists ? 'Captured' : 'Not Captured';
                                        $jsiCaptureBadgeClass = $jsiRecordExists
                                            ? 'bg-green-100 text-green-800 border-green-200'
                                            : 'bg-red-100 text-red-800 border-red-200';
                                        $jsiCaptureDate = $jsiRecordExists && $PrimaryApplication->jsi_inspection_date
                                            ? \Carbon\Carbon::parse($PrimaryApplication->jsi_inspection_date)->format('Y-m-d')
                                            : null;

                                        $jsiApprovalLabel = $jsiApproved ? 'Approved' : 'Pending';
                                        $jsiApprovalBadgeClass = $jsiApproved
                                            ? 'bg-green-100 text-green-800 border-green-200'
                                            : 'bg-gray-100 text-gray-800 border-gray-200';
                                        $jsiApprovalDate = $jsiApproved && $PrimaryApplication->jsi_approved_at
                                            ? \Carbon\Carbon::parse($PrimaryApplication->jsi_approved_at)->format('Y-m-d h:i A')
                                            : null;
                                    @endphp
                                    <td class="table-cell capitalize" data-column="jsi-status">
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
                                    <td class="table-cell capitalize" data-column="jsi-approval">
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
                                    <td class="table-cell capitalize">
                                        <div class="flex flex-col">
                                            <div class="flex items-center">
                                                @php
                                                    $planningStatus = $PrimaryApplication->planning_recommendation_status ?? '';
                                                    $planningBadgeClass = match (strtolower($planningStatus)) {
                                                        'approved' => 'bg-green-100 text-green-800 border-green-200',
                                                        'pending' => 'bg-yellow-100 text-yellow-800 border-yellow-200',
                                                        'declined', 'rejected' => 'bg-red-100 text-red-800 border-red-200',
                                                        'in progress' => 'bg-blue-100 text-blue-800 border-blue-200',
                                                        default => 'bg-gray-100 text-gray-800 border-gray-200'
                                                    };
                                                @endphp
                                                <span
                                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border {{ $planningBadgeClass }}">
                                                    {{ ucfirst(strtolower($planningStatus)) }}
                                                </span>
                                                @if(strtolower($planningStatus) == 'declined')
                                                    <i data-lucide="info" class="w-4 h-4 ml-1 text-blue-500 cursor-pointer"
                                                        onclick="showDeclinedInfo(event, 'Planning Recommendation', {{ json_encode($PrimaryApplication->recomm_comments) }}, {{ json_encode($PrimaryApplication->director_comments) }})"></i>
                                                @endif
                                            </div>
                                            <span class="text-[10px] text-gray-500 mt-1">
                                                @if(!empty($PrimaryApplication->planning_approval_date))
                                                    {{ \Carbon\Carbon::parse($PrimaryApplication->planning_approval_date)->format('Y-m-d') }}
                                                @else
                                                    --
                                                @endif
                                            </span>
                                        </div>
                                    </td>
                                    @if(!request()->has('survey') && (!request()->has('url') || (request()->get('url') !== 'phy_planning' && request()->get('url') !== 'recommendation')))
                                        <td class="table-cell capitalize">
                                            <div class="flex flex-col">
                                                <div class="flex items-center">
                                                    @php
                                                        $directorStatus = $PrimaryApplication->application_status ?? '';
                                                        $directorBadgeClass = match (strtolower($directorStatus)) {
                                                            'approved' => 'bg-green-100 text-green-800 border-green-200',
                                                            'pending' => 'bg-yellow-100 text-yellow-800 border-yellow-200',
                                                            'declined', 'rejected' => 'bg-red-100 text-red-800 border-red-200',
                                                            'in progress' => 'bg-blue-100 text-blue-800 border-blue-200',
                                                            default => 'bg-gray-100 text-gray-800 border-gray-200'
                                                        };
                                                    @endphp
                                                    <span
                                                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border {{ $directorBadgeClass }}">
                                                        {{ ucfirst(strtolower($directorStatus)) }}
                                                    </span>
                                                    @if(strtolower($directorStatus) == 'declined')
                                                        <i data-lucide="info" class="w-4 h-4 ml-1 text-blue-500 cursor-pointer"
                                                            onclick="showDeclinedInfo(event, 'Application Status', {{ json_encode($PrimaryApplication->recomm_comments) }}, {{ json_encode($PrimaryApplication->director_comments) }})"></i>
                                                    @endif
                                                </div>
                                                <span class="text-[10px] text-gray-500 mt-1">
                                                    @if(!empty($PrimaryApplication->approval_date))
                                                        {{ \Carbon\Carbon::parse($PrimaryApplication->approval_date)->format('Y-m-d') }}
                                                    @else
                                                        --
                                                    @endif
                                                </span>
                                            </div>
                                        </td>
                                    @endif

                                    <td class="table-cell overflow-visible relative">
                                        @if(request()->has('url') && request()->get('url') === 'infopro')
                                            @include('sectionaltitling.action_menu.info_pro_action')
                                        @else
                                            @include('sectionaltitling.action_menu.info_pro_action')

                                            {{-- @include('sectionaltitling.action_menu.action') --}}
                                        @endif
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

    @include('sectionaltitling.action_modals.eRegistry_modal')
    @include('sectionaltitling.action_modals.cofo_details_modal')
    @include('sectionaltitling.action_modals.capture_extant_cofo_details')
    @include('sectionaltitling.action_modals.rofo_details_modal')

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Initialize Lucide icons
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        });

        function toggleDropdown(event) {
            event.stopPropagation();
            const dropdownMenu = event.currentTarget.nextElementSibling;
            if (dropdownMenu) {
                dropdownMenu.classList.toggle('hidden');
            }
        }

        document.addEventListener('click', () => {
            const dropdownMenus = document.querySelectorAll('.dropdown-menu');
            dropdownMenus.forEach(menu => menu.classList.add('hidden'));
        });

        function showPassportPreview(imageSrc, title) {
            Swal.fire({
                title: title,
                html: `<img src="${imageSrc}" class="img-fluid" style="max-height: 400px;">`,
                width: 'auto',
                showCloseButton: true,
                showConfirmButton: false
            });
        }

        function showMultipleOwners(owners, passports) {
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

        function showDeclinedInfo(event, title, recommComments, directorComments) {
            event.stopPropagation();

            let htmlContent = '<div class="text-left">';
            if (recommComments) {
                htmlContent += `
                                                                <div class="mb-3">
                                                                    <h3 class="font-bold text-gray-700">Recommendation Comments:</h3>
                                                                    <p class="text-gray-600 mt-1 p-2 bg-gray-100 rounded">${recommComments}</p>
                                                                </div>
                                                            `;
            }

            if (directorComments) {
                htmlContent += `
                                                                <div>
                                                                    <h3 class="font-bold text-gray-700">Director Comments:</h3>
                                                                    <p class="text-gray-600 mt-1 p-2 bg-gray-100 rounded">${directorComments}</p>
                                                                </div>
                                                            `;
            }

            if (!recommComments && !directorComments) {
                htmlContent += '<p>No comments available.</p>';
            }

            htmlContent += '</div>';

            Swal.fire({
                title: `Declined: ${title}`,
                html: htmlContent,
                icon: 'info',
                width: 'auto',
                showCloseButton: true,
                showConfirmButton: true,
                confirmButtonText: 'Close'
            });
        }

    </script>

    {{-- Add DataTables JS dependencies at the end of the file --}}

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.html5.min.js"></script>

    <script>
        $(document).ready(function () {
            // Initialize DataTable
            const table = $('table').DataTable({
                dom: 'Bfrtip',
                pageLength: 10,
                responsive: true,
                buttons: [
                    'excel', 'csv', 'pdf'
                ],
                order: [[8, 'desc']] // Sort by Date Created column (index 8 - sys_date) in descending order
            });

            const planningColumnIndex = $('th[data-column="planning-status"]').index();
            const directorColumnIndex = $('th[data-column="director-status"]').index();
            const statusColumns = [planningColumnIndex, directorColumnIndex].filter(index => index !== -1);

            // Handle status filter
            $('#statusFilter').on('change', function () {
                if (!statusColumns.length) {
                    return;
                }

                const status = $(this).val();
                if (status === 'All...') {
                    statusColumns.forEach(index => table.column(index).search(''));
                    table.draw(); // Clear search on status columns
                } else if (planningColumnIndex !== -1) {
                    // Search within the planning recommendation column
                    table.column(planningColumnIndex).search(status).draw();
                }
            });
        });

        function toggleExportDropdown(event) {
            event.stopPropagation();
            const dropdown = document.getElementById('exportDropdown');
            dropdown.classList.toggle('hidden');
        }

        document.addEventListener('click', () => {
            document.getElementById('exportDropdown').classList.add('hidden');
        });

        function exportTable(type) {
            const table = $('table').DataTable();

            switch (type) {
                case 'excel':
                    table.button('.buttons-excel').trigger();
                    break;
                case 'csv':
                    table.button('.buttons-csv').trigger();
                    break;
                case 'pdf':
                    table.button('.buttons-pdf').trigger();
                    break;
            }

            // Hide the dropdown after export
            document.getElementById('exportDropdown').classList.add('hidden');
        }
    </script>


@endsection