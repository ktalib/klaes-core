@extends('layouts.app')

@section('page-title')
    {{ __('Edit File Index') }}
@endsection

@section('content')
    <!-- Minimal Professional Styling -->
    <style>
        .form-input-clean {
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 10px 12px;
            font-size: 14px;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
            background: #ffffff;
        }

        .form-input-clean:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .form-input-clean:read-only {
            background-color: #f9fafb;
            color: #6b7280;
            border-color: #d1d5db;
        }

        .form-label-clean {
            font-size: 14px;
            font-weight: 500;
            color: #374151;
            margin-bottom: 6px;
            display: block;
        }

        .form-section-clean {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 24px;
            margin-bottom: 20px;
        }

        .form-section-header {
            font-size: 16px;
            font-weight: 600;
            color: #111827;
            margin-bottom: 20px;
            padding-bottom: 8px;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            gap: 8px;
        }


        .form-section-header2 {
            font-size: 16px;
            font-weight: 600;
            color: #111827;
            margin-top: 20px;
            padding-top: 8px;
            border-top: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .checkbox-clean {
            width: 16px;
            height: 16px;
            border: 1px solid #d1d5db;
            border-radius: 3px;
            background: #ffffff;
            cursor: pointer;
        }

        .checkbox-clean:checked {
            background-color: #3b82f6;
            border-color: #3b82f6;
        }

        .btn-clean {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 10px 20px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s ease;
            text-decoration: none;
            border: 1px solid transparent;
            cursor: pointer;
        }

        .btn-primary-clean {
            background-color: #3b82f6;
            color: white;
            border-color: #3b82f6;
        }

        .btn-primary-clean:hover {
            background-color: #2563eb;
            border-color: #2563eb;
        }

        .btn-secondary-clean {
            background-color: #ffffff;
            color: #6b7280;
            border-color: #d1d5db;
        }

        .btn-secondary-clean:hover {
            background-color: #f9fafb;
            color: #374151;
        }

        .alert-clean {
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .alert-success {
            background-color: #f0f9ff;
            border: 1px solid #bae6fd;
            color: #0369a1;
        }

        .alert-error {
            background-color: #fef2f2;
            border: 1px solid #fecaca;
            color: #dc2626;
        }

        .page-header-clean {
            margin-bottom: 32px;
            padding-bottom: 16px;
            border-bottom: 1px solid #e5e7eb;
        }

        .form-grid {
            display: grid;
            gap: 24px;
        }

        @media (min-width: 768px) {
            .form-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        .form-section-clean {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 24px;
            margin-bottom: 24px;
            position: relative;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        }

        .loading-spinner-clean {
            width: 16px;
            height: 16px;
            border: 2px solid #ffffff;
            border-top: 2px solid transparent;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }
    </style>

    <div class="flex-1 overflow-auto">
        @include('admin.header')

        <!-- Dashboard Content -->
        <div class="p-6">
            <div class="max-w-5xl mx-auto">
                <!-- Clean Page Header -->
                <div class="page-header-clean">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-3xl font-semibold text-gray-900">{{ $PageTitle }}</h1>
                            <p class="mt-2 text-gray-600">{{ $PageDescription }}</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <!-- Property Transaction Button -->
                            <button type="button" id="property-transaction-btn"
                                class="inline-flex items-center px-4 py-2 bg-blue-600 text-white font-medium rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors gap-2">
                                <i data-lucide="file-plus" class="h-4 w-4"></i>
                                <span id="property-transaction-btn-text">{{ ($hasPropertyRecords ?? false) ? 'Update Property Transaction Details' : 'Add Property Transaction Details' }}</span>
                            </button>

                            <a href="{{ route('indexed-files.index') }}" class="btn-clean btn-secondary-clean">
                                <i data-lucide="arrow-left" class="h-4 w-4"></i>
                                Back to List
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Alert Messages -->
                <div id="alert-container"></div>

                <!-- Clean Form -->
                <form id="edit-file-form" method="POST" action="{{ route('fileindexing.update', $fileIndexing->id) }}">
                    @csrf
                    @method('PUT')

                    @php
                        $cofoDetails = $cofoDetails ?? [];
                        $hasCofoDefault = $fileIndexing->has_cofo ?? (!empty($cofoDetails) ? 1 : 0);
                        $hasCofo = (int) old('has_cofo', $hasCofoDefault);
                        $cofoValue = function (string $key, $fallback = null) use ($cofoDetails) {
                            return old($key, $cofoDetails[$key] ?? $fallback);
                        };
                        $cofoInstrumentOptions = [
                            'Certificate of Occupancy' => 'Certificate of Occupancy',
                            'ST Certificate of Occupancy' => 'ST Certificate of Occupancy',
                            'SLTR Certificate of Occupancy' => 'SLTR Certificate of Occupancy',
                            'Customary Right of Occupancy' => 'Customary Right of Occupancy',
                            'Deed of Transfer' => 'Deed of Transfer',
                            'Deed of Assignment' => 'Deed of Assignment',
                            'ST Assignment' => 'ST Assignment',
                            'Deed of Mortgage' => 'Deed of Mortgage',
                            'Tripartite Mortgage' => 'Tripartite Mortgage',
                            'Deed of Sub Lease' => 'Deed of Sub Lease',
                            'Deed of Sub Under Lease' => 'Deed of Sub Under Lease',
                            'Power of Attorney' => 'Power of Attorney',
                            'Irrevocable Power of Attorney' => 'Irrevocable Power of Attorney',
                            'Conveyance' => 'Conveyance',
                            'Deed of Gift' => 'Deed of Gift',
                            'Court Affidavit' => 'Court Affidavit',
                            'Consent Judgment' => 'Consent Judgment',
                            'Right of Occupancy' => 'Right of Occupancy',
                            'Other' => 'Other',
                        ];
                        $cofoLandUseOptions = [
                            'RESIDENTIAL' => 'RESIDENTIAL',
                            'AGRICULTURAL' => 'AGRICULTURAL',
                            'COMMERCIAL' => 'COMMERCIAL',
                            'COMMERCIAL ( WARE HOUSE)' => 'COMMERCIAL ( WARE HOUSE)',
                            'COMMERCIAL (OFFICES)' => 'COMMERCIAL (OFFICES)',
                            'COMMERCIAL (PETROL FILLING STATION)' => 'COMMERCIAL (PETROL FILLING STATION)',
                            'COMMERCIAL (RICE PROCESSING)' => 'COMMERCIAL (RICE PROCESSING)',
                            'COMMERCIAL (SCHOOL)' => 'COMMERCIAL (SCHOOL)',
                            'COMMERCIAL (SHOPS & PUBLIC CONVINIENCE)' => 'COMMERCIAL (SHOPS & PUBLIC CONVINIENCE)',
                            'COMMERCIAL (SHOPS AND OFFICES)' => 'COMMERCIAL (SHOPS AND OFFICES)',
                            'COMMERCIAL (SHOPS)' => 'COMMERCIAL (SHOPS)',
                            'COMMERCIAL (WAREHOUSE)' => 'COMMERCIAL (WAREHOUSE)',
                            'COMMERCIAL (WORKSHOP AND OFFICES)' => 'COMMERCIAL (WORKSHOP AND OFFICES)',
                            'COMMERCIAL AND RESIDENTIAL' => 'COMMERCIAL AND RESIDENTIAL',
                            'INDUSTRIAL' => 'INDUSTRIAL',
                            'INDUSTRIAL (SMALL SCALE)' => 'INDUSTRIAL (SMALL SCALE)',
                            'RESIDENTIAL AND COMMERCIAL' => 'RESIDENTIAL AND COMMERCIAL',
                            'RESIDENTIAL/COMMERCIAL' => 'RESIDENTIAL/COMMERCIAL',
                            'RESIDENTIAL/COMMERCIAL LAYOUT' => 'RESIDENTIAL/COMMERCIAL LAYOUT',
                        ];
                    @endphp
 @php
    use Illuminate\Support\Facades\DB;

    $customerTypes = DB::connection('sqlsrv')
        ->table('customer_type')
        ->orderBy('customer_type_name')
        ->pluck('customer_type_name')
        ->toArray();

    $currentFileType = old('file_type', $fileIndexing->file_type ?? '');

    // Detect custom value (not in table OR explicitly "Other")
    $isOther = $currentFileType === 'Other'
        || ($currentFileType && !in_array($currentFileType, $customerTypes));
@endphp



                    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                        <!-- Top Section: Customer Type (Full Width) -->
                        <div class="lg:col-span-12">
                            <div class="form-section-clean">
                                <div class="form-section-header">
                                    <i data-lucide="user" class="h-5 w-5 text-gray-500"></i>
                                    Customer Type
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
     <script>
    function handleFileTypeEdit(select) {
        const hidden = document.getElementById('file_type');
        const other  = document.getElementById('file_type_other');

        if (select.value === 'Other') {
            other.classList.remove('hidden');
            other.required = true;
            hidden.value = '';
        } else {
            other.classList.add('hidden');
            other.required = false;
            hidden.value = select.value;
            other.value = '';
        }

        other.oninput = () => {
            hidden.value = other.value;
        };
    }

    document.addEventListener('DOMContentLoaded', () => {
        handleFileTypeEdit(document.getElementById('file_type_select'));
    });
</script>


   <div>
    <label for="file_type_select" class="form-label-clean">
        Customer Type <span class="text-red-500">*</span>
    </label>

    <select id="file_type_select"
            class="w-full form-input-clean"
            onchange="handleFileTypeEdit(this)"
            required>

        <option value="">Select Customer Type</option>

        @foreach ($customerTypes as $type)
            <option value="{{ $type }}"
                {{ !$isOther && $currentFileType === $type ? 'selected' : '' }}>
                {{ $type }}
            </option>
        @endforeach
    </select>

    {{-- Actual submitted value --}}
    <input type="hidden"
           name="file_type"
           id="file_type"
           value="{{ $isOther ? '' : $currentFileType }}">

    {{-- Shown only when "Other" is selected --}}
    <input type="text"
           id="file_type_other"
           class="mt-2 w-full form-input-clean {{ $isOther ? '' : 'hidden' }}"
           placeholder="Please specify"
           value="{{ $isOther && $currentFileType !== 'Other' ? $currentFileType : '' }}">
</div>


                                    <div id="rc_no_container" style="display: none;">
                                        <label for="rc_no" class="form-label-clean">
                                            RC NO <span class="text-red-600">*</span>
                                        </label>
                                        <input type="text" id="rc_no" name="rc_no"
                                            value="{{ old('rc_no', $fileIndexing->rc_no) }}" class="w-full form-input-clean"
                                            placeholder="Registration Certificate Number">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Left Column (Occupies 1/2) -->
                        <div class="lg:col-span-6 space-y-6">
                            <!-- Personal Details Section -->
                            <div class="form-section-clean">
                                <div class="form-section-header">
                                    <i data-lucide="user-circle" class="h-5 w-5 text-gray-500"></i>
                                    Personal Details
                                </div>

                                <div class="space-y-4">
                                    <!-- Date of Birth & Phone Number -->
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <div>
                                            <label for="dob" class="form-label-clean">Date of Birth</label>
                                            <input type="date" id="dob" name="dob" value="{{ old('dob', $fileIndexing->dob ? \Carbon\Carbon::parse($fileIndexing->dob)->format('Y-m-d') : '') }}"
                                                class="w-full form-input-clean h-[42px]">
                                        </div>

                                        <div>
                                            <label for="gender" class="form-label-clean">Gender <span class="text-red-500">*</span></label>
                                            @php $currentGender = old('gender', $fileIndexing->gender); @endphp
                                            <select id="gender" name="gender" class="w-full form-input-clean h-[42px]" required>
                                                <option value="">Select Gender</option>
                                                <option value="Male" {{ $currentGender === 'Male' ? 'selected' : '' }}>Male</option>
                                                <option value="Female" {{ $currentGender === 'Female' ? 'selected' : '' }}>Female</option>
                                                <option value="Corporate" {{ $currentGender === 'Corporate' ? 'selected' : '' }}>Corporate</option>
                                                <option value="Joint" {{ $currentGender === 'Joint' ? 'selected' : '' }}>Joint</option>
                                            </select>
                                        </div>

                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                      

                                        <div>
                                            <label for="country_code" class="form-label-clean">
                                                Phone Number
                                            </label>
                                            <div class="flex items-center gap-2">
                                                <select id="country_code" name="country_code" class="w-24 form-input-clean px-1 text-xs h-[42px]">
                                                    <option value="+234" {{ old('country_code', $fileIndexing->country_code ?? '+234') == '+234' ? 'selected' : '' }}>+234 (NG)</option>
                                                    <option value="+1" {{ old('country_code', $fileIndexing->country_code) == '+1' ? 'selected' : '' }}>+1 (US)</option>
                                                    <option value="+44" {{ old('country_code', $fileIndexing->country_code) == '+44' ? 'selected' : '' }}>+44 (UK)</option>
                                                </select>
                                                <input type="text" id="phone" name="phone"
                                                    value="{{ old('phone', $fileIndexing->phone) }}"
                                                    class="flex-1 form-input-clean h-[42px]" placeholder="080..."
                                                    maxlength="15" pattern="[0-9]*" inputmode="numeric">
                                            </div>
                                        </div>
                                    </div>

                                    <!-- NIN & TIN -->
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <div>
                                            <label for="nin" class="form-label-clean">NIN</label>
                                            <input type="text" id="nin" name="nin" value="{{ old('nin', $fileIndexing->nin) }}"
                                                class="w-full form-input-clean h-[42px]" placeholder="11 digits" maxlength="11"
                                                pattern="[0-9]{11}" inputmode="numeric">
                                        </div>

                                        <div>
                                            <label for="tin" class="form-label-clean">TIN</label>
                                            <input type="text" id="tin" name="tin" value="{{ old('tin', $fileIndexing->tin) }}"
                                                class="w-full form-input-clean h-[42px]" placeholder="Tax ID Number">
                                        </div>
                                    </div>

                                    <!-- Residence Address -->
                                    <div>
                                        <label for="residence_address" class="form-label-clean">
                                            Residential Address <span class="text-red-500">*</span>
                                        </label>
                                        <textarea id="residence_address" name="residence_address" rows="3"
                                            class="w-full form-input-clean" placeholder="Enter residential address"
                                            required>{{ old('residence_address', $fileIndexing->residence_address) }}</textarea>
                                    </div>
                                </div>
                            </div>

                            <!-- Property Description Section -->
                            <div class="form-section-clean">
                                <div class="form-section-header">
                                    <i data-lucide="map-pin" class="h-5 w-5 text-gray-500"></i>
                                    Property Location (Address Builder)
                                </div>

                                <div class="space-y-4">
                                    {{-- Fields ordered exactly as they compose the auto-built address:
                                         PLOT NUMBER → DISTRICT → LGA → STATE. --}}
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <div>
                                            <label for="plot_number" class="form-label-clean">Plot Number</label>
                                            <input type="text" id="plot_number" name="plot_number"
                                                value="{{ old('plot_number', $fileIndexing->plot_number) }}"
                                                class="w-full form-input-clean uppercase" placeholder="ENTER PLOT NUMBER">
                                        </div>
                                        <div>
                                            <label for="district" class="form-label-clean">District</label>
                                            @php
                                                $currentDistrict = old('district', $fileIndexing->district);
                                                $districtsCollection = collect($districts);
                                                $hasOthers = $districtsCollection->contains(function($value) {
                                                    return strtolower($value) === 'other' || strtolower($value) === 'others';
                                                });
                                                
                                                $mainOptionsIncludesCurrent = $districtsCollection->contains($currentDistrict);
                                                // If current value is not in the list and not empty, treat it as a custom value (Others)
                                                // Unless it is literally "Other" or "Others"
                                                $isCustomDistrict = !empty($currentDistrict) && !$mainOptionsIncludesCurrent && strtolower($currentDistrict) !== 'other' && strtolower($currentDistrict) !== 'others';
                                                
                                                $showSpecifyInput = $isCustomDistrict || strtolower($currentDistrict) === 'others' || strtolower($currentDistrict) === 'other';
                                            @endphp
                                            <select id="district" name="district" class="w-full form-input-clean">
                                                <option value="">Select District</option>
                                                @foreach($districts as $districtName)
                                                    <option value="{{ $districtName }}" {{ ($currentDistrict == $districtName) ? 'selected' : '' }}>
                                                        {{ $districtName }}
                                                    </option>
                                                @endforeach
                                                @if(!$hasOthers)
                                                    <option value="Others" {{ $showSpecifyInput ? 'selected' : '' }}>Others</option>
                                                @else
                                                    {{-- Ensure existing Other/Others is selected if showing specify input --}}
                                                    <script>
                                                        document.addEventListener('DOMContentLoaded', () => {
                                                            const distSelect = document.getElementById('district');
                                                            const shouldSelectOthers = @json($showSpecifyInput);
                                                            if(shouldSelectOthers && distSelect) {
                                                                for(let i=0; i<distSelect.options.length; i++) {
                                                                    const opt = distSelect.options[i];
                                                                    if(opt.value.toLowerCase() === 'other' || opt.value.toLowerCase() === 'others') {
                                                                        distSelect.selectedIndex = i;
                                                                        break;
                                                                    }
                                                                }
                                                            }
                                                        });
                                                    </script>
                                                @endif
                                            </select>
                                            
                                            <div id="district_specify_container" class="{{ $showSpecifyInput ? '' : 'hidden' }} mt-2">
                                                <label for="district_specified" class="form-label-clean text-xs text-gray-500">Specify District Name</label>
                                                <input type="text" id="district_specified" 
                                                    value="{{ $isCustomDistrict ? $currentDistrict : '' }}" 
                                                    class="w-full form-input-clean" 
                                                    placeholder="Enter District Name">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <div>
                                            <label for="lga" class="form-label-clean">LGA</label>
                                            <select id="lga" name="lga" class="w-full form-input-clean">
                                                <option value="">Select LGA</option>
                                                @foreach($lgas as $lgaName)
                                                    <option value="{{ $lgaName }}" {{ old('lga', $fileIndexing->lga) == $lgaName ? 'selected' : '' }}>
                                                        {{ $lgaName }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label for="state" class="form-label-clean">State</label>
                                            <input type="text" id="state" name="state" value="Kano" class="w-full form-input-clean bg-gray-50" readonly>
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <div>
                                            <label for="plot_size" class="form-label-clean">Plot Size <span class="text-red-500">*</span></label>
                                            <input type="number" step="any" min="0" id="plot_size" name="plot_size"
                                                value="{{ old('plot_size', $fileIndexing->plot_size) }}"
                                                required aria-required="true"
                                                class="w-full form-input-clean" placeholder="e.g. 3.4">
                                        </div>
                                    </div>

                                    <div>
                                        <label for="location" class="form-label-clean">
                                            Property Description / Address <span class="text-xs text-gray-400 font-normal">(Auto-built)</span>
                                        </label>
                                        <textarea id="location" name="location" rows="3" class="w-full form-input-clean uppercase bg-gray-100 border-gray-300"
                                            placeholder="DETAILED PROPERTY DESCRIPTION WILL BE BUILT HERE..."
                                            style="text-transform: uppercase;" readonly>{{ old('location', $fileIndexing->location) }}</textarea>
                                    </div>

                                    {{-- Geocoded coordinates (read-only) + pin action. These submit as
                                         latitude / longitude and drive the Property Location Map below. --}}
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <div>
                                            <label for="latitude" class="form-label-clean">Latitude</label>
                                            <input type="text" id="latitude" name="latitude"
                                                value="{{ old('latitude', $fileIndexing->latitude) }}"
                                                class="w-full form-input-clean bg-gray-50" readonly
                                                placeholder="Pin on map to capture latitude">
                                        </div>
                                        <div>
                                            <label for="longitude" class="form-label-clean">Longitude</label>
                                            <input type="text" id="longitude" name="longitude"
                                                value="{{ old('longitude', $fileIndexing->longitude) }}"
                                                class="w-full form-input-clean bg-gray-50" readonly
                                                placeholder="Pin on map to capture longitude">
                                        </div>
                                    </div>
                                    <div>
                                        <button type="button" id="apply-pin-btn"
                                            class="w-full inline-flex items-center justify-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-md transition-colors">
                                            <i data-lucide="map-pin" class="h-4 w-4"></i>
                                            Apply &amp; Pin on Map
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Right Column (Occupies 1/2) -->
                        <div class="lg:col-span-6 space-y-6">
                            <!-- File Information Section -->
                            <div class="form-section-clean">
                                <div class="form-section-header">
                                    <i data-lucide="file-text" class="h-5 w-5 text-gray-500"></i>
                                    File Information
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4">
                                    <div class="sm:col-span-2">
                                        <label for="file_number" class="form-label-clean">
                                            File Number <span class="text-red-500">*</span>
                                        </label>
                                        <div class="flex">
                                            <input type="text" id="file_number" name="file_number"
                                                class="w-full form-input-clean mr-2 font-mono bg-gray-50"
                                                value="{{ old('file_number', $fileIndexing->file_number ?? $fileIndexing->temp_file_no) }}"
                                                placeholder="Enter file number" readonly>

                                            <button type="button" id="change-file-number-btn"
                                                class="px-4 py-2 bg-emerald-600 text-white rounded-md hover:bg-emerald-700 transition-colors flex items-center gap-2"
                                                style="white-space: nowrap;" title="Change existing file number">
                                                <i data-lucide="refresh-cw" class="w-4 h-4"></i>
                                                Change
                                            </button>
                                        </div>
                                        @php
                                            $fi_tracking_id = $fileIndexing->tracking_id ?? null;
                                        @endphp
                                        <div data-tracking-id-badge class="mt-2 flex flex-wrap items-center gap-2 text-xs">
                                            @if(!empty($fi_tracking_id))
                                                <span class="inline-flex items-center gap-1 rounded-full bg-red-50 text-red-700 border border-red-200 px-2.5 py-1 font-mono">
                                                    <i data-lucide="link-2" class="h-3.5 w-3.5"></i>
                                                    Tracking ID: <strong class="font-bold">{{ $fi_tracking_id }}</strong>
                                                </span>
                                                <span class="text-gray-500">
                                                    Changing the File Number will keep this Tracking ID and cascade the rename to all linked tables.
                                                </span>
                                            @else
                                                <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 text-amber-700 border border-amber-200 px-2.5 py-1 font-mono">
                                                    <i data-lucide="alert-triangle" class="h-3.5 w-3.5"></i>
                                                    No Tracking ID linked
                                                </span>
                                                <span class="text-gray-500">
                                                    The system will attempt to resolve one from the registry grouping table on save.
                                                </span>
                                            @endif
                                            @if(!empty($fileIndexing->temp_file_no))
                                                <span class="inline-flex items-center gap-1 rounded-full bg-blue-50 text-blue-700 border border-blue-200 px-2.5 py-1 font-mono">
                                                    <i data-lucide="clock" class="h-3.5 w-3.5"></i>
                                                    Temp File No: <strong class="font-bold">{{ $fileIndexing->temp_file_no }}</strong>
                                                </span>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="sm:col-span-2">
                                        <label for="file_title" class="form-label-clean">
                                            File Title <span class="text-red-500">*</span>
                                        </label>
                                        <input type="text" id="file_title" name="file_title"
                                            value="{{ old('file_title', $fileIndexing->file_title) }}"
                                            class="w-full form-input-clean uppercase" placeholder="ENTER FILE TITLE" required>
                                    </div>

                                  <div>
    <label for="land_use_type" class="form-label-clean">
        Land Use Type <span class="text-red-500">*</span>
    </label>

    <select id="land_use_type" name="land_use_type" class="w-full form-input-clean" required>
        <option value="">Select Land Use Type</option>

        @if(isset($landUseTypes))
            @foreach($landUseTypes as $key => $label)
                <option value="{{ $key }}" {{ old('land_use_type', $fileIndexing->land_use_type) == $key ? 'selected' : '' }}>
                    {{ $label }}
                </option>
            @endforeach

        @elseif(isset($cofoLandUseOptions))
            @foreach($cofoLandUseOptions as $key => $label)
                <option value="{{ $key }}" {{ old('land_use_type', $fileIndexing->land_use_type) == $key ? 'selected' : '' }}>
                    {{ $label }}
                </option>
            @endforeach
        @endif
    </select>
</div>
<div id="other_land_use_wrapper" class="hidden mt-3">
    <label class="form-label-clean">
        Specify Land Use Type <span class="text-red-500">*</span>
    </label>

    <input
        type="text"
        id="other_land_use"
        class="w-full form-input-clean"
        placeholder="Specify land use type"
    >
</div>
<script>
    const landUseSelect = document.getElementById('land_use_type');
    const otherWrapper = document.getElementById('other_land_use_wrapper');
    const otherInput = document.getElementById('other_land_use');

    function handleOtherLandUse() {
        if (landUseSelect.value === 'Other') {
            otherWrapper.classList.remove('hidden');
            otherInput.required = true;
        } else {
            otherWrapper.classList.add('hidden');
            otherInput.required = false;
            otherInput.value = '';
        }
    }

    landUseSelect.addEventListener('change', handleOtherLandUse);

    // Replace ONLY when "Other" is selected
    landUseSelect.closest('form').addEventListener('submit', function () {
        if (landUseSelect.value === 'Other' && otherInput.value.trim() !== '') {
            landUseSelect.value = otherInput.value.trim();
        }
    });

    // Edit / old() handling — ONLY if value was "Other"
    @if(old('land_use_type', $fileIndexing->land_use_type ?? '') === 'Other')
        handleOtherLandUse();
    @endif
</script>

                                    <div>
                                        <label for="tp_no" class="form-label-clean">TP Number</label>
                                        <input type="text" id="tp_no" name="tp_no"
                                            value="{{ old('tp_no', $fileIndexing->tp_no) }}" class="w-full form-input-clean uppercase"
                                            placeholder="TP NUMBER">
                                    </div>

                                    <div>
                                        <label for="lpkn_no" class="form-label-clean">LPKN Number</label>
                                        <input type="text" id="lpkn_no" name="lpkn_no"
                                            value="{{ old('lpkn_no', $fileIndexing->lpkn_no) }}" class="w-full form-input-clean uppercase"
                                            placeholder="LPKN NUMBER">
                                    </div>

                                    <!-- Kangis FileNo Placeholder (shown only when KANGIS registry is selected) -->
                                    @php
                                        $isKangisRegistry = str_contains(
                                            strtoupper(old('general_registry', $fileIndexing->general_registry ?? '')),
                                            'KANGIS'
                                        );
                                        $existingKangisPlaceholder = old('kangis_fileno_placeholder', $fileIndexing->kangis_fileno_placeholder ?? '');
                                        $kangisPrefix = '';
                                        $kangisSerial = '';
                                        if ($existingKangisPlaceholder) {
                                            $parts = explode(' ', trim($existingKangisPlaceholder), 2);
                                            $kangisPrefix = $parts[0] ?? '';
                                            $kangisSerial = $parts[1] ?? '';
                                        }
                                    @endphp
                                    <div id="edit-kangis-placeholder-wrapper" class="sm:col-span-2{{ $isKangisRegistry ? '' : ' hidden' }}">
                                        <label class="form-label-clean">
                                            Kangis FileNo Placeholder
                                            <span class="text-red-500">*</span>
                                            <span class="ml-1 text-xs font-normal text-gray-400">(e.g. KNML 01, MLKN 001)</span>
                                        </label>
                                        <input type="hidden" id="edit-kangis-fileno-placeholder" name="kangis_fileno_placeholder"
                                            value="{{ $existingKangisPlaceholder }}">
                                        <div class="flex gap-2">
                                            <select id="edit-kangis-fileno-prefix"
                                                class="w-36 shrink-0 form-input-clean font-mono">
                                                <option value="">Select Prefix</option>
                                                <option value="KNML" {{ $kangisPrefix === 'KNML' ? 'selected' : '' }}>KNML</option>
                                               
                                                <option value="MLKN" {{ $kangisPrefix === 'MLKN' ? 'selected' : '' }}>MLKN</option>
                                                <option value="KNGP" {{ $kangisPrefix === 'KNGP' ? 'selected' : '' }}>KNGP</option>
                                            </select>
                                            <input type="text" id="edit-kangis-fileno-serial"
                                                class="flex-1 form-input-clean font-mono uppercase"
                                                placeholder="e.g. 01, 001, 1234"
                                                inputmode="numeric"
                                                autocomplete="off"
                                                value="{{ $kangisSerial }}">
                                        </div>
                                        <p id="edit-kangis-placeholder-preview" class="mt-1 text-xs text-gray-400{{ ($kangisPrefix && $kangisSerial) ? '' : ' hidden' }}">
                                            Value: <strong id="edit-kangis-placeholder-preview-value" class="font-mono text-gray-600">{{ $existingKangisPlaceholder }}</strong>
                                        </p>
                                        <div id="edit-kangis-placeholder-feedback" class="hidden mt-2 flex items-center gap-2 text-xs rounded px-3 py-1.5 border">
                                            <i data-lucide="info" class="h-3.5 w-3.5"></i>
                                            <span id="edit-kangis-placeholder-feedback-text"></span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Has New KANGIS FileNo (KN Series) -->
                            @php
                                $existingNewKangis = old('new_kangis_file_no', $fileIndexing->new_kangis_file_no ?? '');
                                $hasNewKangisChecked = trim((string) $existingNewKangis) !== '';
                            @endphp
                            <div class="form-section-clean">
                                <div class="form-section-header">
                                    <i data-lucide="file-badge" class="h-5 w-5 text-gray-500"></i>
                                    New KANGIS FileNo
                                </div>

                                <div class="flex items-center gap-2">
                                    <input type="checkbox" id="has-new-kangis-fileno" name="has_new_kangis_fileno" value="1"
                                        {{ $hasNewKangisChecked ? 'checked' : '' }}
                                        class="h-4 w-4 rounded border-gray-300 text-purple-600 focus:ring-purple-500">
                                    <label for="has-new-kangis-fileno" class="text-sm font-medium text-gray-700 cursor-pointer">
                                        Has New KANGIS FileNo <span class="text-gray-400 font-normal">(KN Series)</span>
                                    </label>
                                </div>

                                <div id="has-new-kangis-fields" class="{{ $hasNewKangisChecked ? '' : 'hidden' }} mt-3">
                                    <label class="form-label-clean">
                                        New KANGIS File No <span class="text-red-500">*</span>
                                    </label>
                                    <div class="flex gap-2">
                                        <input type="text" id="new_kangis_file_no_display" readonly
                                            value="{{ $existingNewKangis }}"
                                            class="flex-grow form-input-clean font-mono font-bold text-purple-900 bg-purple-50 uppercase cursor-pointer"
                                            placeholder="Click Select to choose a New KANGIS file">
                                        <button type="button" id="new_kangis_file_no_select_btn"
                                            class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md text-white bg-purple-600 hover:bg-purple-700"
                                            style="white-space: nowrap;">
                                            Select
                                        </button>
                                        <button type="button" id="new_kangis_file_no_clear_btn"
                                            class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md text-gray-700 bg-white border border-gray-200 hover:bg-gray-50"
                                            style="white-space: nowrap;">
                                            Clear
                                        </button>
                                    </div>
                                    <input type="hidden" name="new_kangis_file_no" id="new_kangis_file_no_hidden" value="{{ $existingNewKangis }}">
                                    <p id="has-new-kangis-error" class="hidden mt-1.5 text-xs text-red-600">
                                        Please select the New KANGIS File No.
                                    </p>
                                    <p id="kn-fileno-status" class="mt-1 text-xs {{ $hasNewKangisChecked ? 'text-purple-700 font-semibold' : 'text-gray-500' }}">{{ $hasNewKangisChecked ? '✓ Selected: ' . $existingNewKangis : '' }}</p>
                                    <p class="mt-1 text-xs text-gray-400">
                                        On update this KN file is indexed on the fly — a KANGIS Registry record is created if it doesn't exist yet, or updated in place if it does (never a duplicate).
                                    </p>
                                </div>
                            </div>

                            <!-- Location & Administrative Section -->
                            <div class="form-section-clean">
                                <div class="form-section-header">
                                    <i data-lucide="clipboard-list" class="h-5 w-5 text-gray-500"></i>
                                    Registry Information
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4">

                                    <!-- General Registry (Dropdown) -->
                                    <div>
                                        <label for="general_registry" class="form-label-clean">
                                            General Registry <span class="text-red-500">*</span>
                                        </label>
                                        <select id="general_registry" name="general_registry" class="w-full form-input-clean" required>
                                            <option value="">Select General Registry</option>
                                            @foreach($registries as $registry)
                                                <option value="{{ $registry->name }}" {{ old('general_registry', $fileIndexing->general_registry) == $registry->name ? 'selected' : '' }}>
                                                    {{ $registry->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <!-- Registry (Input) -->
                                    <div>
                                        <label for="registry" class="form-label-clean">Registry</label>
                                        <input type="text" id="registry" name="registry" 
                                            value="{{ old('registry', $fileIndexing->registry) }}" 
                                            class="w-full form-input-clean" 
                                            placeholder="Enter Registry Name">
                                    </div>

                                    <!-- Physical Registry (Dropdown) -->
                                    <div>
                                        <label for="physical_registry" class="form-label-clean">
                                            Physical Registry <span class="text-red-500">*</span>
                                        </label>
                                        <select id="physical_registry" name="physical_registry" class="w-full form-input-clean" required>
                                            <option value="">Select Physical Registry</option>
                                            @foreach($physicalRegistries as $pHregistry)
                                                <option value="{{ $pHregistry->name }}" {{ old('physical_registry', $fileIndexing->physical_registry) == $pHregistry->name ? 'selected' : '' }}>
                                                    {{ $pHregistry->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div>
                                        <label for="registry_batch_no" class="form-label-clean">Registry Batch No</label>
                                        <input type="text" id="registry_batch_no" name="registry_batch_no"
                                            value="{{ old('registry_batch_no', $fileIndexing->registry_batch_no) }}"
                                            class="w-full form-input-clean">
                                    </div>

                                    <div>
                                        <label for="batch_no" class="form-label-clean">MDC Batch Number</label>
                                        <input type="text" id="batch_no" name="batch_no"
                                            value="{{ old('batch_no', $fileIndexing->batch_no) }}"
                                            class="w-full form-input-clean">
                                    </div>

                                    <div>
                                        <label for="tracking_id" class="form-label-clean">Tracking ID</label>
                                        <div class="relative">
                                            <input type="text" id="tracking_id" name="tracking_id"
                                                value="{{ old('tracking_id', $fileIndexing->tracking_id) }}"
                                                class="w-full form-input-clean text-red-500 bg-red-50 border-red-200 font-bold" readonly>
                                            <div class="absolute right-3 top-2.5">
                                                <i data-lucide="lock" class="h-4 w-4 text-red-400"></i>
                                            </div>
                                        </div>
                                    </div>

                                </div>
                            </div>
                        </div>

                </div>

            <!-- Property Location Map (full-width) -->
            <div class="form-section-clean mt-6" id="property-map-section">
                <div class="form-section-header">
                    <i data-lucide="map" class="h-5 w-5 text-gray-500"></i>
                    Property Location Map
                </div>

                <div id="edit-map" style="height: 420px; width: 100%; display: none;"
                    class="rounded-md overflow-hidden border border-gray-200"></div>

                <div id="edit-map-empty"
                    class="flex flex-col items-center justify-center gap-2 rounded-md border border-dashed border-gray-300 bg-gray-50 text-gray-400"
                    style="height: 220px;">
                    <i data-lucide="map-pin" class="h-8 w-8"></i>
                    <p class="text-sm">No location pinned yet.</p>
                    <p class="text-xs">Fill in the <strong>Property Location (Address Builder)</strong> above, then click <strong>Apply &amp; Pin on Map</strong>.</p>
                </div>

                <p id="edit-map-hint" class="mt-2 text-xs text-gray-500" style="display: none;">
                    <i data-lucide="move" class="inline h-3.5 w-3.5"></i>
                    Drag the pin or click the map to fine-tune the exact location.
                </p>
            </div>

            @php
                // ---- Related File Number backfill ----
                // related_fileno column may hold a JSON array, a plain string, or null.
                $rawRelatedFileno = $fileIndexing->related_fileno ?? null;
                $relatedFilenoList = [];
                if (is_array($rawRelatedFileno)) {
                    $relatedFilenoList = $rawRelatedFileno;
                } elseif (is_string($rawRelatedFileno) && trim($rawRelatedFileno) !== '') {
                    $decodedRelatedFileno = json_decode($rawRelatedFileno, true);
                    $relatedFilenoList = is_array($decodedRelatedFileno) ? $decodedRelatedFileno : [$rawRelatedFileno];
                }
                $relatedFilenoList = array_values(array_filter(array_map(function ($v) {
                    return trim((string) $v);
                }, $relatedFilenoList), function ($v) {
                    return $v !== '';
                }));

                // Existing file_indexing_links details attached by FileIndexingController::edit().
                $existingRelatedDetails = collect($fileIndexing->related_details ?? [])->values()->all();

                // Older records may have links without the JSON column (or vice versa) —
                // union both sources so every linked file number gets a row.
                foreach ($existingRelatedDetails as $existingRelatedDetail) {
                    $linkedFileNo = trim((string) ($existingRelatedDetail['file_number'] ?? ''));
                    if ($linkedFileNo !== '' && !in_array($linkedFileNo, $relatedFilenoList, true)) {
                        $relatedFilenoList[] = $linkedFileNo;
                    }
                }

                $hasRelatedFiles = count($relatedFilenoList) > 0;
                $relatedFilenoRows = $hasRelatedFiles ? $relatedFilenoList : [''];
            @endphp

            <!-- Related File Number Card -->
            <div class="form-section-clean mt-6 border-l-4 border-amber-400">
                <div class="form-section-header flex justify-between items-center">
                    <div class="flex items-center gap-2">
                        <i data-lucide="link" class="h-5 w-5 text-amber-600"></i>
                        Related File Number
                    </div>
                    <div class="flex items-center gap-2 bg-amber-50 px-3 py-1.5 rounded-full border border-amber-100">
                        <input type="checkbox" id="has-related-file" name="has_related_file"
                            class="checkbox-clean" {{ $hasRelatedFiles ? 'checked' : '' }}>
                        <label for="has-related-file" class="text-xs font-bold text-amber-700 cursor-pointer">Has Related File?</label>
                    </div>
                </div>

                <div id="related-files-wrapper" class="{{ $hasRelatedFiles ? '' : 'hidden' }} space-y-4">
                    @foreach($relatedFilenoRows as $index => $relFileno)
                        <div class="related-file-row flex gap-2">
                            <div class="flex-grow">
                                <input type="text" name="related_fileno[]" value="{{ $relFileno }}"
                                    class="related-file-input block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm bg-gray-50"
                                    placeholder="Select related file number" readonly>
                            </div>
                            <div class="flex gap-2">
                                <button type="button" class="select-related-btn inline-flex items-center px-3 py-2 border border-indigo-600 text-indigo-600 rounded-md hover:bg-indigo-50" title="Select File Number">
                                    <i data-lucide="search" class="h-4 w-4 text-indigo-600"></i>
                                </button>
                                <button type="button" class="clear-related-btn inline-flex items-center px-3 py-2 border border-red-600 text-red-600 rounded-md hover:bg-red-50" title="Remove/Clear">
                                    <i data-lucide="{{ $index === 0 ? 'eraser' : 'trash-2' }}" class="h-4 w-4 text-red-600"></i>
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-4 flex justify-between items-center">
                    <button type="button" id="add-related-file-btn" class="{{ $hasRelatedFiles ? '' : 'hidden' }} inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        <i data-lucide="plus" class="h-4 w-4 mr-2 text-gray-700"></i>
                        Add Another
                    </button>

                    <button type="button" id="open-related-details-btn" class="{{ $hasRelatedFiles ? '' : 'hidden' }} inline-flex items-center px-3 py-2 border border-blue-600 text-blue-600 font-medium rounded-md hover:bg-blue-50 transition-colors">
                        <i data-lucide="list-plus" class="h-4 w-4 mr-2 text-blue-600"></i>
                        Enter Related Details
                    </button>
                </div>

                {{-- Hidden related_details[i][field] inputs, kept in sync by JS so the
                     update endpoint recreates file_indexing_links without losing details. --}}
                <div id="related-details-hidden" class="hidden"></div>
            </div>

            @php
                $hasRofoDefault = $fileIndexing->has_rofo ?? 0;
                $hasRofo = (int) old('has_rofo', $hasRofoDefault);
            @endphp

            <!-- RoFO Details Section -->
            <div class="form-section-clean mt-6 border-l-4 border-green-400">
                <div class="form-section-header flex justify-between items-center">
                    <div class="flex items-center gap-2">
                        <i data-lucide="file-text" class="h-5 w-5 text-green-500"></i>
                        Right of Occupancy (RoFO) Details
                    </div>
                    <div class="flex items-center gap-2 bg-green-50 px-3 py-1.5 rounded-full border border-green-100">
                        <input type="hidden" name="has_rofo" value="0">
                        <input type="checkbox" id="has_rofo" name="has_rofo" value="1"
                            {{ $hasRofo ? 'checked' : '' }} class="checkbox-clean">
                        <label for="has_rofo" class="text-xs font-bold text-green-700 cursor-pointer">Has RoFO</label>
                    </div>
                </div>

                <div id="rofo-fields" class="space-y-6 {{ !$hasRofo ? 'hidden' : '' }}">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="rofo_instrument_type" class="form-label-clean">Instrument Type</label>
                            <select id="rofo_instrument_type" class="w-full form-input-clean bg-gray-50" disabled>
                                <option value="Right of Occupancy" selected>Right of Occupancy</option>
                            </select>
                            <input type="hidden" name="rofo_instrument_type" value="Right of Occupancy">
                        </div>
                        <div>
                            <label for="rofo_date" class="form-label-clean">RoFO Date</label>
                            <input type="date" id="rofo_date" name="rofo_date"
                                class="w-full form-input-clean"
                                value="{{ old('rofo_date', $fileIndexing->rofo_date ?? '') }}">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="rofo_number" class="form-label-clean">RoFO Number</label>
                            <input type="text" id="rofo_number" name="rofo_number"
                                class="w-full form-input-clean bg-gray-50"
                                placeholder="Auto-populated from file number"
                                value="{{ old('rofo_number', $fileIndexing->rofo_number ?? $fileIndexing->file_number ?? '') }}"
                                readonly>
                        </div>
                        <div>
                            <label for="rofo_file_number" class="form-label-clean">File Number</label>
                            <input type="text" id="rofo_file_number" name="rofo_file_number"
                                class="w-full form-input-clean bg-gray-50"
                                placeholder="Auto-populated from file number"
                                value="{{ old('rofo_file_number', $fileIndexing->rofo_file_number ?? $fileIndexing->file_number ?? '') }}"
                                readonly>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="rofo_land_use" class="form-label-clean">Land Use</label>
                            <select id="rofo_land_use" name="rofo_land_use" class="w-full form-input-clean">
                                <option value="">Select Land Use</option>
                                @if(isset($landUseTypes))
                                    @foreach($landUseTypes as $key => $label)
                                        <option value="{{ $key }}" {{ old('rofo_land_use', $fileIndexing->rofo_land_use ?? '') == $key ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                @endif
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="rofo_grantor" class="form-label-clean">Party 1 / Grantor</label>
                            <input type="text" id="rofo_grantor" name="rofo_grantor"
                                class="w-full form-input-clean bg-gray-50"
                                value="{{ old('rofo_grantor', $fileIndexing->rofo_grantor ?? 'KANO STATE GOVERNMENT') }}"
                                readonly>
                        </div>
                        <div>
                            <label for="rofo_grantee" class="form-label-clean">Party 2 / Grantee</label>
                            <input type="text" id="rofo_grantee" name="rofo_grantee"
                                class="w-full form-input-clean" placeholder="Enter grantee name"
                                value="{{ old('rofo_grantee', $fileIndexing->rofo_grantee ?? '') }}"
                                style="text-transform: uppercase;">
                        </div>
                    </div>
                </div>
            </div>

            <!-- CofO Details Section -->
            <div class="form-section-clean mt-6 border-l-4 border-blue-400">
                <div class="form-section-header flex justify-between items-center">
                    <div class="flex items-center gap-2">
                        <i data-lucide="award" class="h-5 w-5 text-blue-500"></i>
                        Certificate of Occupancy (CofO) Details
                    </div>
                    <div class="flex items-center gap-2 bg-blue-50 px-3 py-1.5 rounded-full border border-blue-100">
                        <input type="hidden" name="has_cofo" value="0">
                        <input type="checkbox" id="has_cofo" name="has_cofo" value="1" 
                            {{ $hasCofo ? 'checked' : '' }} class="checkbox-clean">
                        <label for="has_cofo" class="text-xs font-bold text-blue-700 cursor-pointer">Include details?</label>
                    </div>
                </div>

                <div id="cofo-fields" class="space-y-6 {{ !$hasCofo ? 'hidden' : '' }}">
                    <div class="text-sm text-gray-500 mb-4">
                        When enabled, these fields update the CofO staging table so downstream teams always see the latest
                        registry details.
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="cofo_instrument_type" class="form-label-clean">Instrument Type</label>
                            <input type="text" id="cofo_instrument_type_display" class="w-full form-input-clean bg-gray-50"
                                value="Certificate of Occupancy" readonly>
                            <input type="hidden" id="cofo_instrument_type" name="cofo_instrument_type" value="Certificate of Occupancy">
                        </div>
                        <div>
                            <label for="cofo_type" class="form-label-clean">CofO Type</label>
                            @php $cofoTypeValue = $cofoValue('cofo_type', null); @endphp
                            <select id="cofo_type" name="cofo_type" class="w-full form-input-clean">
                                <option value="" {{ !$cofoTypeValue ? 'selected' : '' }}>Select CofO Type</option>
                                <option value="Old CofO (Ministry)" {{ $cofoTypeValue === 'Old CofO (Ministry)' ? 'selected' : '' }}>Old CofO (Ministry)</option>
                                <option value="KANGIS CofO" {{ $cofoTypeValue === 'KANGIS CofO' ? 'selected' : '' }}>KANGIS CofO</option>
                                <option value="New KANGIS CofO" {{ $cofoTypeValue === 'New KANGIS CofO' ? 'selected' : '' }}>New KANGIS CofO</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label-clean">Registration Serial No</label>
                            <input type="text" id="cofo_serial_no" name="cofo_serial_no"
                                class="w-full form-input-clean font-bold text-blue-600" placeholder="e.g. 12"
                                value="{{ $cofoValue('cofo_serial_no', null) }}">
                        </div>
                        <div>
                            <label class="form-label-clean">Page Number</label>
                            <input type="text" id="cofo_page_no" name="cofo_page_no" class="w-full form-input-clean bg-gray-50"
                                value="{{ $cofoValue('cofo_page_no', null) }}" readonly>
                            <p class="text-[10px] text-gray-500 mt-1">Mirrors serial number for registry consistency.</p>
                        </div>
                        <div>
                            <label class="form-label-clean">Volume Number</label>
                            <input type="text" id="cofo_vol_no" name="cofo_vol_no" class="w-full form-input-clean"
                                placeholder="e.g. 150" value="{{ $cofoValue('cofo_vol_no', null) }}">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="form-label-clean">Lease Period</label>
                            <div class="flex gap-2">
                                <input type="number" min="0" id="cofo_period" name="cofo_period"
                                    class="w-full form-input-clean" placeholder="99"
                                    value="{{ $cofoValue('cofo_period', null) }}">
                                <select id="cofo_period_unit" name="cofo_period_unit" class="form-input-clean w-32 bg-gray-50">
                                    @php
                                        $periodUnit = $cofoValue('cofo_period_unit', 'Years');
                                    @endphp
                                    <option value="Years" {{ $periodUnit === 'Years' ? 'selected' : '' }}>Years</option>
                                    <option value="Months" {{ $periodUnit === 'Months' ? 'selected' : '' }}>Months</option>
                                    <option value="Days" {{ $periodUnit === 'Days' ? 'selected' : '' }}>Days</option>
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-3 gap-4">
                            <div>
                                <label for="transaction_date" class="form-label-clean">Transaction Date</label>
                                <input type="date" id="transaction_date" name="transaction_date"
                                    class="w-full form-input-clean" value="{{ $cofoValue('transaction_date', null) }}">
                            </div>


                            
 

                            
                            <div>
                                <label for="cofo_deeds_date" class="form-label-clean">Deeds Date</label>
                                <input type="date" id="cofo_deeds_date" name="cofo_deeds_date"
                                    class="w-full form-input-clean" value="{{ $cofoValue('cofo_deeds_date', null) }}">
                            </div>
                            <div>
                                <label for="cofo_deeds_time" class="form-label-clean">Deeds Time</label>
                                <input type="time" id="cofo_deeds_time" name="cofo_deeds_time"
                                    class="w-full form-input-clean" value="{{ $cofoValue('cofo_deeds_time', null) }}">
                            </div>
                        </div>

                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="cofo_first_party" class="form-label-clean">Party 1 / Grantor</label>
                            <input type="text" id="cofo_first_party" name="cofo_first_party" class="w-full form-input-clean uppercase bg-gray-50"
                                value="KANO STATE GOVERNMENT" readonly>
                        </div>
                        <div>
                            <label for="cofo_second_party" class="form-label-clean">Party 2 / Grantee</label>
                            <input type="text" id="cofo_second_party" name="cofo_second_party"
                                class="w-full form-input-clean uppercase" placeholder="ENTER NAME"
                                value="{{ $cofoValue('cofo_second_party', null) }}">
                        </div>
                    </div>
                </div>


                <!-- Add JavaScript to update Location in real-time -->
                <script>
                    document.addEventListener('DOMContentLoaded', function () {
                        const stateField = document.getElementById('state');
                        const lgaField = document.getElementById('lga');
                        const districtField = document.getElementById('district');
                        const districtSpecifiedField = document.getElementById('district_specified');
                        const plotField = document.getElementById('plot_number');
                        const locationPreview = document.getElementById('location-preview');
                        const locationTextarea = document.getElementById('location');

                        function updateLocationPreview() {
                            const state = stateField ? stateField.value.trim() : 'Kano';
                            const lga = lgaField ? lgaField.value.trim() : '';
                            const plot = plotField ? plotField.value.trim() : '';
                            let district = districtField ? districtField.value.trim() : '';

                            // If "Others" is selected, use the specified value
                            if ((district.toLowerCase() === 'others' || district.toLowerCase() === 'other') && districtSpecifiedField) {
                                district = districtSpecifiedField.value.trim();
                            }

                            if (plot || state || lga || district) {
                                let parts = [];

                                // Address order: PLOT NUMBER, DISTRICT, LGA, STATE
                                if (plot) parts.push(plot);
                                if (district) parts.push(district);
                                if (lga) parts.push(lga);
                                if (state) parts.push(state);

                                const previewText = parts.join(', ').toUpperCase();

                                if (locationPreview) {
                                    locationPreview.textContent = previewText;
                                    locationPreview.classList.toggle('hidden', previewText === '');
                                }

                                // Auto-update the textarea
                                if (locationTextarea) {
                                    locationTextarea.value = previewText;
                                }
                            } else {
                                if (locationPreview) {
                                    locationPreview.textContent = '';
                                    locationPreview.classList.add('hidden');
                                }
                                if (locationTextarea) {
                                    locationTextarea.value = '';
                                }
                            }
                        }

                        // Add event listeners
                        if (lgaField) lgaField.addEventListener('change', updateLocationPreview);
                        if (districtField) districtField.addEventListener('change', updateLocationPreview);
                        if (districtSpecifiedField) districtSpecifiedField.addEventListener('input', updateLocationPreview);
                        if (plotField) plotField.addEventListener('input', updateLocationPreview);
                        
                        // Initialize on page load
                        updateLocationPreview();
                    });
                </script>

                <!-- File Attributes Section  hidden for now-->
                <div class="form-section-clean mt-6 hidden">
                    <div class="form-section-header">
                        <i data-lucide="settings" class="h-5 w-5 text-gray-500"></i>
                        File Attributes
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <div class="flex items-center gap-3">
                            <input type="hidden" name="is_merged" value="0">
                            <input type="checkbox" id="is_merged" name="is_merged" value="1" {{ old('is_merged', $fileIndexing->is_merged) ? 'checked' : '' }} class="checkbox-clean">
                            <label for="is_merged" class="form-label-clean mb-0 cursor-pointer">
                                Is Merged
                            </label>
                        </div>

                        <div class="flex items-center gap-3">
                            <input type="hidden" name="has_transaction" value="0">
                            <input type="checkbox" id="has_transaction" name="has_transaction" value="1" {{ old('has_transaction', $fileIndexing->has_transaction) ? 'checked' : '' }}
                                class="checkbox-clean">
                            <label for="has_transaction" class="form-label-clean mb-0 cursor-pointer">
                                Has Transaction
                            </label>
                        </div>

                        <div class="flex items-center gap-3">
                            <input type="hidden" name="is_problematic" value="0">
                            <input type="checkbox" id="is_problematic" name="is_problematic" value="1" {{ old('is_problematic', $fileIndexing->is_problematic) ? 'checked' : '' }}
                                class="checkbox-clean">
                            <label for="is_problematic" class="form-label-clean mb-0 cursor-pointer">
                                Is Problematic
                            </label>
                        </div>

                        <div class="flex items-center gap-3">
                            <input type="hidden" name="is_co_owned_plot" value="0">
                            <input type="checkbox" id="is_co_owned_plot" name="is_co_owned_plot" value="1" {{ old('is_co_owned_plot', $fileIndexing->is_co_owned_plot) ? 'checked' : '' }}
                                class="checkbox-clean">
                            <label for="is_co_owned_plot" class="form-label-clean mb-0 cursor-pointer">
                                Co-owned Plot
                            </label>
                        </div>
                    </div>
                </div>



                <!-- Bill Balance & Ground Rent -->
                <div class="form-section-clean mt-6">
                    @include('fileindexing.addons.partials.sections.billing_ground_rent')
                </div>

                <!-- Form Actions -->
                <div class="flex items-center justify-between pt-8 mt-8 border-t border-gray-200">
                    <div class="text-sm text-gray-500">
                        <i data-lucide="shield-check" class="h-4 w-4 inline mr-1"></i>
                        Changes will be saved securely
                    </div>
                    <div class="flex gap-3">
                        <a href="{{ route('fileindexing.index') }}" class="btn-clean btn-secondary-clean">
                            <i data-lucide="x" class="h-4 w-4"></i>
                            Cancel
                        </a>
                        <button type="submit" class="btn-clean btn-primary-clean" id="update-btn">
                            <i data-lucide="save" class="h-4 w-4"></i>
                            <span id="btn-text">Update File</span>
                        </button>
                    </div>
                </div>
                </form>

                <!-- Quick Actions -->
                <div class="mt-8 grid grid-cols-1 md:grid-cols-3 gap-4">
                    {{-- <a href="{{ route('fileindexing.show', $fileIndexing->id) }}"
                        class="flex items-center gap-3 p-4 bg-white border border-gray-200 rounded-lg hover:border-gray-300 hover:shadow-sm transition-all">
                        <div class="flex-shrink-0 w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center">
                            <i data-lucide="eye" class="h-5 w-5 text-gray-600"></i>
                        </div>
                        <div>
                            <p class="font-medium text-gray-900">View Details</p>
                            <p class="text-xs text-gray-500">View full file information</p>
                        </div>
                    </a> --}}
                    <a href="{{ route('fileindexing.batch-tracking-sheet') }}?files={{ $fileIndexing->id }}"
                        id="tracking-sheet-link" target="_blank"
                        class="flex items-center gap-3 p-4 bg-white border border-gray-200 rounded-lg hover:border-gray-300 hover:shadow-sm transition-all">
                        <div class="flex-shrink-0 w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center">
                            <i data-lucide="file-text" class="h-5 w-5 text-gray-600"></i>
                        </div>
                        <div>
                            <p class="font-medium text-gray-900">Tracking Sheet</p>
                            <p class="text-xs text-gray-500" id="tracking-sheet-status">
                                @if($fileIndexing->batch_generated)
                                    Last generated: {{ $fileIndexing->formatted_batch_generated_at ?? 'Unknown' }}
                                @else
                                    Generate tracking document
                                @endif
                            </p>
                        </div>
                    </a>

                    @if(!empty($fileIndexing->new_kangis_file_no))
                    <a href="{{ route('fileindexing.kangis-tracking-sheet') }}?files={{ $fileIndexing->id }}"
                        target="_blank"
                        class="flex items-center gap-3 p-4 bg-white border border-amber-200 rounded-lg hover:border-amber-400 hover:shadow-sm transition-all">
                        <div class="flex-shrink-0 w-10 h-10 bg-amber-50 rounded-lg flex items-center justify-center">
                            <i data-lucide="file-badge" class="h-5 w-5 text-amber-700"></i>
                        </div>
                        <div>
                            <p class="font-medium text-amber-900">KANGIS Tracking Sheet</p>
                            <p class="text-xs text-amber-700">KN: {{ $fileIndexing->new_kangis_file_no }}</p>
                        </div>
                    </a>
                    @endif

                    <a href="{{ route('fileindexing.index') }}"
                        class="flex items-center gap-3 p-4 bg-white border border-gray-200 rounded-lg hover:border-gray-300 hover:shadow-sm transition-all">
                        <div class="flex-shrink-0 w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center">
                            <i data-lucide="list" class="h-5 w-5 text-gray-600"></i>
                        </div>
                        <div>
                            <p class="font-medium text-gray-900">All Files</p>
                            <p class="text-xs text-gray-500">Return to file list</p>
                        </div>
                    </a>
                </div>
            </div>
            </div>
        </div>

        <!-- Footer -->
        @include('admin.footer')
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('edit-file-form');
            const updateBtn = document.getElementById('update-btn');
            const btnText = document.getElementById('btn-text');
            const alertContainer = document.getElementById('alert-container');

            // Check if required elements exist
            if (!form) {
                console.error('Edit form not found');
                return;
            }

            if (!updateBtn) {
                console.error('Update button not found');
                return;
            }

            if (!alertContainer) {
                console.error('Alert container not found');
                return;
            }

            const cofoFieldsWrapper = document.getElementById('cofo-fields');
            const hasCofoCheckbox = document.getElementById('has_cofo');
            const cofoSerialInput = document.getElementById('cofo_serial_no');
            const cofoPageInput = document.getElementById('cofo_page_no');

            const syncCofoPageNumber = () => {
                if (cofoSerialInput && cofoPageInput && cofoPageInput.hasAttribute('readonly')) {
                    cofoPageInput.value = cofoSerialInput.value || '';
                }
            };

            if (cofoSerialInput && cofoPageInput) {
                cofoSerialInput.addEventListener('input', syncCofoPageNumber);
                if (!cofoPageInput.value) {
                    syncCofoPageNumber();
                }
            }

            if (hasCofoCheckbox && cofoFieldsWrapper) {
                const cofoFieldInputs = cofoFieldsWrapper.querySelectorAll('input, select, textarea');
                
                // Auto-enable if file number contains "cofo"
                const fileNumEl = document.getElementById('file_number');
                const fileNumHasCofo = fileNumEl && fileNumEl.value && fileNumEl.value.toLowerCase().includes('cofo');
                if (fileNumHasCofo) {
                    hasCofoCheckbox.checked = true;
                }

                const toggleCofOFields = () => {
                    const shouldShow = hasCofoCheckbox.checked || (fileNumEl && fileNumEl.value && fileNumEl.value.toLowerCase().includes('cofo'));
                    cofoFieldsWrapper.classList.toggle('hidden', !shouldShow);
                    cofoFieldInputs.forEach(element => {
                        element.disabled = !shouldShow;
                    });
                    if (shouldShow) {
                        syncCofoPageNumber();
                    }
                };

                toggleCofOFields();
                hasCofoCheckbox.addEventListener('change', toggleCofOFields);
                if (fileNumEl) {
                    fileNumEl.addEventListener('input', toggleCofOFields);
                    fileNumEl.addEventListener('change', toggleCofOFields);
                }
            }

            // RoFO toggle
            const rofoFieldsWrapper = document.getElementById('rofo-fields');
            const hasRofoCheckbox = document.getElementById('has_rofo');

            if (hasRofoCheckbox && rofoFieldsWrapper) {
                const rofoFieldInputs = rofoFieldsWrapper.querySelectorAll('input, select, textarea');
                const toggleRofoFields = () => {
                    const shouldShow = hasRofoCheckbox.checked;
                    rofoFieldsWrapper.classList.toggle('hidden', !shouldShow);
                    rofoFieldInputs.forEach(element => {
                        element.disabled = !shouldShow;
                    });
                    // Auto-populate file number fields when toggled on
                    if (shouldShow) {
                        const fileNumber = document.getElementById('file_number');
                        const rofoNumber = document.getElementById('rofo_number');
                        const rofoFileNumber = document.getElementById('rofo_file_number');
                        if (fileNumber && rofoNumber && !rofoNumber.value) {
                            rofoNumber.value = fileNumber.value;
                        }
                        if (fileNumber && rofoFileNumber && !rofoFileNumber.value) {
                            rofoFileNumber.value = fileNumber.value;
                        }
                    }
                };

                toggleRofoFields();
                hasRofoCheckbox.addEventListener('change', toggleRofoFields);
            }

            // Has New KANGIS FileNo (KN Series) — pick via the global file-number modal.
            // On update the controller indexes the KN file on the fly: it creates the
            // KANGIS Registry record if missing, or updates the existing one (never a dup).
            (function initEditNewKangis() {
                const chk = document.getElementById('has-new-kangis-fileno');
                const fields = document.getElementById('has-new-kangis-fields');
                const knDisplay = document.getElementById('new_kangis_file_no_display');
                const knHidden = document.getElementById('new_kangis_file_no_hidden');
                const knStatus = document.getElementById('kn-fileno-status');
                const errorEl = document.getElementById('has-new-kangis-error');
                const selectBtn = document.getElementById('new_kangis_file_no_select_btn');
                const clearBtn = document.getElementById('new_kangis_file_no_clear_btn');
                if (!chk || !fields || !knDisplay || !knHidden) return;

                function clearKn() {
                    knDisplay.value = '';
                    knHidden.value = '';
                    if (knStatus) { knStatus.textContent = ''; knStatus.className = 'mt-1 text-xs text-gray-500'; }
                    if (errorEl) errorEl.classList.add('hidden');
                    knDisplay.classList.remove('border-red-300');
                }

                chk.addEventListener('change', function () {
                    if (this.checked) {
                        fields.classList.remove('hidden');
                        if (window.lucide && lucide.createIcons) lucide.createIcons();
                    } else {
                        fields.classList.add('hidden');
                        clearKn();
                    }
                });

                function openPicker() {
                    if (!window.GlobalFileNoModal) { console.warn('GlobalFileNoModal not available'); return; }
                    GlobalFileNoModal.open({
                        initialTab: 'newkangis',
                        // Do NOT overwrite the main file_number — this is a separate KN reference.
                        autoPopulateGenericFields: false,
                        callback: function (data) {
                            if (!data || !data.fileNumber) return;
                            const val = String(data.fileNumber).trim();
                            knDisplay.value = val;
                            knHidden.value = val;
                            knDisplay.classList.remove('border-red-300');
                            if (errorEl) errorEl.classList.add('hidden');
                            if (knStatus) { knStatus.textContent = '✓ Selected: ' + val; knStatus.className = 'mt-1 text-xs text-purple-700 font-semibold'; }
                        }
                    });
                }

                if (selectBtn) selectBtn.addEventListener('click', openPicker);
                knDisplay.addEventListener('click', openPicker);
                if (clearBtn) clearBtn.addEventListener('click', clearKn);
            })();

            // Form validation
            function validateForm() {
                if (!form) {
                    console.error('Form element not found');
                    return false;
                }

                const requiredFields = form.querySelectorAll('[required]');
                let isValid = true;

                requiredFields.forEach(field => {
                    if (!field.value.trim()) {
                        field.classList.add('border-red-300');
                        isValid = false;
                    } else {
                        field.classList.remove('border-red-300');
                    }
                });

                return isValid;
            }

            // Show clean alert messages
            function showAlert(message, type = 'success') {
                const alertClass = type === 'success' ? 'alert-success' : 'alert-error';
                const icon = type === 'success' ? 'check-circle' : 'alert-circle';

                alertContainer.innerHTML = `
                    <div class="alert-clean ${alertClass}">
                        <i data-lucide="${icon}" class="h-4 w-4"></i>
                        <span>${message}</span>
                    </div>
                `;

                // Re-initialize icons
                if (typeof lucide !== 'undefined' && lucide.createIcons) {
                    lucide.createIcons();
                }

                // Auto-hide success messages
                if (type === 'success') {
                    setTimeout(() => {
                        alertContainer.innerHTML = '';
                    }, 4000);
                }

                // Scroll to alert
                alertContainer.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }

            // Form submission
            form.addEventListener('submit', function (e) {
                e.preventDefault();

                // Validate form
                if (!validateForm()) {
                    showAlert('Please fill in all required fields.', 'error');
                    return;
                }

                // Has New KANGIS FileNo ticked but no KN file chosen → block submit.
                const nkChk = document.getElementById('has-new-kangis-fileno');
                const nkHidden = document.getElementById('new_kangis_file_no_hidden');
                if (nkChk && nkChk.checked && !(nkHidden && nkHidden.value.trim())) {
                    showAlert('Please select the New KANGIS File No.', 'error');
                    document.getElementById('has-new-kangis-error')?.classList.remove('hidden');
                    document.getElementById('new_kangis_file_no_display')?.classList.add('border-red-300');
                    return;
                }

                // Show loading state
                updateBtn.disabled = true;
                if (btnText) {
                    btnText.textContent = 'Updating...';
                }
                const loadingIcon = updateBtn.querySelector('i');
                if (loadingIcon) {
                    loadingIcon.className = 'loading-spinner-clean';
                }

                // Submit form via AJAX
                const formData = new FormData(form);

                // Debug: Log form data
                console.log('Form action URL:', form.action);
                console.log('Form data entries:');
                for (let [key, value] of formData.entries()) {
                    console.log(key + ': ' + value);
                }

                // Add timeout to prevent hanging
                const controller = new AbortController();
                const timeoutId = setTimeout(() => {
                    controller.abort();
                    console.log('Request timed out after 30 seconds');
                }, 30000);

                fetch(form.action, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    signal: controller.signal
                })
                    .then(response => {
                        console.log('Response status:', response.status);
                        console.log('Response URL:', response.url);

                        if (response.ok) {
                            return response.json().catch(jsonError => {
                                console.error('JSON parsing failed:', jsonError);
                                return response.text().then(text => {
                                    console.log('Response as text:', text);
                                    throw new Error('Invalid JSON response: ' + text.substring(0, 200));
                                });
                            });
                        } else {
                            // Try to get error message from response
                            return response.text().then(text => {
                                console.log('Error response text:', text);
                                if (response.status === 404) {
                                    throw new Error('Route not found (404). Please check if the update route exists.');
                                }
                                throw new Error(`Server error: ${response.status} - ${text.substring(0, 200)}`);
                            });
                        }
                    })
                    .then(data => {
                        console.log('Received data:', data);

                        if (data.success) {
                            showAlert('File indexing updated successfully!', 'success');

                            // Reset form state and enable tracking sheet
                            hasUnsavedChanges = false;
                            updateTrackingSheetState(false);

                            // Update any dynamic content if needed
                            if (data.fileIndexing) {
                                // Update tracking sheet status text with latest batch info
                                const trackingSheetStatus = document.getElementById('tracking-sheet-status');
                                if (trackingSheetStatus && data.fileIndexing.batch_generated) {
                                    const batchDate = data.fileIndexing.batch_generated_at ?
                                        new Date(data.fileIndexing.batch_generated_at).toLocaleString('en-US', {
                                            month: 'short', day: 'numeric', year: 'numeric',
                                            hour: 'numeric', minute: '2-digit'
                                        }) : 'Unknown';
                                    trackingSheetStatus.textContent = `Last generated: ${batchDate}`;
                                    trackingSheetStatus.dataset.originalText = trackingSheetStatus.textContent;
                                }
                            }
                        } else {
                            showAlert('Error: ' + (data.message || 'Failed to update file indexing'), 'error');
                        }
                    })
                    .catch(error => {
                        clearTimeout(timeoutId);
                        console.error('Fetch error details:', error);

                        if (error.name === 'AbortError') {
                            showAlert('Request timed out. Please try again or check your network connection.', 'error');
                        } else {
                            showAlert('An error occurred while updating. Please check console for details.', 'error');
                        }
                    })
                    .finally(() => {
                        clearTimeout(timeoutId);
                        console.log('Resetting button state...');

                        // Reset button state
                        if (updateBtn) {
                            updateBtn.disabled = false;
                            console.log('Button enabled');
                        }

                        if (btnText) {
                            btnText.textContent = 'Update File';
                            console.log('Button text reset');
                        }

                        const icon = updateBtn ? updateBtn.querySelector('i') : null;
                        if (icon) {
                            icon.className = 'h-4 w-4';
                            icon.setAttribute('data-lucide', 'save');
                            console.log('Icon reset');
                        } else {
                            console.warn('Icon element not found in button');
                        }

                        // Re-initialize icons
                        if (typeof lucide !== 'undefined' && lucide.createIcons) {
                            lucide.createIcons();
                            console.log('Icons re-initialized');
                        }
                    });
            });

            // Input focus styling
            const inputs = form.querySelectorAll('input, select, textarea');
            inputs.forEach(input => {
                input.addEventListener('focus', function () {
                    this.parentElement.classList.add('focused');
                });

                input.addEventListener('blur', function () {
                    this.parentElement.classList.remove('focused');
                });
            });

            // Keyboard shortcut (Ctrl+S)
            document.addEventListener('keydown', function (e) {
                if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                    e.preventDefault();
                    form.dispatchEvent(new Event('submit'));
                }
            });

            // File Number Modal Integration
            const selectFileNumberBtn = document.getElementById('select-file-number-btn');
            const changeFileNumberBtn = document.getElementById('change-file-number-btn');
            const fileNumberInput = document.getElementById('file_number');
            let applyTrackingIdFromFileData = async function () {};
            // Tracks whether the *main* file-number modal is the one currently open.
            // The related-file selector reuses the same GlobalFileNoModal, which fires a
            // global `fileno-modal:applied` event on every apply. Without this guard the
            // backup listener below would clobber the main file number (and tracking id)
            // with the related file's values, causing a duplicate-key rename on save.
            let mainFileNoModalActive = false;

            if (fileNumberInput) {
                // Keep the field read-only: users must use the Change button + file number selector.
                fileNumberInput.setAttribute('readonly', 'readonly');
                fileNumberInput.setAttribute('aria-readonly', 'true');
                fileNumberInput.style.cursor = 'not-allowed';
                fileNumberInput.addEventListener('keydown', function (e) {
                    // Allow copy/select shortcuts and Tab, block everything else
                    const allowed = ['Tab', 'ArrowLeft', 'ArrowRight', 'Home', 'End'];
                    if (allowed.includes(e.key)) return;
                    if ((e.ctrlKey || e.metaKey) && ['a', 'c', 'x'].includes(e.key.toLowerCase())) return;
                    e.preventDefault();
                });
                fileNumberInput.addEventListener('paste', function (e) { e.preventDefault(); });
                fileNumberInput.addEventListener('drop', function (e) { e.preventDefault(); });
            }

            // Initialize file number modal integration
            function initFileNumberModal() {
                let trackingBadgeDelayTimer = null;

                async function resolveTrackingIdForFile(fileNumber, seedTrackingId = '') {
                    const initial = String(seedTrackingId || '').trim();
                    if (initial) {
                        return initial;
                    }

                    const selectedFile = String(fileNumber || '').trim();
                    if (!selectedFile) {
                        return '';
                    }

                    const registryValue =
                        document.getElementById('general_registry')?.value ||
                        document.getElementById('general-registry')?.value ||
                        '';

                    // First fallback: centralized file-number lookup API
                    try {
                        const lookupUrl = `/api/file-numbers/lookup?file_number=${encodeURIComponent(selectedFile)}&mlsf_no=${encodeURIComponent(selectedFile)}`;
                        const lookupResponse = await fetch(lookupUrl, {
                            method: 'GET',
                            headers: { 'Accept': 'application/json' },
                            credentials: 'same-origin'
                        });
                        const lookupJson = await lookupResponse.json().catch(() => null);
                        const lookupTracking =
                            lookupJson?.data?.tracking_id ||
                            lookupJson?.data?.trackingId ||
                            lookupJson?.data?.tra ||
                            '';
                        if (String(lookupTracking).trim()) {
                            return String(lookupTracking).trim();
                        }
                    } catch (err) {
                        console.warn('Tracking lookup API fallback failed:', err);
                    }

                    // Second fallback: grouping awaiting lookup (same source used by Create Indexing)
                    try {
                        const groupingUrl = `/api/grouping/awaiting/${encodeURIComponent(selectedFile)}?registry=${encodeURIComponent(registryValue || 'Lands Registry')}`;
                        const groupingResponse = await fetch(groupingUrl, {
                            method: 'GET',
                            headers: { 'Accept': 'application/json' },
                            credentials: 'same-origin'
                        });
                        const groupingJson = await groupingResponse.json().catch(() => null);
                        const groupingTracking =
                            groupingJson?.data?.tracking_id ||
                            groupingJson?.data?.trackingId ||
                            '';
                        if (String(groupingTracking).trim()) {
                            return String(groupingTracking).trim();
                        }
                    } catch (err) {
                        console.warn('Grouping fallback failed:', err);
                    }

                    return '';
                }

                // Helper: refresh the Tracking ID badge/input from fileData returned by the modal
                applyTrackingIdFromFileData = async function (fileData, options = {}) {
                    const delayMs = Number.isFinite(options.delayMs) ? options.delayMs : 1800;
                    const seedTrackingId = (fileData && (
                        fileData.tra ||
                        fileData.tracking_id ||
                        fileData.trackingId ||
                        fileData?.record?.tracking_id ||
                        fileData?.record?.trackingId ||
                        fileData?.record?.tra
                    )) || '';
                    const selectedFileNumber =
                        fileData?.fileNumber ||
                        fileData?.file_number ||
                        fileData?.record?.file_number ||
                        document.getElementById('file_number')?.value ||
                        '';
                    const newTra = await resolveTrackingIdForFile(selectedFileNumber, seedTrackingId);
                    const trackingInput = document.getElementById('tracking_id');

                    // Replace the badge area under the File Number input
                    const fileNumberField = document.getElementById('file_number');
                    if (!fileNumberField) return;
                    const fileNumberWrapper = fileNumberField.closest('.sm\\:col-span-2');
                    if (!fileNumberWrapper) return;

                    let badgeHost = fileNumberWrapper.querySelector('[data-tracking-id-badge]');
                    if (!badgeHost) {
                        badgeHost = document.createElement('div');
                        badgeHost.setAttribute('data-tracking-id-badge', '');
                        badgeHost.className = 'mt-2 flex flex-wrap items-center gap-2 text-xs';
                        // Insert after the flex row that holds the input + Change button
                        const flexRow = fileNumberField.closest('.flex');
                        if (flexRow && flexRow.parentNode) {
                            flexRow.parentNode.insertBefore(badgeHost, flexRow.nextSibling);
                        } else {
                            fileNumberWrapper.appendChild(badgeHost);
                        }
                    }

                    // Show a brief loading state before rendering the resolved tracking badge.
                    if (trackingInput) {
                        trackingInput.value = '';
                        trackingInput.placeholder = 'Resolving tracking ID...';
                    }
                    badgeHost.innerHTML = `
                        <span class="inline-flex items-center gap-1 rounded-full bg-gray-50 text-gray-700 border border-gray-200 px-2.5 py-1 font-mono">
                            <i data-lucide="loader-2" class="h-3.5 w-3.5 animate-spin"></i>
                            Resolving Tracking ID...
                        </span>
                        <span class="text-gray-500">
                            Please wait a moment while the tracking mapping is refreshed.
                        </span>
                    `;

                    if (window.lucide && typeof window.lucide.createIcons === 'function') {
                        try { window.lucide.createIcons(); } catch (_) {}
                    }

                    if (trackingBadgeDelayTimer) {
                        clearTimeout(trackingBadgeDelayTimer);
                    }

                    trackingBadgeDelayTimer = setTimeout(function () {
                        if (trackingInput) {
                            trackingInput.value = newTra;
                            trackingInput.placeholder = '';
                        }

                        if (newTra) {
                            badgeHost.innerHTML = `
                                <span class="inline-flex items-center gap-1 rounded-full bg-red-50 text-red-700 border border-red-200 px-2.5 py-1 font-mono">
                                    <i data-lucide="link-2" class="h-3.5 w-3.5"></i>
                                    Tracking ID: <strong class="font-bold">${newTra}</strong>
                                </span>
                                <span class="text-gray-500">
                                    Changing the File Number will keep this Tracking ID and cascade the rename to all linked tables.
                                </span>
                            `;
                        } else {
                            badgeHost.innerHTML = `
                                <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 text-amber-700 border border-amber-200 px-2.5 py-1 font-mono">
                                    <i data-lucide="alert-triangle" class="h-3.5 w-3.5"></i>
                                    No Tracking ID linked
                                </span>
                                <span class="text-gray-500">
                                    The system will attempt to resolve one from the registry grouping table on save.
                                </span>
                            `;
                        }

                        if (window.lucide && typeof window.lucide.createIcons === 'function') {
                            try { window.lucide.createIcons(); } catch (_) {}
                        }
                    }, delayMs);
                };

                // Function to handle modal opening with current value pre-selected
                function openFileNumberModal(isChange = false) {
                    try {
                        const currentValue = fileNumberInput ? fileNumberInput.value : '';

                        mainFileNoModalActive = true;
                        GlobalFileNoModal.open({
                            initialValue: isChange ? currentValue : null, // Pre-populate if changing
                            callback: async function (fileData) {
                                console.log('File selected from modal:', fileData);
                                if (fileData && fileData.fileNumber && fileNumberInput) {
                                    console.log('Updating file number field:', fileData.fileNumber);
                                    fileNumberInput.value = fileData.fileNumber;
                                    await applyTrackingIdFromFileData(fileData, { delayMs: 1200 });
                                } else {
                                    console.log('No valid file data received');
                                }
                            }
                        });
                    } catch (error) {
                        console.error('Error opening modal:', error);
                        alert('Error opening file number modal: ' + error.message);
                    }
                }

                if (typeof GlobalFileNoModal !== 'undefined') {
                    // Add event listener for Select button
                    if (selectFileNumberBtn) {
                        selectFileNumberBtn.addEventListener('click', function (e) {
                            e.preventDefault();
                            openFileNumberModal(false);
                        });
                    }

                    // Add event listener for Change button
                    if (changeFileNumberBtn) {
                        changeFileNumberBtn.addEventListener('click', function (e) {
                            e.preventDefault();
                            openFileNumberModal(true);
                        });
                    }
                } else {
                    // Fallback if GlobalFileNoModal is not available
                    if (selectFileNumberBtn) {
                        selectFileNumberBtn.addEventListener('click', function () {
                            alert('File number selection modal is not available. Please contact administrator.');
                        });
                    }

                    if (changeFileNumberBtn) {
                        changeFileNumberBtn.addEventListener('click', function () {
                            alert('File number selection modal is not available. Please contact administrator.');
                        });
                    }
                }
            }


            // Wait for GlobalFileNoModal to be available
            if (typeof GlobalFileNoModal !== 'undefined') {
                initFileNumberModal();
            } else {
                // Wait for the script to load
                let attempts = 0;
                setTimeout(function checkModal() {
                    attempts++;
                    if (typeof GlobalFileNoModal !== 'undefined') {
                        initFileNumberModal();
                    } else if (attempts < 50) { // Max 5 seconds
                        setTimeout(checkModal, 100);
                    } else {
                        // Add fallback click handler if modal fails to load
                        if (selectFileNumberBtn) {
                            selectFileNumberBtn.addEventListener('click', function () {
                                alert('File number modal failed to load. Please refresh the page and try again.');
                            });
                        }
                        if (changeFileNumberBtn) {
                            changeFileNumberBtn.addEventListener('click', function () {
                                alert('File number modal failed to load. Please refresh the page and try again.');
                            });
                        }
                    }
                }, 100);
            }

            // Also listen for the global modal events as backup.
            // IMPORTANT: only honor this when the *main* file-number modal is active.
            // The related-file selector shares the same GlobalFileNoModal and also fires
            // `fileno-modal:applied`; applying it here would overwrite the main file number.
            $(document).on('fileno-modal:applied', function (event, data) {
                console.log('Global modal applied event received:', data);
                if (!mainFileNoModalActive) {
                    return;
                }
                if (data && data.fileNumber && fileNumberInput) {
                    fileNumberInput.value = data.fileNumber;
                    applyTrackingIdFromFileData(data, { delayMs: 1200 });
                }
            });

            // The main modal is no longer the active one once it closes (apply or cancel),
            // so drop the guard to avoid a later related-file apply leaking into the main field.
            $(document).on('fileno-modal:closed', function () {
                mainFileNoModalActive = false;
            });

            // Tracking sheet state management
            const trackingSheetLink = document.getElementById('tracking-sheet-link');
            const trackingSheetStatus = document.getElementById('tracking-sheet-status');
            let hasUnsavedChanges = false;

            // Function to disable/enable tracking sheet
            function updateTrackingSheetState(isDisabled = false, customMessage = null) {
                if (trackingSheetLink && trackingSheetStatus) {
                    if (isDisabled) {
                        trackingSheetLink.style.opacity = '0.5';
                        trackingSheetLink.style.pointerEvents = 'none';
                        trackingSheetLink.classList.add('cursor-not-allowed');
                        trackingSheetStatus.textContent = customMessage || 'Changes detected - auto-saving...';
                    } else {
                        trackingSheetLink.style.opacity = '1';
                        trackingSheetLink.style.pointerEvents = 'auto';
                        trackingSheetLink.classList.remove('cursor-not-allowed');
                        // Keep the original status text (with batch info if exists)
                        const originalText = trackingSheetStatus.dataset.originalText || 'Generate tracking document';
                        trackingSheetStatus.textContent = originalText;
                    }
                }
            }

            // Store original status text
            if (trackingSheetStatus) {
                trackingSheetStatus.dataset.originalText = trackingSheetStatus.textContent;
            }

            // Auto-save functionality
            let autoSaveTimeout;

            function autoSaveForm() {
                if (hasUnsavedChanges) {
                    console.log('Auto-saving form...');

                    // Update tracking sheet status to show saving
                    updateTrackingSheetState(true, 'Saving changes...');

                    // Trigger form submission silently
                    const updateBtn = document.getElementById('updateBtn');
                    if (updateBtn && !updateBtn.disabled) {
                        updateBtn.click();
                    }
                }
            }

            // Monitor form changes
            const formInputs = form.querySelectorAll('input, select, textarea');
            formInputs.forEach(input => {
                input.addEventListener('change', function () {
                    hasUnsavedChanges = true;
                    updateTrackingSheetState(true);

                    // Clear existing timeout and set new one for auto-save
                    clearTimeout(autoSaveTimeout);
                    autoSaveTimeout = setTimeout(autoSaveForm, 1500); // Auto-save after 1.5 seconds of inactivity
                });

                input.addEventListener('input', function () {
                    hasUnsavedChanges = true;
                    updateTrackingSheetState(true);

                    // Clear existing timeout and set new one for auto-save
                    clearTimeout(autoSaveTimeout);
                    autoSaveTimeout = setTimeout(autoSaveForm, 1500); // Auto-save after 1.5 seconds of inactivity
                });
            });

            // Initialize tracking sheet as enabled on page load (no unsaved changes yet)
            updateTrackingSheetState(false);

            // Force correct selection of dropdown values
            setTimeout(function () {
                // Force Land Use Type selection
                const landUseType = document.getElementById('land_use_type');
                const landUseValue = '{{ old("land_use_type", $fileIndexing->land_use_type ?? "") }}';
                if (landUseType && landUseValue) {
                    landUseType.value = landUseValue;
                    console.log('Set Land Use Type to:', landUseValue);
                }

                // Force LGA selection
                const lga = document.getElementById('lga');
                const lgaValue = '{{ old("lga", $fileIndexing->lga ?? "") }}';
                if (lga && lgaValue) {
                    lga.value = lgaValue;
                    console.log('Set LGA to:', lgaValue);
                }

                // Force Registry selection
                const registry = document.getElementById('registry');
                const registryValue = '{{ old("registry", $fileIndexing->registry ?? "") }}';
                if (registry && registryValue) {
                    registry.value = registryValue;
                    console.log('Set Registry to:', registryValue);
                }

                // Log current selections for debugging
                console.log('Current form selections after force-set:');
                console.log('Land Use Type:', landUseType?.value);
                console.log('LGA:', lga?.value);
                console.log('Registry:', registry?.value);
            }, 100);

            // Initialize Lucide icons
            if (typeof lucide !== 'undefined' && lucide.createIcons) {
                lucide.createIcons();
            }

            // Phone number validation
            (function () {
                const countryCodeSelect = document.getElementById('country_code');
                const phoneInput = document.getElementById('phone');
                const phoneHint = document.getElementById('phone-hint');
                const phoneError = document.getElementById('phone-error');

                if (!countryCodeSelect || !phoneInput) return;

                function validateNigerianPhone() {
                    const countryCode = countryCodeSelect.value;
                    const phoneNumber = phoneInput.value.trim();

                    if (countryCode === '+234' && phoneNumber) {
                        // Nigerian phone numbers should be 11 digits starting with 0
                        if (!/^0\d{10}$/.test(phoneNumber)) {
                            if (phoneError) {
                                phoneError.textContent = 'Nigerian phone numbers must be 11 digits starting with 0 (e.g., 08012345678)';
                                phoneError.classList.remove('hidden');
                            }
                            phoneInput.classList.add('border-red-500');
                            return false;
                        } else {
                            if (phoneError) {
                                phoneError.classList.add('hidden');
                            }
                            phoneInput.classList.remove('border-red-500');
                            return true;
                        }
                    } else {
                        if (phoneError) {
                            phoneError.classList.add('hidden');
                        }
                        phoneInput.classList.remove('border-red-500');
                        return true;
                    }
                }

                function updatePhoneHint() {
                    const countryCode = countryCodeSelect.value;
                    if (!phoneHint) {
                        return;
                    }

                    if (countryCode === '+234') {
                        phoneHint.textContent = 'For Nigeria (+234): Enter 11 digits starting with 0 (e.g., 08012345678)';
                    } else {
                        phoneHint.textContent = 'Enter phone number without country code';
                    }
                }

                // Only allow numeric input
                phoneInput.addEventListener('input', function (e) {
                    this.value = this.value.replace(/[^0-9]/g, '');
                    validateNigerianPhone();
                });

                countryCodeSelect.addEventListener('change', function () {
                    updatePhoneHint();
                    validateNigerianPhone();
                });

                phoneInput.addEventListener('blur', validateNigerianPhone);

                // Validate before form submission
                const form = document.querySelector('form');
                if (form) {
                    form.addEventListener('submit', function (e) {
                        if (!validateNigerianPhone() && phoneInput.value.trim()) {
                            e.preventDefault();
                            phoneInput.focus();
                            phoneInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        }
                    });
                }

                updatePhoneHint();
            })();

            // File Type and RC NO validation
            (function () {
                const fileTypeSelect = document.getElementById('file_type');
                const rcNoContainer = document.getElementById('rc_no_container');
                const rcNoInput = document.getElementById('rc_no');
                const ninInput = document.getElementById('nin');

                if (!fileTypeSelect || !rcNoContainer || !rcNoInput) return;

                // Show/hide RC NO based on file type
                function toggleRCNoField() {
                    const fileType = fileTypeSelect.value;
                    if (fileType === 'Corporate') {
                        rcNoContainer.style.display = 'block';
                        rcNoInput.setAttribute('required', 'required');
                    } else {
                        rcNoContainer.style.display = 'none';
                        rcNoInput.removeAttribute('required');
                        rcNoInput.value = ''; // Clear value when hidden
                    }
                }

                // Restrict NIN to digits only
                if (ninInput) {
                    ninInput.addEventListener('input', function (e) {
                        this.value = this.value.replace(/[^0-9]/g, '').slice(0, 13);
                    });
                }

                fileTypeSelect.addEventListener('change', toggleRCNoField);

                // Initialize on page load
                toggleRCNoField();
            })();

            // Kangis FileNo Placeholder — show/hide and assemble based on general_registry
            (function () {
                const generalRegistry = document.getElementById('general_registry');
                const fileNumberInput = document.getElementById('file_number');
                const wrapper = document.getElementById('edit-kangis-placeholder-wrapper');
                const prefixSelect = document.getElementById('edit-kangis-fileno-prefix');
                const serialInput = document.getElementById('edit-kangis-fileno-serial');
                const hiddenInput = document.getElementById('edit-kangis-fileno-placeholder');
                const preview = document.getElementById('edit-kangis-placeholder-preview');
                const previewValue = document.getElementById('edit-kangis-placeholder-preview-value');
                const feedback = document.getElementById('edit-kangis-placeholder-feedback');
                const feedbackText = document.getElementById('edit-kangis-placeholder-feedback-text');
                const currentRecordId = @json((int) $fileIndexing->id);

                if (!generalRegistry || !wrapper) return;

                function hideFeedback() {
                    if (feedback) {
                        feedback.classList.add('hidden');
                    }
                }

                function assemble() {
                    const prefix = (prefixSelect ? prefixSelect.value : '').trim();
                    const serial = (serialInput ? serialInput.value : '').trim();
                    const assembled = (prefix && serial) ? (prefix + ' ' + serial) : '';
                    if (hiddenInput) hiddenInput.value = assembled;
                    if (preview && previewValue) {
                        if (assembled) {
                            previewValue.textContent = assembled;
                            preview.classList.remove('hidden');
                        } else {
                            preview.classList.add('hidden');
                        }
                    }
                }

                async function checkKangisPlaceholderSiblings() {
                    const assembled = (hiddenInput ? hiddenInput.value : '').trim();
                    const selectedFileNumber = (fileNumberInput ? fileNumberInput.value : '').trim();

                    if (!assembled || !selectedFileNumber || !feedback || !feedbackText) {
                        hideFeedback();
                        return;
                    }

                    try {
                        const resp = await fetch(`/api/kangis-placeholder/check?file_number=${encodeURIComponent(selectedFileNumber)}&exclude_id=${encodeURIComponent(String(currentRecordId || 0))}`, {
                            headers: { Accept: 'application/json' },
                        });

                        if (!resp.ok) {
                            hideFeedback();
                            return;
                        }

                        const data = await resp.json();
                        const bareExists = data.bare_exists || false;
                        const suffixedCount = data.count || 0;
                        const firstCollision = data.first_collision || false;

                        if (firstCollision) {
                            feedback.className = 'mt-2 flex items-center gap-2 text-xs rounded px-3 py-1.5 border text-amber-700 bg-amber-50 border-amber-200';
                            feedbackText.innerHTML = `<strong>${selectedFileNumber}</strong> already exists — it will be renamed to <strong>${selectedFileNumber}_1</strong> and this record will be saved as <strong>${selectedFileNumber}_2</strong>.`;
                        } else if (bareExists || suffixedCount > 0) {
                            const nextSuffix = `${selectedFileNumber}_${suffixedCount + 1}`;
                            feedback.className = 'mt-2 flex items-center gap-2 text-xs rounded px-3 py-1.5 border text-blue-700 bg-blue-50 border-blue-200';
                            feedbackText.innerHTML = `<strong>${suffixedCount}</strong> variant(s) exist — this record will be saved as <strong>${nextSuffix}</strong>.`;
                        } else {
                            feedback.className = 'mt-2 flex items-center gap-2 text-xs rounded px-3 py-1.5 border text-green-700 bg-green-50 border-green-200';
                            feedbackText.innerHTML = `No existing record for <strong>${selectedFileNumber}</strong> — this value remains <strong>${selectedFileNumber}</strong>.`;
                        }

                        feedback.classList.remove('hidden');
                        if (typeof lucide !== 'undefined') {
                            lucide.createIcons({ nodes: [feedback] });
                        }
                    } catch (_) {
                        hideFeedback();
                    }
                }

                function updateVisibility() {
                    const val = (generalRegistry.value || '').toUpperCase();
                    if (val.includes('KANGIS')) {
                        wrapper.classList.remove('hidden');
                        checkKangisPlaceholderSiblings();
                    } else {
                        wrapper.classList.add('hidden');
                        if (hiddenInput) hiddenInput.value = '';
                        if (preview) preview.classList.add('hidden');
                        hideFeedback();
                    }
                }

                if (prefixSelect) {
                    prefixSelect.addEventListener('change', function () {
                        assemble();
                        checkKangisPlaceholderSiblings();
                    });
                }
                if (serialInput) {
                    serialInput.addEventListener('input', function () {
                        this.value = this.value.replace(/[^0-9]/g, '');
                        assemble();
                        checkKangisPlaceholderSiblings();
                    });
                    serialInput.addEventListener('blur', checkKangisPlaceholderSiblings);
                }

                if (fileNumberInput) {
                    fileNumberInput.addEventListener('change', checkKangisPlaceholderSiblings);
                }

                generalRegistry.addEventListener('change', updateVisibility);

                // Ensure hidden placeholder value and initial feedback are in sync on load.
                assemble();
                updateVisibility();
            })();
        });

        // Debug function to show current values
        function showCurrentValues() {
            const values = {
                'File Number': document.getElementById('file_number')?.value || 'N/A',
                'File Title': document.getElementById('file_title')?.value || 'N/A',
                'Land Use Type': document.getElementById('land_use_type')?.value || 'N/A',
                'Plot Number': document.getElementById('plot_number')?.value || 'N/A',
                'TP Number': document.getElementById('tp_no')?.value || 'N/A',
                'LPKN Number': document.getElementById('lpkn_no')?.value || 'N/A',
                'District': document.getElementById('district')?.value || 'N/A',
                'LGA': document.getElementById('lga')?.value || 'N/A',
                'Registry': document.getElementById('registry')?.value || 'N/A',
                'Batch Number': document.getElementById('batch_no')?.value || 'N/A',
                'Tracking ID': document.getElementById('tracking_id')?.value || 'N/A',
                'Location': document.getElementById('location')?.value || 'N/A'
            };

            let message = 'Current Form Values:\n\n';
            for (let [key, value] of Object.entries(values)) {
                message += `${key}: ${value}\n`;
            }

            // Also show backend values
            message += '\n--- Backend Values ---\n';
            message += `Land Use Type (Backend): {{ $fileIndexing->land_use_type ?? 'NULL' }}\n`;
            message += `LGA (Backend): {{ $fileIndexing->lga ?? 'NULL' }}\n`;
            message += `Registry (Backend): {{ $fileIndexing->registry ?? 'NULL' }}\n`;

            // Show property records info
            message += '\n--- Property Records ---\n';
            message += `Existing Records Count: ${existingPropertyRecords.length}\n`;
            if (existingPropertyRecords.length > 0) {
                message += `Sample Record Keys: ${Object.keys(existingPropertyRecords[0]).join(', ')}\n`;
                message += `Transaction Type: ${existingPropertyRecords[0].transaction_type || 'N/A'}\n`;
                message += `First Party Fields: ${Object.keys(existingPropertyRecords[0]).filter(key => key.toLowerCase().includes('grantor') || key.toLowerCase().includes('assignor') || key.toLowerCase().includes('mortgagor')).join(', ')}\n`;
            }

            // Test API call with transaction order verification
            const fileNumber = document.getElementById('file_number').value;
            if (fileNumber) {
                message += `\nTesting API call for file number: ${fileNumber}`;
                fetch(`/api/property-records/check/${encodeURIComponent(fileNumber)}`)
                    .then(response => response.json())
                    .then(apiData => {
                        console.log('API Test Response:', apiData);
                        if (apiData.records && apiData.records.length > 0) {
                            console.log('API Record Fields:', Object.keys(apiData.records[0]));
                            console.log('API Sample Record:', apiData.records[0]);

                            // Add transaction order information to debug message
                            let orderMessage = '\n--- Transaction Order (First Added → Last Added) ---\n';
                            apiData.records.forEach((record, index) => {
                                const createdAt = record.created_at ? new Date(record.created_at).toLocaleString() : 'No date';
                                const transactionType = record.transaction_type || record.transactionType || 'No type';
                                orderMessage += `Transaction ${index + 1}: ${transactionType} (${createdAt})\n`;
                            });

                            // Show complete debug info
                            alert(message + orderMessage);
                        } else {
                            alert(message);
                        }
                    })
                    .catch(error => {
                        console.error('API Test Failed:', error);
                        alert(message);
                    });
            } else {
                alert(message);
            }

            console.log('Form Values:', values);
            console.log('Existing Property Records:', existingPropertyRecords);
        }
    </script>

    <!-- Include Global File Number Modal -->
    @include('components.global-fileno-modal')

    <!-- Include Property Transaction Modal -->
    @include('fileindexing.partial.property_transaction_modal')

    <!-- Include Related File Details Modal -->
    @include('fileindexing.addons.partials.sections.related_fileno_details')

    <!-- Global File Number Modal JavaScript -->
    <script src="{{ asset('js/global-fileno-modal.js') }}"></script>

    <!-- Related File Number Card Logic -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const wrapper = document.getElementById('related-files-wrapper');
            const addBtn = document.getElementById('add-related-file-btn');
            const hasRelatedCheckbox = document.getElementById('has-related-file');
            const openBtn = document.getElementById('open-related-details-btn');
            const modal = document.getElementById('related-file-details-modal');
            const container = document.getElementById('related-file-details-container');
            const saveBtn = document.getElementById('save-related-details-btn');
            const cancelBtn = document.getElementById('cancel-related-details-btn');
            const hiddenContainer = document.getElementById('related-details-hidden');

            if (!wrapper || !hasRelatedCheckbox) return;

            // Fields persisted per related file. The first five are editable in the
            // details modal; the rest are passed through untouched so the update
            // endpoint's link re-sync never wipes them.
            const EDITABLE_FIELDS = ['file_title', 'location', 'plot_number', 'tp_no', 'lpkn_no'];
            const PASSTHROUGH_FIELDS = ['entity_type', 'entity_name', 'customer_name', 'land_use_type', 'district', 'lga', 'mfile'];
            const DETAIL_FIELDS = EDITABLE_FIELDS.concat(PASSTHROUGH_FIELDS);

            // Existing link details from file_indexing_links, keyed by file number.
            const detailsCache = new Map();
            (@json($existingRelatedDetails ?? [])).forEach(function (detail) {
                const key = String(detail.file_number || '').trim();
                if (!key) return;
                const entry = {};
                DETAIL_FIELDS.forEach(function (field) {
                    entry[field] = detail[field] == null ? '' : String(detail[field]);
                });
                detailsCache.set(key, entry);
            });

            function currentFileNos() {
                return Array.from(wrapper.querySelectorAll('input[name="related_fileno[]"]'))
                    .map(function (input) { return input.value.trim(); })
                    .filter(function (value) { return value !== ''; });
            }

            // Mirror cached details into hidden inputs inside the form, index-aligned
            // with the non-empty related_fileno rows (the controller filters blanks
            // the same way, so index and file_number matching both hold).
            function syncHiddenDetails() {
                if (!hiddenContainer) return;
                hiddenContainer.innerHTML = '';
                currentFileNos().forEach(function (fileNo, index) {
                    const cached = detailsCache.get(fileNo) || {};
                    function addHidden(field, value) {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'related_details[' + index + '][' + field + ']';
                        input.value = value == null ? '' : value;
                        hiddenContainer.appendChild(input);
                    }
                    addHidden('file_number', fileNo);
                    DETAIL_FIELDS.forEach(function (field) {
                        addHidden(field, cached[field] || '');
                    });
                });
            }

            function updateOpenBtnVisibility() {
                if (!openBtn) return;
                openBtn.classList.toggle('hidden', currentFileNos().length === 0);
            }

            function refresh() {
                syncHiddenDetails();
                updateOpenBtnVisibility();
            }

            // When a related file number is selected, if that file is indexed, pull its
            // property details (title, location, plot, TP, LPKN) into the cache so the
            // Related Details modal prefills. Only fills blanks — never clobbers edits.
            function backfillIndexedDetails(fileNo) {
                const key = String(fileNo || '').trim();
                if (!key) return;
                fetch('/api/file-indexings/related-indexed-details?file_number=' + encodeURIComponent(key), {
                    headers: { 'Accept': 'application/json' }
                })
                    .then(function (res) { return res.ok ? res.json() : null; })
                    .then(function (payload) {
                        if (!payload || !payload.indexed || !payload.data) return;
                        const entry = detailsCache.get(key) || {};
                        EDITABLE_FIELDS.forEach(function (field) {
                            const incoming = payload.data[field];
                            if ((entry[field] == null || String(entry[field]).trim() === '') &&
                                incoming != null && String(incoming).trim() !== '') {
                                entry[field] = String(incoming);
                            }
                        });
                        detailsCache.set(key, entry);
                        syncHiddenDetails();
                    })
                    .catch(function () { /* non-indexed / lookup failure: leave blank for manual entry */ });
            }

            hasRelatedCheckbox.addEventListener('change', function () {
                wrapper.classList.toggle('hidden', !this.checked);
                if (addBtn) addBtn.classList.toggle('hidden', !this.checked);
            });

            function addRelatedFileRow(initialValue) {
                const rows = wrapper.querySelectorAll('.related-file-row');
                const template = rows[0];
                if (!template) return;
                const newRow = template.cloneNode(true);
                const input = newRow.querySelector('.related-file-input');
                if (input) input.value = initialValue || '';
                const actionIcon = newRow.querySelector('.clear-related-btn i, .clear-related-btn svg');
                if (actionIcon) actionIcon.setAttribute('data-lucide', 'trash-2');
                wrapper.appendChild(newRow);
                if (window.lucide) window.lucide.createIcons();
                if (!hasRelatedCheckbox.checked) {
                    hasRelatedCheckbox.checked = true;
                    hasRelatedCheckbox.dispatchEvent(new Event('change', { bubbles: true }));
                }
            }

            if (addBtn) {
                addBtn.addEventListener('click', function () { addRelatedFileRow(); });
            }

            // Select / Clear buttons (delegated so cloned rows work too)
            wrapper.addEventListener('click', function (e) {
                const target = e.target.closest('button');
                if (!target) return;
                const row = target.closest('.related-file-row');
                if (!row) return;
                const input = row.querySelector('.related-file-input');

                if (target.classList.contains('select-related-btn')) {
                    if (typeof GlobalFileNoModal !== 'undefined') {
                        GlobalFileNoModal.open({
                            autoPopulateGenericFields: false,
                            callback: function (data) {
                                if (data && data.fileNumber && input) {
                                    input.value = data.fileNumber;
                                    refresh();
                                    backfillIndexedDetails(data.fileNumber);
                                }
                            }
                        });
                    } else {
                        console.error('GlobalFileNoModal is not defined');
                    }
                } else if (target.classList.contains('clear-related-btn')) {
                    if (wrapper.querySelectorAll('.related-file-row').length > 1) {
                        row.remove();
                    } else if (input) {
                        input.value = '';
                    }
                    refresh();
                }
            });

            // ---------- Related Details modal ----------
            function escapeAttribute(value) {
                return String(value == null ? '' : value)
                    .replace(/&/g, '&amp;')
                    .replace(/"/g, '&quot;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;');
            }

            // Read the modal's visible fields back into the cache, preserving
            // passthrough fields that are not shown in the modal.
            function syncModalToCache() {
                if (!container) return;
                container.querySelectorAll('[data-file-no]').forEach(function (section) {
                    const fileNo = (section.dataset.fileNo || '').trim();
                    if (!fileNo) return;
                    const entry = detailsCache.get(fileNo) || {};
                    EDITABLE_FIELDS.forEach(function (field) {
                        const input = section.querySelector('input[data-detail-field="' + field + '"]');
                        if (input) entry[field] = input.value;
                    });
                    detailsCache.set(fileNo, entry);
                });
            }

            if (openBtn && modal && container && saveBtn && cancelBtn) {
                openBtn.addEventListener('click', function () {
                    const fileNos = currentFileNos();
                    if (fileNos.length === 0) {
                        alert('Please select at least one related file number first.');
                        return;
                    }

                    container.innerHTML = '';
                    fileNos.forEach(function (fileNo) {
                        const cached = detailsCache.get(fileNo) || {};
                        const section = document.createElement('div');
                        section.className = 'bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden mb-6 last:mb-0';
                        section.dataset.fileNo = fileNo;

                        const fieldConfigs = [
                            { field: 'file_title', label: 'File Title <span class="text-gray-400 font-normal">(Current Holder)</span>', required: true },
                            { field: 'location', label: 'Location', required: true },
                            { field: 'plot_number', label: 'Plot Number', required: false },
                            { field: 'tp_no', label: 'TP Number', required: false },
                            { field: 'lpkn_no', label: 'LPKN Number', required: false }
                        ];

                        const fieldsHtml = fieldConfigs.map(function (cfg) {
                            return '<div>'
                                + '<label class="block text-sm font-medium text-gray-700 mb-1">' + cfg.label
                                + (cfg.required ? ' <span class="text-red-500">*</span>' : '') + '</label>'
                                + '<input type="text" data-detail-field="' + cfg.field + '"'
                                + ' value="' + escapeAttribute(cached[cfg.field] || '') + '"'
                                + (cfg.required ? ' required' : '')
                                + ' class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm bg-white">'
                                + '</div>';
                        }).join('');

                        section.innerHTML =
                            '<div class="bg-gray-50 px-4 py-3 border-b border-gray-200 flex items-center gap-2">'
                            + '<div class="bg-blue-100 p-1.5 rounded-full"><i data-lucide="file-text" class="w-4 h-4 text-blue-600"></i></div>'
                            + '<h4 class="font-bold text-gray-800 text-sm">Related File: ' + escapeAttribute(fileNo) + '</h4>'
                            + '</div>'
                            + '<div class="p-4 bg-gray-50/50"><div class="grid grid-cols-1 md:grid-cols-2 gap-4">' + fieldsHtml + '</div></div>';

                        container.appendChild(section);
                    });

                    if (window.lucide) window.lucide.createIcons();
                    modal.classList.remove('hidden');

                    // Backfill any related file whose detail fields are still empty from
                    // its indexed record — covers files just selected in the picker whose
                    // details were not yet cached (e.g. lookup still in flight).
                    container.querySelectorAll('[data-file-no]').forEach(function (section) {
                        const fileNo = (section.dataset.fileNo || '').trim();
                        if (!fileNo) return;

                        const inputs = {};
                        EDITABLE_FIELDS.forEach(function (field) {
                            inputs[field] = section.querySelector('input[data-detail-field="' + field + '"]');
                        });
                        const allBlank = EDITABLE_FIELDS.every(function (field) {
                            return !inputs[field] || inputs[field].value.trim() === '';
                        });
                        if (!allBlank) return; // user/cache already has data — don't clobber

                        fetch('/api/file-indexings/related-indexed-details?file_number=' + encodeURIComponent(fileNo), {
                            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                        })
                            .then(function (res) { return res.ok ? res.json() : null; })
                            .then(function (payload) {
                                if (!payload || !payload.indexed || !payload.data) return;
                                const entry = detailsCache.get(fileNo) || {};
                                EDITABLE_FIELDS.forEach(function (field) {
                                    const incoming = payload.data[field];
                                    if (inputs[field] && inputs[field].value.trim() === '' &&
                                        incoming != null && String(incoming).trim() !== '') {
                                        inputs[field].value = String(incoming);
                                        entry[field] = String(incoming);
                                    }
                                });
                                detailsCache.set(fileNo, entry);
                                syncHiddenDetails();
                            })
                            .catch(function () { /* leave blank for manual entry */ });
                    });
                });

                saveBtn.addEventListener('click', function () {
                    let isValid = true;
                    container.querySelectorAll('input[required]').forEach(function (input) {
                        if (!input.value.trim()) {
                            isValid = false;
                            input.classList.add('border-red-500');
                        } else {
                            input.classList.remove('border-red-500');
                        }
                    });
                    if (!isValid) {
                        alert('Please fill in all required fields.');
                        return;
                    }

                    syncModalToCache();
                    syncHiddenDetails();
                    modal.classList.add('hidden');

                    const originalHtml = openBtn.innerHTML;
                    openBtn.innerHTML = '<i data-lucide="check" class="w-4 h-4 mr-2"></i> Details Staged';
                    openBtn.classList.add('bg-green-50', 'text-green-700', 'border-green-300');
                    if (window.lucide) window.lucide.createIcons();
                    setTimeout(function () {
                        openBtn.innerHTML = originalHtml;
                        openBtn.classList.remove('bg-green-50', 'text-green-700', 'border-green-300');
                        if (window.lucide) window.lucide.createIcons();
                    }, 2000);
                });

                cancelBtn.addEventListener('click', function () {
                    modal.classList.add('hidden');
                });
            }

            // Seed the hidden inputs on load so existing link details survive a save
            // even if the user never opens the details modal.
            refresh();
        });
    </script>

    <!-- Property Transaction Logic for Edit Page -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            console.log('=== Edit page DOMContentLoaded ===');

            const propertyTransactionBtn = document.getElementById('property-transaction-btn');
            const propertyTransactionBtnText = document.getElementById('property-transaction-btn-text');
            // Server-side initial state (avoids race conditions for concurrent users)
            const serverHasPropertyRecords = @json($hasPropertyRecords ?? false);
            let existingPropertyRecords = [];

            // Set modal title immediately from server data
            (function() {
                const modalTitle = document.getElementById('transaction-form-title');
                if (modalTitle) {
                    modalTitle.textContent = serverHasPropertyRecords
                        ? 'Update Property Transaction Details'
                        : 'Add Property Transaction Details';
                }
            })();

            console.log('Property transaction button found:', !!propertyTransactionBtn);
            console.log('Modal functions available:');
            console.log('- window.openPropertyTransactionModal:', typeof window.openPropertyTransactionModal);
            console.log('- window.closePropertyTransactionModal:', typeof window.closePropertyTransactionModal);

            // Wait a bit for modal functions to load
            setTimeout(() => {
                console.log('After timeout - Modal functions available:');
                console.log('- window.openPropertyTransactionModal:', typeof window.openPropertyTransactionModal);
                console.log('- window.closePropertyTransactionModal:', typeof window.closePropertyTransactionModal);
            }, 500);

            // Check if property records exist for this file number
            async function checkExistingPropertyRecords() {
                const fileNumber = document.getElementById('file_number').value;

                if (!fileNumber) {
                    console.log('No file number available');
                    return;
                }

                try {
                    // Make API call to check for existing property records
                    const response = await fetch(`/api/property-records/check/${encodeURIComponent(fileNumber)}`);
                    const data = await response.json();

                    if (data.success && data.records && data.records.length > 0) {
                        // Records exist — populate for modal use only (button text already set server-side)
                        const modalTitle = document.getElementById('transaction-form-title');
                        if (modalTitle) modalTitle.textContent = 'Update Property Transaction Details';
                        if (propertyTransactionBtnText) propertyTransactionBtnText.textContent = 'Update Property Transaction Details';

                        existingPropertyRecords = data.records;
                        console.log('Found existing property records:', data.records.length);
                        if (data.records.length > 0) {
                            console.log('First record structure:', Object.keys(data.records[0]));
                        }

                        // Sync CofO data back to main form fields
                        const cofoRecord = data.records.find(r => {
                            const instType = (r.instrument_type || '').toLowerCase();
                            const transType = (r.transaction_type || '').toLowerCase();
                            return instType === 'certificate of occupancy' || 
                                   instType === 'cofo' ||
                                   transType === 'certificate of occupancy' ||
                                   transType === 'cofo';
                        });
                        if (cofoRecord && document.getElementById('has_cofo')) {
                            document.getElementById('has_cofo').checked = true;
                            if (document.getElementById('cofo_serial_no')) document.getElementById('cofo_serial_no').value = cofoRecord.serialNo || '';
                            if (document.getElementById('cofo_page_no')) document.getElementById('cofo_page_no').value = cofoRecord.pageNo || '';
                            if (document.getElementById('cofo_vol_no')) document.getElementById('cofo_vol_no').value = cofoRecord.volumeNo || '';
                            if (document.getElementById('cofo_deeds_date')) document.getElementById('cofo_deeds_date').value = cofoRecord.transaction_date ? cofoRecord.transaction_date.substring(0, 10) : '';
                            // Party 1 is constant: KANO STATE GOVERNMENT
                            if (document.getElementById('cofo_second_party')) document.getElementById('cofo_second_party').value = cofoRecord.party_2 || '';
                            
                            // Trigger toggle logic
                            document.getElementById('has_cofo').dispatchEvent(new Event('change'));
                        }
                    } else {
                        // Only override if server said no records too (avoid false negatives overwriting correct state)
                        if (!serverHasPropertyRecords) {
                            const modalTitle = document.getElementById('transaction-form-title');
                            if (propertyTransactionBtnText) propertyTransactionBtnText.textContent = 'Add Property Transaction Details';
                            if (modalTitle) modalTitle.textContent = 'Add Property Transaction Details';
                        }
                        existingPropertyRecords = [];
                        console.log('No existing property records found via API. Server said:', serverHasPropertyRecords);
                    }
                } catch (error) {
                    console.error('Error checking property records:', error);
                    // Do NOT override button text on error — server-side state is already correct
                    existingPropertyRecords = [];
                }
            }

            // Property transaction button click handler
            if (propertyTransactionBtn) {
                propertyTransactionBtn.addEventListener('click', function () {
                    console.log('=== Property transaction button clicked ===');

                    // Check if modal element exists
                    const modal = document.getElementById('property-transaction-dialog');
                    console.log('Modal element found:', !!modal);

                    if (!modal) {
                        alert('Property transaction modal not found in the page. Please refresh and try again.');
                        return;
                    }

                    // Gather file indexing data safely with correct field IDs
                    const district = (document.getElementById('district') && document.getElementById('district').value) || '';
                    const lga = (document.getElementById('lga') && document.getElementById('lga').value) || '';

                    // Construct property description from district and lga
                    const propertyDescription = [district, lga].filter(Boolean).join(', ');

                    const fileIndexingData = {
                        id: {{ $fileIndexing->id }},
                        file_number: (document.getElementById('file_number') && document.getElementById('file_number').value) || '{{ $fileIndexing->temp_file_no ?? '' }}',
                        temp_file_no: '{{ $fileIndexing->temp_file_no ?? '' }}',
                        file_title: (document.getElementById('file_title') && document.getElementById('file_title').value) || '',
                        plot_no: (document.getElementById('plot_number') && document.getElementById('plot_number').value) || '',
                        tp_no: (document.getElementById('tp_no') && document.getElementById('tp_no').value) || '',
                        lpkn_no: (document.getElementById('lpkn_no') && document.getElementById('lpkn_no').value) || '',
                        lga: lga,
                        district: district,
                        property_description: propertyDescription,
                        land_use_type: (document.getElementById('land_use_type') && document.getElementById('land_use_type').value) || '',
                        existing_records: existingPropertyRecords
                    };

                    console.log('File indexing data prepared:', fileIndexingData);

                    // Sync main form CofO fields to existingPropertyRecords if has_cofo is checked
                    const hasCofo = document.getElementById('has_cofo')?.checked;
                    if (hasCofo) {
                        const formCofo = {
                            transaction_type: 'Certificate of Occupancy',
                            instrument_type: document.getElementById('cofo_instrument_type')?.value || 'Certificate of Occupancy',
                            serialNo: document.getElementById('cofo_serial_no')?.value || '',
                            pageNo: document.getElementById('cofo_page_no')?.value || '',
                            volumeNo: document.getElementById('cofo_vol_no')?.value || '',
                            period: document.getElementById('cofo_period')?.value || '',
                            period_unit: document.getElementById('cofo_period_unit')?.value || 'Years',
                            transaction_date: document.getElementById('cofo_deeds_date')?.value || '',
                            reg_time: document.getElementById('cofo_deeds_time')?.value || '',
                            party_1: document.getElementById('cofo_first_party')?.value || '',
                            party_2: document.getElementById('cofo_second_party')?.value || '',
                            land_use: document.getElementById('cofo_land_use')?.value || ''
                        };

                        const existingCofoIndex = existingPropertyRecords.findIndex(r => {
                            const instType = (r.instrument_type || '').toLowerCase();
                            const transType = (r.transaction_type || '').toLowerCase();
                            return instType === 'certificate of occupancy' || 
                                   instType === 'cofo' ||
                                   transType === 'certificate of occupancy' ||
                                   transType === 'cofo';
                        });
                        
                        if (existingCofoIndex >= 0) {
                            existingPropertyRecords[existingCofoIndex] = { ...existingPropertyRecords[existingCofoIndex], ...formCofo };
                        } else {
                            existingPropertyRecords.push(formCofo);
                        }
                    }

                    // Try using the global function first
                    if (typeof window.openPropertyTransactionModal === 'function') {
                        // Add existing records to fileIndexingData
                        fileIndexingData.existing_records = existingPropertyRecords;
                        console.log('Opening modal with existing records:', existingPropertyRecords.length);
                        console.log('Using global openPropertyTransactionModal function');
                        try {
                            window.openPropertyTransactionModal(fileIndexingData);
                            console.log('Modal opened successfully via global function');
                        } catch (error) {
                            console.error('Error calling global openPropertyTransactionModal:', error);
                            // Fallback to manual display
                            console.log('Falling back to manual modal display');
                            modal.classList.remove('hidden');
                            modal.style.display = 'flex';
                            modal.style.zIndex = '10000';
                        }
                    } else {
                        console.error('Global openPropertyTransactionModal function not found');
                        console.log('Available window functions:', Object.keys(window).filter(key => key.toLowerCase().includes('property')));

                        // Fallback: manually show modal
                        console.log('Manually opening modal...');
                        modal.classList.remove('hidden');
                        modal.style.display = 'flex';
                        modal.style.zIndex = '10000';

                        console.log('Modal display style:', modal.style.display);
                        console.log('Modal classes:', modal.className);
                    }
                });
            } else {
                console.error('Property transaction button not found');
            }

            // Check for existing records on page load
            checkExistingPropertyRecords();

            // Re-check when file number changes
            const fileNumberInput = document.getElementById('file_number');
            if (fileNumberInput) {
                fileNumberInput.addEventListener('change', checkExistingPropertyRecords);
            }

            // DEBUG: Test modal button
            const debugModalBtn = document.getElementById('debug-modal-btn');
            if (debugModalBtn) {
                debugModalBtn.addEventListener('click', function () {
                    console.log('=== MODAL DEBUG TEST ===');

                    const modal = document.getElementById('property-transaction-dialog');
                    console.log('1. Modal element:', modal);
                    console.log('2. Modal classes:', modal ? modal.className : 'N/A');
                    console.log('3. Modal style display:', modal ? modal.style.display : 'N/A');

                    console.log('4. Window functions:');
                    console.log('   - openPropertyTransactionModal:', typeof window.openPropertyTransactionModal);
                    console.log('   - closePropertyTransactionModal:', typeof window.closePropertyTransactionModal);

                    if (modal) {
                        console.log('5. Forcing modal display...');
                        modal.classList.remove('hidden');
                        modal.style.display = 'flex';
                        modal.style.zIndex = '10000';
                        console.log('6. Modal should be visible now');
                        console.log('7. Final modal style:', modal.style.cssText);
                    } else {
                        console.error('Modal element not found!');
                        alert('Modal element not found! Check browser console for details.');
                    }
                });
            }
            // District Specify Logic
            const districtSelect = document.getElementById('district');
            const districtSpecifyContainer = document.getElementById('district_specify_container');
            const districtSpecifiedInput = document.getElementById('district_specified');

            if (districtSelect && districtSpecifyContainer && districtSpecifiedInput) {
                function toggleDistrictInput() {
                    const val = districtSelect.value.toLowerCase();
                    const isOthers = val === 'others' || val === 'other';
                    
                    if (isOthers) {
                        districtSpecifyContainer.classList.remove('hidden');
                        districtSpecifiedInput.setAttribute('required', 'required');
                        // Ensure the input value is sent as 'district'
                        districtSelect.removeAttribute('name');
                        districtSpecifiedInput.setAttribute('name', 'district');
                    } else {
                        districtSpecifyContainer.classList.add('hidden');
                        districtSpecifiedInput.removeAttribute('required');
                        // Ensure the select value is sent as 'district'
                        districtSelect.setAttribute('name', 'district');
                        districtSpecifiedInput.removeAttribute('name');
                    }
                }

                districtSelect.addEventListener('change', toggleDistrictInput);

                // Initial setup
                toggleDistrictInput();
            }

        });
    </script>

    {{-- Property Location Map — geocodes the auto-built address and pins a draggable
         marker. Coordinates are captured into the readonly latitude/longitude fields. --}}
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const latEl        = document.getElementById('latitude');
            const lngEl        = document.getElementById('longitude');
            const applyBtn     = document.getElementById('apply-pin-btn');
            const locationEl   = document.getElementById('location');
            const mapEl        = document.getElementById('edit-map');
            const emptyEl      = document.getElementById('edit-map-empty');
            const hintEl       = document.getElementById('edit-map-hint');
            const plotEl       = document.getElementById('plot_number');
            const districtEl   = document.getElementById('district');
            const districtSpec = document.getElementById('district_specified');
            const lgaEl        = document.getElementById('lga');
            const stateEl      = document.getElementById('state');

            if (!latEl || !lngEl || !mapEl) return;

            let map = null, marker = null;

            const gmapsReady = () => typeof google !== 'undefined' && google.maps;
            const roundCoord = v => Math.round(parseFloat(v) * 1e7) / 1e7;

            function markerIcon() {
                const FILL = '#2563eb';
                const svg = '<svg xmlns="http://www.w3.org/2000/svg" width="42" height="50" viewBox="0 0 42 50">'
                    + '<path d="M21 0C10 0 1 9 1 20c0 14 20 30 20 30s20-16 20-30C41 9 32 0 21 0z" fill="' + FILL + '" stroke="#ffffff" stroke-width="2"/>'
                    + '<circle cx="21" cy="20" r="13" fill="#ffffff"/>'
                    + '<path d="M21 12l-8 7h2.4v8.4h11.2V19H29z" fill="' + FILL + '"/></svg>';
                return {
                    url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(svg),
                    scaledSize: new google.maps.Size(42, 50),
                    anchor: new google.maps.Point(21, 50),
                };
            }

            function setCoords(lat, lng) {
                latEl.value = roundCoord(lat);
                lngEl.value = roundCoord(lng);
            }

            function showMap(lat, lng, recenter) {
                if (!gmapsReady()) return;
                const pos = { lat: parseFloat(lat), lng: parseFloat(lng) };
                if (emptyEl) emptyEl.style.display = 'none';
                mapEl.style.display = 'block';
                if (hintEl) hintEl.style.display = '';

                if (!map) {
                    map = new google.maps.Map(mapEl, {
                        center: pos, zoom: 17, mapTypeId: 'satellite',
                        streetViewControl: true, fullscreenControl: true,
                    });
                    map.addListener('click', e => {
                        setCoords(e.latLng.lat(), e.latLng.lng());
                        if (marker) marker.setPosition(e.latLng);
                    });
                } else {
                    google.maps.event.trigger(map, 'resize');
                    if (recenter) map.setCenter(pos);
                }

                if (!marker) {
                    marker = new google.maps.Marker({
                        position: pos, map, draggable: true, icon: markerIcon(),
                        animation: google.maps.Animation.DROP,
                        title: 'Drag to adjust the exact property location',
                    });
                    marker.addListener('dragend', e => setCoords(e.latLng.lat(), e.latLng.lng()));
                } else {
                    marker.setPosition(pos);
                    marker.setMap(map);
                }

                if (window.lucide && typeof window.lucide.createIcons === 'function') window.lucide.createIcons();
            }

            function buildAddress() {
                // Prefer the auto-built Location; otherwise compose from the address parts.
                const loc = (locationEl && locationEl.value.trim()) || '';
                if (loc) return loc + ', NIGERIA';

                const parts = [];
                if (plotEl && plotEl.value.trim()) parts.push(plotEl.value.trim());
                let dist = districtEl ? districtEl.value.trim() : '';
                if ((dist.toLowerCase() === 'others' || dist.toLowerCase() === 'other') && districtSpec) {
                    dist = districtSpec.value.trim();
                }
                if (dist) parts.push(dist);
                if (lgaEl && lgaEl.value.trim()) parts.push(lgaEl.value.trim());
                parts.push(stateEl ? (stateEl.value.trim() || 'Kano') : 'Kano');
                parts.push('NIGERIA');
                return parts.filter(Boolean).join(', ');
            }

            function geocodeAndPin() {
                if (!gmapsReady()) {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({ icon: 'error', title: 'Map not ready', text: 'Google Maps is still loading. Please try again in a moment.' });
                    }
                    return;
                }
                const address = buildAddress();
                if (!address) return;

                new google.maps.Geocoder().geocode({ address: address, region: 'NG' }, (results, status) => {
                    if (status === 'OK' && results[0]) {
                        const pos = results[0].geometry.location;
                        setCoords(pos.lat(), pos.lng());
                        showMap(pos.lat(), pos.lng(), true);
                    } else if (status === 'ZERO_RESULTS') {
                        if (typeof Swal !== 'undefined') Swal.fire({ icon: 'warning', title: 'Location not found', text: 'Google could not find: ' + address });
                    } else {
                        if (typeof Swal !== 'undefined') Swal.fire({ icon: 'error', title: 'Geocoding failed (' + status + ')', text: 'The map service rejected the request. Check that Billing and the Geocoding API are enabled for the API key.' });
                    }
                });
            }

            if (applyBtn) applyBtn.addEventListener('click', geocodeAndPin);

            // Auto-show an existing (backfilled) pin once Maps is ready.
            (function initExisting(retries) {
                const lat = parseFloat(latEl.value), lng = parseFloat(lngEl.value);
                if (!isFinite(lat) || !isFinite(lng)) return;
                if (gmapsReady()) { showMap(lat, lng, true); }
                else if (retries > 0) { setTimeout(() => initExisting(retries - 1), 300); }
            })(25);
        });
    </script>

    @push('scripts')
        {{-- Google Maps JS — powers the Property Location Map geocoder/pin --}}
        <script src="https://maps.googleapis.com/maps/api/js?key={{ config('services.google_maps.key') }}" defer></script>
    @endpush

@endsection