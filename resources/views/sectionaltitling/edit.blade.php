@extends('layouts.app')
@section('page-title')
{{ __('EDIT SECTIONAL TITLING APPLICATION') }}
@endsection

@include('sectionaltitling.partials.assets.css')
@section('content')
<!-- Main Content -->
<div class="flex-1 overflow-auto">
    <!-- Header -->
    @include('admin.header')

    <!-- Dashboard Content -->
    <div class="p-6">
        <div class="bg-white rounded-md shadow-sm border border-gray-200 p-6">
            <div class="container mx-auto mt-4 p-4">
                <div class="card shadow-lg border-0">
                    <div class="card-header bg-gray-50 border-b border-gray-200 py-3 px-4">
                        <div class="flex justify-between items-center">
                            <div>
                                <p class="text-xs uppercase tracking-wide text-gray-500">Sectional Titling</p>
                                <h2 class="text-xl font-bold text-gray-800">Edit Application</h2>
                                <p class="text-sm text-gray-500">Update data that was captured during Steps 1 &ndash; 3 of the primary application form.</p>
                            </div>

                            <div class="flex items-center gap-3">
                                <a href="{{ route('sectionaltitling.viewrecorddetail', ['id' => $application->id]) }}"
                                   class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium py-2 px-4 rounded-md transition duration-150 ease-in-out flex items-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M12.293 15.707a1 1 0 01-1.414 0L5.586 10l5.293-5.707a1 1 0 111.414 1.414L8.414 10l3.879 4.293a1 1 0 010 1.414z" clip-rule="evenodd" />
                                    </svg>
                                    Back to Details
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="card-body p-6">
                        @if(session('success'))
                            <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded">
                                {{ session('success') }}
                            </div>
                        @endif

                        @if(session('error'))
                            <div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded">
                                {{ session('error') }}
                            </div>
                        @endif

                        @if($errors->any())
                            <div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded">
                                <ul class="list-disc list-inside space-y-1">
                                    @foreach($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        @php
                            $sharedAreasSelected = [];
                            if (!empty($application->shared_areas)) {
                                $decodedSharedAreas = is_array($application->shared_areas)
                                    ? $application->shared_areas
                                    : json_decode($application->shared_areas, true);
                                if (is_array($decodedSharedAreas)) {
                                    $sharedAreasSelected = array_values(array_filter(array_map(function ($value) {
                                        return is_string($value) ? trim($value) : null;
                                    }, $decodedSharedAreas)));
                                }
                            }

                            $sharedAreaOptions = [
                                ['value' => 'Hallways', 'label' => 'Hallways', 'icon' => 'door-open'],
                                ['value' => 'Gardens', 'label' => 'Gardens', 'icon' => 'flower'],
                                ['value' => 'Parking Lots', 'label' => 'Parking Lots', 'icon' => 'car'],
                                ['value' => 'Swimming Pool', 'label' => 'Swimming Pool', 'icon' => 'droplets'],
                                ['value' => 'Gym', 'label' => 'Gym / Fitness', 'icon' => 'dumbbell'],
                                ['value' => 'Rooftop Terrace', 'label' => 'Rooftop Terrace', 'icon' => 'mountain'],
                                ['value' => 'Lobby', 'label' => 'Lobby', 'icon' => 'sofa'],
                                ['value' => 'Elevator', 'label' => 'Elevator', 'icon' => 'arrow-up-down'],
                                ['value' => 'Storage Areas', 'label' => 'Storage Areas', 'icon' => 'package'],
                                ['value' => 'Conference Room', 'label' => 'Conference Room', 'icon' => 'users'],
                                ['value' => 'Playground', 'label' => 'Playground', 'icon' => 'sparkles'],
                                ['value' => 'Security Post', 'label' => 'Security Post', 'icon' => 'shield'],
                                ['value' => 'Generator Room', 'label' => 'Generator Room', 'icon' => 'zap'],
                                ['value' => 'Laundry Room', 'label' => 'Laundry Room', 'icon' => 'shirt'],
                                ['value' => 'Community Hall', 'label' => 'Community Hall', 'icon' => 'home'],
                                ['value' => 'Shared Compounds', 'label' => 'Shared Compound', 'icon' => 'layers'],
                                ['value' => 'BBQ Area', 'label' => 'BBQ Area', 'icon' => 'flame'],
                                ['value' => 'Tennis Court', 'label' => 'Tennis Court', 'icon' => 'circle'],
                                ['value' => 'Basketball Court', 'label' => 'Basketball Court', 'icon' => 'circle-dot'],
                                ['value' => 'Jogging Track', 'label' => 'Jogging Track', 'icon' => 'footprints'],
                                ['value' => 'Sauna', 'label' => 'Sauna / Spa', 'icon' => 'waves'],
                                ['value' => 'Library', 'label' => 'Library', 'icon' => 'book-open'],
                                ['value' => 'Cinema Room', 'label' => 'Cinema Room', 'icon' => 'film'],
                                ['value' => 'Game Room', 'label' => 'Game Room', 'icon' => 'gamepad-2'],
                                ['value' => 'Mailroom', 'label' => 'Mailroom', 'icon' => 'mail'],
                                ['value' => 'Bike Storage', 'label' => 'Bike Storage', 'icon' => 'bike'],
                                ['value' => 'Pet Area', 'label' => 'Pet Area', 'icon' => 'paw-print'],
                                ['value' => 'Co-working Space', 'label' => 'Co-working Space', 'icon' => 'briefcase'],
                                ['value' => 'Reception Area', 'label' => 'Reception Area', 'icon' => 'user-round'],
                                ['value' => 'Staircase', 'label' => 'Staircase', 'icon' => 'move-up'],
                            ];

                            $sharedAreaOptionValuesLower = array_map('strtolower', array_column($sharedAreaOptions, 'value'));
                            $customSharedAreas = [];
                            foreach ($sharedAreasSelected as $value) {
                                if (!in_array(strtolower($value), $sharedAreaOptionValuesLower, true)) {
                                    $customSharedAreas[] = $value;
                                }
                            }

                            $documentsPayload = [];
                            if (!empty($application->documents)) {
                                $decodedDocuments = is_array($application->documents)
                                    ? $application->documents
                                    : json_decode($application->documents, true);
                                if (is_array($decodedDocuments)) {
                                    $documentsPayload = $decodedDocuments;
                                }
                            }

                            $documentDefinitions = [
                                'application_letter' => [
                                    'label' => 'Application Letter',
                                    'description' => 'Formal request addressed to the Ministry.',
                                    'icon' => 'file-text',
                                ],
                                'building_plan' => [
                                    'label' => 'Building Plan',
                                    'description' => 'Approved architectural drawings.',
                                    'icon' => 'building',
                                ],
                                'architectural_design' => [
                                    'label' => 'Architectural Design',
                                    'description' => 'Detailed elevations / layouts.',
                                    'icon' => 'drafting-compass',
                                ],
                                'ownership_document' => [
                                    'label' => 'Ownership Document',
                                    'description' => 'Proof of title e.g. CofO, deed.',
                                    'icon' => 'file-check',
                                ],
                                'survey_plan' => [
                                    'label' => 'Survey / Site Plan',
                                    'description' => 'Site boundary and coordinates.',
                                    'icon' => 'map',
                                ],
                            ];

                            $identityDocuments = [
                                'passport' => [
                                    'label' => 'Passport Photo',
                                    'hint' => 'JPEG or PNG, max 5MB',
                                    'accept' => 'image/*',
                                ],
                                'id_document' => [
                                    'label' => 'ID Document',
                                    'hint' => 'PDF, JPG or PNG, max 5MB',
                                    'accept' => '.pdf,.jpg,.jpeg,.png',
                                ],
                                'rc_document' => [
                                    'label' => 'RC / CAC Document',
                                    'hint' => 'Upload CAC certificate for corporate applicants.',
                                    'accept' => '.pdf,.jpg,.jpeg,.png',
                                ],
                            ];

                            $sharedAreasSelectedLower = array_map('strtolower', $sharedAreasSelected);
                        @endphp

                        <!-- Step Indicators -->
                        @php
                            $formatDate = function ($value) {
                                if (empty($value)) {
                                    return null;
                                }
                                try {
                                    return \Illuminate\Support\Carbon::parse($value)->format('Y-m-d');
                                } catch (\Exception $e) {
                                    return $value;
                                }
                            };
                            $stepMeta = [
                                1 => 'Basic Information',
                                2 => 'Shared Areas',
                                3 => 'Documents',
                            ];
                            $stepCount = count($stepMeta);
                        @endphp
                        <div class="mb-8">
                            <div class="flex items-center gap-4 flex-wrap">
                                @foreach ($stepMeta as $stepNumber => $stepLabel)
                                    <button type="button"
                                            class="flex items-center gap-3 focus:outline-none step-nav {{ $stepNumber === 1 ? 'opacity-100' : 'opacity-80 hover:opacity-100' }}"
                                            data-step-target="#step{{ $stepNumber }}Section">
                                        <div data-step-circle
                                             class="w-9 h-9 rounded-full flex items-center justify-center {{ $stepNumber === 1 ? 'bg-emerald-600 text-white' : 'bg-gray-200 text-gray-600' }} font-semibold transition-colors">
                                            {{ $stepNumber }}
                                        </div>
                                        <div>
                                            <p class="text-xs uppercase text-gray-500">Step {{ $stepNumber }}</p>
                                            <p data-step-label class="text-sm font-medium {{ $stepNumber === 1 ? 'text-gray-900' : 'text-gray-600' }}">
                                                {{ $stepLabel }}
                                            </p>
                                        </div>
                                    </button>
                                    @if($stepNumber !== $stepCount)
                                        <div class="flex-1 h-px bg-gray-200 hidden sm:block"></div>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                        <form id="editApplicationForm" method="POST" action="{{ route('sectionaltitling.update', $application->id) }}" enctype="multipart/form-data" class="space-y-10">
                            @csrf
                            @method('PUT')

                            <!-- Step 1 -->
                            <section class="border border-gray-200 rounded-2xl shadow-sm bg-white scroll-mt-24" id="step1Section">
                                <div class="border-b border-gray-100 px-6 py-5 flex flex-col gap-1">
                                    <p class="text-xs uppercase tracking-wide text-emerald-600 font-semibold">Step 1</p>
                                    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                                        <div>
                                            <h3 class="text-lg font-semibold text-gray-900">Applicant &amp; Property Basics</h3>
                                            <p class="text-sm text-gray-500">This section mirrors the Primary Application Form &ldquo;Basic Information&rdquo; step.</p>
                                        </div>
                                        <div class="flex flex-wrap gap-3">
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700">File: {{ $application->np_fileno ?? 'N/A' }}</span>
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-blue-50 text-blue-700">Land Use: {{ strtoupper($application->land_use ?? 'N/A') }}</span>
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-purple-50 text-purple-700">Tracking: {{ $application->tracking_id ?? 'N/A' }}</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="p-6 space-y-6">
                                    <!-- Applicant Information -->
                                    <div class="bg-gray-50 border border-gray-200 rounded-xl p-6">
                                        <div class="flex items-center justify-between mb-4">
                                            <div>
                                                <h4 class="text-base font-semibold text-gray-800">Applicant Information</h4>
                                                <p class="text-sm text-gray-500">Update the data captured in Step 1 of the primary form.</p>
                                            </div>
                                            <span class="text-xs text-gray-500">Applicant Type: {{ ucfirst($application->applicant_type) ?? 'N/A' }}</span>
                                        </div>
                                        <div class="mb-6">
                                            <label class="block text-sm font-medium text-gray-700 mb-2">Applicant Type</label>
                                            <input type="text" name="applicant_type" id="applicant_type" value="{{ old('applicant_type', $application->applicant_type) }}" class="w-full py-2 px-3 border border-gray-300 rounded-md" oninput="toggleApplicantFields()" placeholder="individual | corporate | multiple">
                                            <p class="text-xs text-gray-500 mt-1">Switches the form sections that are shown below.</p>
                                        </div>
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-2">NP File Number</label>
                                                <input type="text" value="{{ $application->np_fileno ?? 'N/A' }}" class="w-full py-2 px-3 border border-gray-200 rounded-md bg-gray-100 text-gray-700" readonly>
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-2">ST File Number</label>
                                                <input type="text" name="fileno" id="fileno" value="{{ old('fileno', $application->fileno) }}" class="w-full py-2 px-3 border border-gray-300 rounded-md">
                                            </div>
                                        </div>
                                        <!-- Individual Fields -->
                                        <div id="individual_fields" class="mt-6 {{ $application->applicant_type == 'individual' ? '' : 'hidden' }}">
                                            <h5 class="text-sm font-semibold text-gray-700 mb-3">Individual Applicant Details</h5>
                                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700 mb-2">Title</label>
                                                    <select name="applicant_title" class="w-full py-2 px-3 border border-gray-300 rounded-md">
                                                        <option value="">Select title</option>
                                                        @foreach(['Mr.', 'Mrs.', 'Chief', 'Master', 'Capt', 'Coln', 'Pastor', 'King', 'Prof', 'Dr.', 'Alhaji', 'Alhaja', 'High Chief', 'Lady', 'Bishop', 'Senator', 'Messr', 'Honorable', 'Miss', 'Rev.', 'Barr.', 'Arc.', 'Sister', 'Other'] as $title)
                                                            <option value="{{ $title }}" {{ $application->applicant_title == $title ? 'selected' : '' }}>{{ $title }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700 mb-2">First Name</label>
                                                    <input type="text" name="first_name" value="{{ old('first_name', $application->first_name) }}" class="w-full py-2 px-3 border border-gray-300 rounded-md">
                                                </div>
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700 mb-2">Middle Name</label>
                                                    <input type="text" name="middle_name" value="{{ old('middle_name', $application->middle_name) }}" class="w-full py-2 px-3 border border-gray-300 rounded-md">
                                                </div>
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700 mb-2">Surname</label>
                                                    <input type="text" name="surname" value="{{ old('surname', $application->surname) }}" class="w-full py-2 px-3 border border-gray-300 rounded-md">
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Corporate Fields -->
                                        <div id="corporate_fields" class="mt-6 {{ $application->applicant_type == 'corporate' ? '' : 'hidden' }}">
                                            <h5 class="text-sm font-semibold text-gray-700 mb-3">Corporate Applicant Details</h5>
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700 mb-2">Corporate Name</label>
                                                    <input type="text" name="corporate_name" value="{{ old('corporate_name', $application->corporate_name) }}" class="w-full py-2 px-3 border border-gray-300 rounded-md">
                                                </div>
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700 mb-2">RC Number</label>
                                                    <input type="text" name="rc_number" value="{{ old('rc_number', $application->rc_number) }}" class="w-full py-2 px-3 border border-gray-300 rounded-md">
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Multiple Owners -->
                                        <div id="multiple_fields" class="mt-6 {{ $application->applicant_type == 'multiple' ? '' : 'hidden' }}">
                                            <h5 class="text-sm font-semibold text-gray-700 mb-3">Multiple Owners</h5>
                                            @php
                                                $multipleOwnersNames = [];
                                                if ($application->multiple_owners_names) {
                                                    $multipleOwnersNames = is_string($application->multiple_owners_names)
                                                        ? json_decode($application->multiple_owners_names, true)
                                                        : $application->multiple_owners_names;
                                                    if (!is_array($multipleOwnersNames)) {
                                                        $multipleOwnersNames = [];
                                                    }
                                                }
                                            @endphp
                                            <div id="owners_container" class="space-y-3">
                                                @if(count($multipleOwnersNames) > 0)
                                                    @foreach($multipleOwnersNames as $index => $ownerName)
                                                        <div class="owner-row flex items-center gap-4">
                                                            <div class="flex-1">
                                                                <label class="block text-sm font-medium text-gray-700 mb-1">Owner {{ $index + 1 }} Name</label>
                                                                <input type="text" name="multiple_owners_names[]" value="{{ $ownerName }}" class="w-full py-2 px-3 border border-gray-300 rounded-md" placeholder="Enter owner name">
                                                            </div>
                                                            @if($index > 0)
                                                                <button type="button" onclick="removeOwner(this)" class="bg-red-500 text-white px-3 py-2 rounded-md hover:bg-red-600 mt-6">Remove</button>
                                                            @endif
                                                        </div>
                                                    @endforeach
                                                @else
                                                    <div class="owner-row flex items-center gap-4">
                                                        <div class="flex-1">
                                                            <label class="block text-sm font-medium text-gray-700 mb-1">Owner 1 Name</label>
                                                            <input type="text" name="multiple_owners_names[]" value="" class="w-full py-2 px-3 border border-gray-300 rounded-md" placeholder="Enter owner name">
                                                        </div>
                                                    </div>
                                                @endif
                                            </div>
                                            <button type="button" onclick="addOwner()" class="mt-3 bg-blue-500 text-white px-4 py-2 rounded-md hover:bg-blue-600">Add Another Owner</button>
                                        </div>

                                        <!-- Contact Information -->
                                        <div class="mt-6">
                                            <h5 class="text-sm font-semibold text-gray-700 mb-3">Contact Information</h5>
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                                                    <input type="email" name="email" value="{{ old('email', $application->email) }}" class="w-full py-2 px-3 border border-gray-300 rounded-md">
                                                </div>
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700 mb-2">Phone Number</label>
                                                    <input type="text" name="phone_number" value="{{ old('phone_number', $application->phone_number) }}" class="w-full py-2 px-3 border border-gray-300 rounded-md">
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Address Information -->
                                        <div class="mt-6">
                                            <h5 class="text-sm font-semibold text-gray-700 mb-3">Address Information</h5>
                                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700 mb-2">House No.</label>
                                                    <input type="text" name="address_house_no" value="{{ old('address_house_no', $application->address_house_no) }}" class="w-full py-2 px-3 border border-gray-300 rounded-md">
                                                </div>
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700 mb-2">Street Name</label>
                                                    <input type="text" name="address_street_name" value="{{ old('address_street_name', $application->address_street_name) }}" class="w-full py-2 px-3 border border-gray-300 rounded-md">
                                                </div>
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700 mb-2">District</label>
                                                    <input type="text" name="address_district" value="{{ old('address_district', $application->address_district) }}" class="w-full py-2 px-3 border border-gray-300 rounded-md">
                                                </div>
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700 mb-2">LGA</label>
                                                    <input type="text" name="address_lga" value="{{ old('address_lga', $application->address_lga) }}" class="w-full py-2 px-3 border border-gray-300 rounded-md">
                                                </div>
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700 mb-2">State</label>
                                                    <input type="text" name="address_state" value="{{ old('address_state', $application->address_state) }}" class="w-full py-2 px-3 border border-gray-300 rounded-md">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Property Information -->
                                    <div class="bg-gray-50 border border-gray-200 rounded-xl p-6">
                                        <div class="flex items-center mb-4">
                                            <div class="bg-green-500 p-2 rounded-lg mr-3">
                                                <i data-lucide="map" class="w-5 h-5 text-white"></i>
                                            </div>
                                            <div>
                                                <h4 class="text-base font-semibold text-gray-800">Property Information</h4>
                                                <p class="text-xs text-gray-500">Matches property portion of Step 1.</p>
                                            </div>
                                        </div>

                                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-2">Land Use</label>
                                                <input type="text" name="land_use" value="{{ old('land_use', $application->land_use) }}" class="w-full py-2 px-3 border border-gray-300 rounded-md">
                                            </div>

                                            @if($application->land_use == 'residential')
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700 mb-2">Residential Type</label>
                                                    <select name="residential_type" class="w-full py-2 px-3 border border-gray-300 rounded-md">
                                                        <option value="">Select Type</option>
                                                        <option value="detached" {{ $application->residential_type == 'detached' ? 'selected' : '' }}>Detached</option>
                                                        <option value="semi_detached" {{ $application->residential_type == 'semi_detached' ? 'selected' : '' }}>Semi-Detached</option>
                                                        <option value="terrace" {{ $application->residential_type == 'terrace' ? 'selected' : '' }}>Terrace</option>
                                                        <option value="apartment" {{ $application->residential_type == 'apartment' ? 'selected' : '' }}>Apartment</option>
                                                    </select>
                                                </div>
                                            @endif

                                            @if($application->land_use == 'commercial')
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700 mb-2">Commercial Type</label>
                                                    <input type="text" name="commercial_type" value="{{ old('commercial_type', $application->commercial_type) }}" class="w-full py-2 px-3 border border-gray-300 rounded-md">
                                                </div>
                                            @endif

                                            @if($application->land_use == 'industrial')
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700 mb-2">Industrial Type</label>
                                                    <input type="text" name="industrial_type" value="{{ old('industrial_type', $application->industrial_type) }}" class="w-full py-2 px-3 border border-gray-300 rounded-md">
                                                </div>
                                            @endif

                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-2">Plot Size (sqm)</label>
                                                <input type="number" step="0.01" name="plot_size" value="{{ old('plot_size', $application->plot_size) }}" class="w-full py-2 px-3 border border-gray-300 rounded-md">
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-2">Number of Units</label>
                                                <input type="number" name="NoOfUnits" value="{{ old('NoOfUnits', $application->NoOfUnits) }}" class="w-full py-2 px-3 border border-gray-300 rounded-md">
                                            </div>
                                        </div>

                                        <div class="mt-6">
                                            <h5 class="text-sm font-semibold text-gray-700 mb-3">Property Location</h5>
                                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700 mb-2">Plot No.</label>
                                                    <input type="text" name="property_plot_no" value="{{ old('property_plot_no', $application->property_plot_no) }}" class="w-full py-2 px-3 border border-gray-300 rounded-md">
                                                </div>
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700 mb-2">Street Name</label>
                                                    <input type="text" name="property_street_name" value="{{ old('property_street_name', $application->property_street_name) }}" class="w-full py-2 px-3 border border-gray-300 rounded-md">
                                                </div>
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700 mb-2">District</label>
                                                    <input type="text" name="property_district" value="{{ old('property_district', $application->property_district) }}" class="w-full py-2 px-3 border border-gray-300 rounded-md">
                                                </div>
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700 mb-2">LGA</label>
                                                    <input type="text" name="property_lga" value="{{ old('property_lga', $application->property_lga) }}" class="w-full py-2 px-3 border border-gray-300 rounded-md">
                                                </div>
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700 mb-2">State</label>
                                                    <input type="text" name="property_state" value="{{ old('property_state', $application->property_state) }}" class="w-full py-2 px-3 border border-gray-300 rounded-md">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Financial Information -->
                                    @php
                                        $sanitizeMoney = function ($value) {
                                            if (is_null($value) || $value === '') {
                                                return '';
                                            }
                                            if (is_numeric($value)) {
                                                return $value;
                                            }
                                            return preg_replace('/[^\d\.\-]/', '', (string) $value);
                                        };
                                    @endphp
                                    <div class="bg-gray-50 border border-gray-200 rounded-xl p-6">
                                        <div class="flex items-center mb-4">
                                            <div class="bg-blue-500 p-2 rounded-lg mr-3">
                                                <i data-lucide="wallet" class="w-5 h-5 text-white"></i>
                                            </div>
                                            <div>
                                                <h4 class="text-base font-semibold text-gray-800">Financial Information</h4>
                                                <p class="text-xs text-gray-500">Payment data captured alongside Step 1.</p>
                                            </div>
                                        </div>

                                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-2">Application Fee</label>
                                                <input type="number" step="0.01" name="application_fee" value="{{ old('application_fee', $sanitizeMoney($application->application_fee)) }}" class="w-full py-2 px-3 border border-gray-300 rounded-md">
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-2">Processing Fee</label>
                                                <input type="number" step="0.01" name="processing_fee" value="{{ old('processing_fee', $sanitizeMoney($application->processing_fee)) }}" class="w-full py-2 px-3 border border-gray-300 rounded-md">
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-2">Site Plan Fee</label>
                                                <input type="number" step="0.01" name="site_plan_fee" value="{{ old('site_plan_fee', $sanitizeMoney($application->site_plan_fee)) }}" class="w-full py-2 px-3 border border-gray-300 rounded-md">
                                            </div>
                                        </div>

                                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mt-4">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-2">Application Fee Payment Date</label>
                                                <input type="date" name="application_fee_payment_date" value="{{ old('application_fee_payment_date', $formatDate($application->application_fee_payment_date ?? null)) }}" class="w-full py-2 px-3 border border-gray-300 rounded-md">
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-2">Processing Fee Payment Date</label>
                                                <input type="date" name="processing_fee_payment_date" value="{{ old('processing_fee_payment_date', $formatDate($application->processing_fee_payment_date ?? null)) }}" class="w-full py-2 px-3 border border-gray-300 rounded-md">
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-2">Site Plan Fee Payment Date</label>
                                                <input type="date" name="site_plan_fee_payment_date" value="{{ old('site_plan_fee_payment_date', $formatDate($application->site_plan_fee_payment_date ?? null)) }}" class="w-full py-2 px-3 border border-gray-300 rounded-md">
                                            </div>
                                        </div>

                                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mt-4">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-2">Application Fee Receipt Number</label>
                                                <input type="text" name="application_fee_receipt_number" value="{{ old('application_fee_receipt_number', $application->application_fee_receipt_number) }}" class="w-full py-2 px-3 border border-gray-300 rounded-md">
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-2">Processing Fee Receipt Number</label>
                                                <input type="text" name="processing_fee_receipt_number" value="{{ old('processing_fee_receipt_number', $application->processing_fee_receipt_number) }}" class="w-full py-2 px-3 border border-gray-300 rounded-md">
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-2">Site Plan Fee Receipt Number</label>
                                                <input type="text" name="site_plan_fee_receipt_number" value="{{ old('site_plan_fee_receipt_number', $application->site_plan_fee_receipt_number) }}" class="w-full py-2 px-3 border border-gray-300 rounded-md">
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-2">Payment Date</label>
                                                <input type="date" name="payment_date" value="{{ old('payment_date', $formatDate($application->payment_date ?? null)) }}" class="w-full py-2 px-3 border border-gray-300 rounded-md">
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-2">Receipt Number</label>
                                                <input type="text" name="receipt_number" value="{{ old('receipt_number', $application->receipt_number) }}" class="w-full py-2 px-3 border border-gray-300 rounded-md">
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Comments -->
                                    <div class="bg-gray-50 border border-gray-200 rounded-xl p-6">
                                        <h4 class="text-base font-semibold text-gray-800 mb-3">Comments</h4>
                                        <textarea name="comments" rows="4" class="w-full py-2 px-3 border border-gray-300 rounded-md" placeholder="Add additional information that assists processing.">{{ old('comments', $application->comments) }}</textarea>
                                    </div>
                                </div>
                            </section>
                            <!-- Step 2 -->
                            <section class="border border-gray-200 rounded-2xl shadow-sm bg-white scroll-mt-24" id="step2Section">
                                <div class="border-b border-gray-100 px-6 py-5 flex flex-col gap-1">
                                    <p class="text-xs uppercase tracking-wide text-emerald-600 font-semibold">Step 2</p>
                                    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                                        <div>
                                            <h3 class="text-lg font-semibold text-gray-900">Shared Areas &amp; Common Facilities</h3>
                                            <p class="text-sm text-gray-500">Keep the shared facilities list in sync with the Step 2 grid.</p>
                                        </div>
                                        <span id="sharedAreasSelectedCount" class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-blue-50 text-blue-700">0 selected</span>
                                    </div>
                                </div>
                                <div class="p-6 space-y-6">
                                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                        @foreach ($sharedAreaOptions as $option)
                                            @php
                                                $isChecked = in_array(strtolower($option['value']), $sharedAreasSelectedLower, true);
                                            @endphp
                                            <label class="flex items-center gap-3 border border-gray-200 rounded-lg p-3 hover:border-emerald-400 transition-colors cursor-pointer bg-gray-50">
                                                <input type="checkbox" name="shared_areas[]" value="{{ $option['value'] }}" class="w-4 h-4 text-emerald-600 border-gray-300 rounded" {{ $isChecked ? 'checked' : '' }} data-shared-area>
                                                <div class="flex items-center gap-2 text-sm text-gray-700">
                                                    <i data-lucide="{{ $option['icon'] }}" class="w-4 h-4 text-gray-500"></i>
                                                    {{ $option['label'] }}
                                                </div>
                                            </label>
                                        @endforeach
                                    </div>

                                    <div class="bg-gray-50 border border-dashed border-gray-300 rounded-xl p-4">
                                        <label for="sharedAreasCustomInput" class="block text-sm font-semibold text-gray-700 mb-2">Custom Shared Areas</label>
                                        <textarea id="sharedAreasCustomInput" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm" rows="3" placeholder="e.g. Access Road, Hallway B, Passage C">{{ implode(', ', $customSharedAreas) }}</textarea>
                                        <p class="text-xs text-gray-500 mt-2">Separate entries with commas. They will be appended to <code>shared_areas[]</code> automatically when you save.</p>
                                        <div id="sharedAreasCustomContainer"></div>
                                    </div>
                                </div>
                            </section>
                            <!-- Step 3 -->
                            <section class="border border-gray-200 rounded-2xl shadow-sm bg-white scroll-mt-24" id="step3Section">
                                <div class="border-b border-gray-100 px-6 py-5 flex flex-col gap-1">
                                    <p class="text-xs uppercase tracking-wide text-emerald-600 font-semibold">Step 3</p>
                                    <div>
                                        <h3 class="text-lg font-semibold text-gray-900">Document Uploads</h3>
                                        <p class="text-sm text-gray-500">Aligns with the Step 3 &ldquo;Documents&rdquo; stage &mdash; re-upload to replace existing files.</p>
                                    </div>
                                </div>
                                <div class="p-6 space-y-8">
                                    <div>
                                        <h4 class="text-base font-semibold text-gray-800 mb-3">Identity Documents</h4>
                                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                            @foreach ($identityDocuments as $field => $meta)
                                                @php
                                                    $currentPath = $application->{$field} ?? null;
                                                    $currentUrl = $currentPath ? \Illuminate\Support\Facades\Storage::disk('public')->url($currentPath) : null;
                                                @endphp
                                                <div class="border border-gray-200 rounded-xl p-4 bg-gray-50">
                                                    <div class="flex items-center justify-between mb-2">
                                                        <span class="font-medium text-gray-800">{{ $meta['label'] }}</span>
                                                        @if($currentUrl)
                                                            <a href="{{ $currentUrl }}" target="_blank" class="text-xs text-emerald-600 font-semibold">View</a>
                                                        @else
                                                            <span class="text-xs text-gray-400">Not uploaded</span>
                                                        @endif
                                                    </div>
                                                    @if($field === 'passport' && $currentUrl)
                                                        <img src="{{ $currentUrl }}" alt="Current Passport" class="w-full h-32 object-cover rounded mb-3 border">
                                                    @elseif($currentUrl)
                                                        <p class="text-xs text-gray-600 mb-3 truncate">{{ basename($application->{$field}) }}</p>
                                                    @else
                                                        <p class="text-xs text-gray-500 mb-3">{{ $meta['hint'] }}</p>
                                                    @endif
                                                    <input type="file" name="{{ $field }}" accept="{{ $meta['accept'] }}" class="w-full text-sm">
                                                    <p class="text-xs text-gray-500 mt-1">{{ $meta['hint'] }}</p>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>

                                    <div>
                                        <h4 class="text-base font-semibold text-gray-800 mb-3">Supporting Documents</h4>
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            @foreach ($documentDefinitions as $field => $meta)
                                                @php
                                                    $docInfo = $documentsPayload[$field] ?? null;
                                                    $docPath = $docInfo['path'] ?? ($field === 'survey_plan' ? $application->survey_plan : null);
                                                    $docUrl = $docPath ? \Illuminate\Support\Facades\Storage::disk('public')->url($docPath) : null;
                                                    $docName = $docInfo['original_name'] ?? ($docPath ? basename($docPath) : null);
                                                @endphp
                                                <div class="border border-gray-200 rounded-xl p-4 bg-white">
                                                    <div class="flex items-center mb-3">
                                                        <div class="w-10 h-10 rounded-full bg-emerald-50 flex items-center justify-center mr-3">
                                                            <i data-lucide="{{ $meta['icon'] }}" class="w-5 h-5 text-emerald-600"></i>
                                                        </div>
                                                        <div>
                                                            <h5 class="font-semibold text-gray-800">{{ $meta['label'] }}</h5>
                                                            <p class="text-xs text-gray-500">{{ $meta['description'] }}</p>
                                                        </div>
                                                    </div>
                                                    @if($docUrl)
                                                        <a href="{{ $docUrl }}" target="_blank" class="inline-flex items-center text-xs font-semibold text-emerald-600 mb-2">
                                                            <i data-lucide="external-link" class="w-4 h-4 mr-1"></i>
                                                            {{ $docName ?? 'View Document' }}
                                                        </a>
                                                    @else
                                                        <p class="text-xs text-gray-400 mb-2">No file uploaded yet.</p>
                                                    @endif
                                                    <input type="file" name="{{ $field }}" accept=".pdf,.jpg,.jpeg,.png" class="w-full text-sm">
                                                    <p class="text-xs text-gray-500 mt-1">PDF, JPG or PNG (max 5MB)</p>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            </section>

                            <!-- Submit Buttons -->
                            <div class="flex justify-end gap-4 pt-2">
                                <a href="{{ route('sectionaltitling.viewrecorddetail', ['id' => $application->id]) }}" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-medium py-2 px-6 rounded-md transition duration-150 ease-in-out">Cancel</a>
                                <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-2 px-6 rounded-md transition duration-150 ease-in-out">Update Application</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    function toggleApplicantFields() {
        const applicantType = document.getElementById('applicant_type').value;
        const individualFields = document.getElementById('individual_fields');
        const corporateFields = document.getElementById('corporate_fields');
        const multipleFields = document.getElementById('multiple_fields');

        [individualFields, corporateFields, multipleFields].forEach(section => section && section.classList.add('hidden'));

        if (applicantType === 'individual' && individualFields) {
            individualFields.classList.remove('hidden');
        } else if (applicantType === 'corporate' && corporateFields) {
            corporateFields.classList.remove('hidden');
        } else if (applicantType === 'multiple' && multipleFields) {
            multipleFields.classList.remove('hidden');
        }
    }

    function addOwner() {
        const container = document.getElementById('owners_container');
        const ownerRows = container.querySelectorAll('.owner-row');
        const newIndex = ownerRows.length + 1;

        const wrapper = document.createElement('div');
        wrapper.className = 'owner-row flex items-center gap-4';
        wrapper.innerHTML = `
            <div class="flex-1">
                <label class="block text-sm font-medium text-gray-700 mb-1">Owner ${newIndex} Name</label>
                <input type="text" name="multiple_owners_names[]" class="w-full py-2 px-3 border border-gray-300 rounded-md" placeholder="Enter owner name">
            </div>
            <button type="button" onclick="removeOwner(this)" class="bg-red-500 text-white px-3 py-2 rounded-md hover:bg-red-600 mt-6">Remove</button>
        `;
        container.appendChild(wrapper);
    }

    function removeOwner(button) {
        const row = button.closest('.owner-row');
        if (row) {
            row.remove();
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        toggleApplicantFields();

        const sharedAreaCheckboxes = document.querySelectorAll('[data-shared-area]');
        const sharedAreasCount = document.getElementById('sharedAreasSelectedCount');
        const customInput = document.getElementById('sharedAreasCustomInput');
        const customContainer = document.getElementById('sharedAreasCustomContainer');
        const form = document.getElementById('editApplicationForm');
        const stepButtons = document.querySelectorAll('.step-nav');

        function syncCustomAreasToHiddenInputs() {
            if (!customInput || !customContainer) {
                return;
            }

            customContainer.innerHTML = '';
            customInput.value.split(',')
                .map(token => token.trim())
                .filter(token => token.length > 0)
                .forEach(value => {
                    const hidden = document.createElement('input');
                    hidden.type = 'hidden';
                    hidden.name = 'shared_areas[]';
                    hidden.value = value;
                    hidden.dataset.generated = 'true';
                    customContainer.appendChild(hidden);
                });
        }

        function updateSharedAreasCount() {
            const selected = Array.from(sharedAreaCheckboxes).filter(cb => cb.checked).length;
            const generated = customContainer ? customContainer.querySelectorAll('input[data-generated]').length : 0;
            if (sharedAreasCount) {
                sharedAreasCount.textContent = `${selected + generated} selected`;
            }
        }

        sharedAreaCheckboxes.forEach(cb => cb.addEventListener('change', updateSharedAreasCount));

        if (customInput) {
            customInput.addEventListener('input', () => {
                syncCustomAreasToHiddenInputs();
                updateSharedAreasCount();
            });
        }

        if (form) {
            form.addEventListener('submit', () => {
                syncCustomAreasToHiddenInputs();
            });
        }

        function setActiveStep(targetButton) {
            stepButtons.forEach(button => {
                const circle = button.querySelector('[data-step-circle]');
                const label = button.querySelector('[data-step-label]');
                const isActive = button === targetButton;
                if (circle) {
                    circle.classList.toggle('bg-emerald-600', isActive);
                    circle.classList.toggle('text-white', isActive);
                    circle.classList.toggle('bg-gray-200', !isActive);
                    circle.classList.toggle('text-gray-600', !isActive);
                }
                if (label) {
                    label.classList.toggle('text-gray-900', isActive);
                    label.classList.toggle('text-gray-600', !isActive);
                }
            });
        }

        stepButtons.forEach(button => {
            button.addEventListener('click', () => {
                const targetSelector = button.dataset.stepTarget;
                const targetSection = document.querySelector(targetSelector);
                if (targetSection) {
                    targetSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
                setActiveStep(button);
            });
        });

        if (stepButtons.length > 0) {
            setActiveStep(stepButtons[0]);
        }

        syncCustomAreasToHiddenInputs();
        updateSharedAreasCount();
    });
</script>
@endsection
