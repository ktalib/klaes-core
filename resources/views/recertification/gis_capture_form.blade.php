@extends('layouts.app')
@section('page-title')
    {{ __('GIS Data Capture - Recertification') }}
@endsection

@include('sectionaltitling.partials.assets.css')
@section('content')
<div class="flex-1 overflow-auto">
    <!-- Header -->
    @include('admin.header')
    <!-- Dashboard Content -->
    <div class="p-6">
      <!-- GIS Data Capture Form -->
      <div class="bg-white rounded-md shadow-sm border border-gray-200 p-6">
        <div class="flex justify-between items-center mb-6">
          <div>
            <h2 class="text-xl font-bold">GIS Data Capture - Recertification</h2>
            <p class="text-gray-600">Capture GIS data for recertification application: {{ $application->file_number ?? 'N/A' }}</p>
          </div>
          <div class="flex items-center gap-3">
            <a href="{{ route('recertification.details', $application->id) }}" class="inline-flex items-center justify-center rounded-md font-medium text-sm px-3 py-2 transition-all cursor-pointer bg-transparent border border-gray-300 text-gray-700 hover:bg-gray-50 gap-2">
              <i data-lucide="arrow-left" class="h-4 w-4"></i>
              Back to Application
            </a>
          </div>
        </div>
        
        <form action="{{ route('recertification.gis-capture.store', $application->id) }}" method="POST" enctype="multipart/form-data" class="space-y-8">
            @csrf
            <input type="hidden" name="recertification_application_id" value="{{ $application->id }}">
            <input type="hidden" name="gis_type" value="recertification">
            <input type="hidden" name="mlsfNo" value="{{ $application->file_number }}">
            
            <!-- Application Summary -->
            <div class="bg-blue-50 p-4 rounded-lg border border-blue-200">
                <h3 class="text-lg font-semibold mb-4 text-blue-800">Application Summary</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">

                <div class="space-y-2">
                        <label class="block text-sm font-medium text-blue-700">New KANGIS File No</label>
                        <div class="w-full p-2 bg-white border border-blue-300 rounded-md text-sm font-mono">
                            {{ $application->NewKANGISFileno ?? 'N/A' }}
                        </div>
                    </div>
                    
                    <div class="space-y-2">
                        <label class="block text-sm font-medium text-blue-700">KANGIS File No</label>
                        <div class="w-full p-2 bg-white border border-blue-300 rounded-md text-sm font-mono">
                            {{ $application->kangisFileNo ?? 'N/A' }}
                        </div>
                    </div>
                    
                    <div class="space-y-2">
                        <label class="block text-sm font-medium text-blue-700">MLPP File No</label>
                        <div class="w-full p-2 bg-white border border-blue-300 rounded-md text-sm font-mono">
                            {{ $application->mlsfNo ?? 'N/A' }}
                        </div>
                    </div>

                    <!-- <div class="space-y-2">
                        <label class="block text-sm font-medium text-blue-700">File Number</label>
                        <div class="w-full p-2 bg-white border border-blue-300 rounded-md text-sm font-mono font-semibold text-blue-900">
                            {{ $application->file_number ?? 'N/A' }}
                        </div>
                    </div> -->
                    
                    <div class="space-y-2">
                        <label class="block text-sm font-medium text-blue-700">Applicant Name</label>
                        <div class="w-full p-2 bg-white border border-blue-300 rounded-md text-sm">
                            @if($application->applicant_type === 'Corporate')
                                {{ $application->organisation_name ?? 'N/A' }}
                            @else
                                {{ trim(($application->surname ?? '') . ' ' . ($application->first_name ?? '')) ?: 'N/A' }}
                            @endif
                        </div>
                    </div>
                    
                    <div class="space-y-2">
                        <label class="block text-sm font-medium text-blue-700">Plot Number</label>
                        <div class="w-full p-2 bg-white border border-blue-300 rounded-md text-sm">
                            {{ $application->plot_number ?? 'N/A' }}
                        </div>
                    </div>
                    
                    <div class="space-y-2">
                        <label class="block text-sm font-medium text-blue-700">Layout/District</label>
                        <div class="w-full p-2 bg-white border border-blue-300 rounded-md text-sm">
                            {{ $application->layout_district ?? 'N/A' }}
                        </div>
                    </div>
                    
                    <div class="space-y-2">
                        <label class="block text-sm font-medium text-blue-700">LGA</label>
                        <div class="w-full p-2 bg-white border border-blue-300 rounded-md text-sm">
                            {{ $application->lga_name ?? 'N/A' }}
                        </div>
                    </div>
                    
                    <div class="space-y-2">
                        <label class="block text-sm font-medium text-blue-700">C-of-O Number</label>
                        <div class="w-full p-2 bg-white border border-blue-300 rounded-md text-sm font-mono">
                            {{ $application->cofo_number ?? 'N/A' }}
                        </div>
                    </div>
                    
                  
                </div>
            </div>
            
            <!-- Plot Information Section -->
            <div class="bg-gray-50 p-4 rounded-lg">
                <h3 class="text-lg font-semibold mb-4 text-gray-700">Plot Information</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div class="space-y-2">
                        <label for="plotNo" class="block text-sm font-medium text-gray-700">Plot Number</label>
                        <input type="text" id="plotNo" name="plotNo" value="{{ $application->plot_number }}" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                    </div>
                    
                    <div class="space-y-2">
                        <label for="blockNo" class="block text-sm font-medium text-gray-700">Block Number</label>
                        <input type="text" id="blockNo" name="blockNo" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                    </div>
                    
                    <div class="space-y-2">
                        <label for="approvedPlanNo" class="block text-sm font-medium text-gray-700">Approved Plan Number</label>
                        <input type="text" id="approvedPlanNo" name="approvedPlanNo" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                    </div>
                    
                    <div class="space-y-2">
                        <label for="tpPlanNo" class="block text-sm font-medium text-gray-700">TP Plan Number</label>
                        <input type="text" id="tpPlanNo" name="tpPlanNo" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                    </div>
                    
                    <div class="space-y-2">
                        <label for="areaInHectares" class="block text-sm font-medium text-gray-700">Area (in Hectares)</label>
                        <input type="number" step="0.0001" id="areaInHectares" name="areaInHectares" value="{{ $application->plot_size }}" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                    </div>
                    
                    <div class="space-y-2">
                        <label for="areaInHectares" class="block text-sm font-medium text-gray-700">Land Use</label>
                        <input type="text" step="0.0001" id="areaInHectares" name="areaInHectares" value="{{ $application->current_land_use }}" class="w-full p-2 border border-gray-300 rounded-md text-sm" readonl>
                    </div>

                    <!-- @include('components.landuse') -->
                    @include('components.specifically')
                </div>
            </div>
            
            <!-- Location Information Section -->
            <div class="bg-gray-50 p-4 rounded-lg">
                <h3 class="text-lg font-semibold mb-4 text-gray-700">Location Information</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div class="space-y-2">
                        <label for="layoutName" class="block text-sm font-medium text-gray-700">Layout Name</label>
                        <input type="text" id="layoutName" name="layoutName" value="{{ $application->layout_district }}" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                    </div>
                    
                    <div class="space-y-2">
                        <label for="districtName" class="block text-sm font-medium text-gray-700">District Name</label>
                        <input type="text" id="districtName" name="districtName" value="{{ $application->layout_district }}" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                    </div>
                    
                    <div class="space-y-2">
                        <label for="lgaName" class="block text-sm font-medium text-gray-700">LGA Name</label>
                        <input type="text" id="lgaName" name="lgaName" value="{{ $application->lga_name }}" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                    </div>
                    
                    <div class="space-y-2">
                        <label for="StateName" class="block text-sm font-medium text-gray-700">State Name</label>
                        <input type="text" id="StateName" name="StateName" value="{{ $application->state_name ?? 'Kano' }}" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                    </div>
                    
                    @include('components.StreetName')
                    
                    <div class="space-y-2">
                        <label for="houseNo" class="block text-sm font-medium text-gray-700">House Number</label>
                        <input type="text" id="houseNo" name="houseNo" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                    </div>
                    
                    @include('components.HouseType')

                    <div class="space-y-2">
                        <label for="tenancy" class="block text-sm font-medium text-gray-700">Tenancy</label>
                        <input type="text" id="tenancy" name="tenancy" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                    </div>
                </div>
            </div>
            
            <!-- Title Information Section -->
            <div class="bg-gray-50 p-4 rounded-lg">
                <h3 class="text-lg font-semibold mb-4 text-gray-700">Title Information</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div class="space-y-2">
                        <label for="oldTitleSerialNo" class="block text-sm font-medium text-gray-700">Old Title Serial No</label>
                        <input type="text" id="oldTitleSerialNo" name="oldTitleSerialNo" value="{{ $application->serial_no }}" class="w-full p-2 border border-gray-300 rounded-md text-sm bg-gray-100" readonly>
                    </div>
                    
                    <div class="space-y-2">
                        <label for="oldTitlePageNo" class="block text-sm font-medium text-gray-700">Old Title Page No</label>
                        <input type="text" id="oldTitlePageNo" name="oldTitlePageNo" value="{{ $application->reg_page }}" class="w-full p-2 border border-gray-300 rounded-md text-sm bg-gray-100" readonly>
                    </div>
                    
                    <div class="space-y-2">
                        <label for="oldTitleVolumeNo" class="block text-sm font-medium text-gray-700">Old Title Volume No</label>
                        <input type="text" id="oldTitleVolumeNo" name="oldTitleVolumeNo" value="{{ $application->reg_volume }}" class="w-full p-2 border border-gray-300 rounded-md text-sm bg-gray-100" readonly>
                    </div>
                    
                    <div class="space-y-2">
                        <label for="deedsDate" class="block text-sm font-medium text-gray-700">Deeds Date</label>
                        <input type="date" id="deedsDate" name="deedsDate" value="{{ date('Y-m-d') }}" class="w-full p-2 border border-gray-300 rounded-md text-sm bg-gray-100" readonly>
                    </div>
                    
                    <div class="space-y-2">
                        <label for="deedsTime" class="block text-sm font-medium text-gray-700">Deeds Time</label>
                        <input type="time" id="deedsTime" name="deedsTime" value="{{ date('H:i') }}" class="w-full p-2 border border-gray-300 rounded-md text-sm bg-gray-100" readonly>
                    </div>
                    
                    <div class="space-y-2">
                        <label for="certificateDate" class="block text-sm font-medium text-gray-700">Certificate Date</label>
                        <input type="date" id="certificateDate" name="certificateDate" value="{{ $application->commencement_date }}" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                    </div>
                    
                    <div class="space-y-2">
                        <label for="CofOSerialNo" class="block text-sm font-medium text-gray-700">CofO Serial No</label>
                        <input type="text" id="CofOSerialNo" name="CofOSerialNo" value="{{ $application->cofo_number }}" class="w-full p-2 border border-gray-300 rounded-md text-sm bg-gray-100" readonly>
                    </div>
                    
                    <div class="space-y-2">
                        <label for="titleIssuedYear" class="block text-sm font-medium text-gray-700">Title Issued Year</label>
                        <input type="number" id="titleIssuedYear" name="titleIssuedYear" min="1900" max="2099" value="{{ date('Y', strtotime($application->commencement_date ?? 'now')) }}" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                    </div>
                </div>
            </div>
            
            <!-- Owner Information Section -->
            <div class="bg-gray-50 p-4 rounded-lg">
                <h3 class="text-lg font-semibold mb-4 text-gray-700">Owner Information</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div class="space-y-2">
                        <label for="originalAllottee" class="block text-sm font-medium text-gray-700">Original Allottee</label>
                        <input type="text" id="originalAllottee" name="originalAllottee" value="{{ trim(($application->title_holder_title ?? '') . ' ' . ($application->title_holder_surname ?? '') . ' ' . ($application->title_holder_first_name ?? '')) }}" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                    </div>
                    
                    <div class="space-y-2">
                        <label for="addressOfOriginalAllottee" class="block text-sm font-medium text-gray-700">Address of Original Allottee</label>
                        <textarea id="addressOfOriginalAllottee" name="addressOfOriginalAllottee" rows="2" class="w-full p-2 border border-gray-300 rounded-md text-sm">{{ collect([$application->address_line1, $application->address_line2, $application->city_town, $application->state_name])->filter()->implode(', ') }}</textarea>
                    </div>
                    
                    <div class="space-y-2">
                        <label for="changeOfOwnership" class="block text-sm font-medium text-gray-700">Change of Ownership</label>
                        <select id="changeOfOwnership" name="changeOfOwnership" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                            <option value="No" {{ $application->is_original_owner == 1 ? 'selected' : '' }}>No</option>
                            <option value="Yes" {{ $application->is_original_owner == 0 ? 'selected' : '' }}>Yes</option>
                        </select>
                    </div>
                    
                    <div class="space-y-2">
                        <label for="reasonForChange" class="block text-sm font-medium text-gray-700">Reason for Change</label>
                        <input type="text" id="reasonForChange" name="reasonForChange" value="{{ $application->instrument_type }}" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                    </div>
                    
                    <div class="space-y-2">
                        <label for="currentAllottee" class="block text-sm font-medium text-gray-700">Current Allottee</label>
                        <input type="text" id="currentAllottee" name="currentAllottee" value="@if($application->applicant_type === 'Corporate'){{ $application->organisation_name }}@else{{ trim(($application->surname ?? '') . ' ' . ($application->first_name ?? '')) }}@endif" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                    </div>
                    
                    <div class="space-y-2">
                        <label for="addressOfCurrentAllottee" class="block text-sm font-medium text-gray-700">Address of Current Allottee</label>
                        <textarea id="addressOfCurrentAllottee" name="addressOfCurrentAllottee" rows="2" class="w-full p-2 border border-gray-300 rounded-md text-sm">{{ collect([$application->address_line1, $application->address_line2, $application->city_town, $application->state_name])->filter()->implode(', ') }}</textarea>
                    </div>
                    
                    <div class="space-y-2">
                        <label for="title" class="block text-sm font-medium text-gray-700">Title <span class="text-red-600">*</span></label>
                        <select id="title" name="titleOfCurrentAllottee" class="w-full p-2 border border-gray-300 rounded-md text-sm" onchange="toggleOtherTitle()" required>
                            <option value="">Select Title</option>
                            <option value="CofO" selected>CofO</option>
                            <option value="RofO">RofO</option>
                            <option value="Grant">Grant</option>
                            <option value="Occupancy Permit">Occupancy Permit</option>
                            <option value="SLTR">SLTR</option>
                            <option value="GIS">GIS</option>
                            <option value="Customary">Customary</option>
                            <option value="other">Other</option>
                        </select>
                        <input type="text" id="otherTitle" name="titleOfCurrentAllottee" class="w-full p-2 border border-gray-300 rounded-md text-sm mt-2 hidden" placeholder="Please specify other title">
                    </div>

                    <script>
                    function toggleOtherTitle() {
                        const select = document.getElementById('title');
                        const otherInput = document.getElementById('otherTitle');
                        if (select.value === 'other') {
                            otherInput.classList.remove('hidden');
                            select.name = '';
                        } else {
                            otherInput.classList.add('hidden');
                            select.name = 'titleOfCurrentAllottee';
                            otherInput.name = '';
                        }
                    }
                    </script>
                    
                    <div class="space-y-2">
                        <label for="phoneNo" class="block text-sm font-medium text-gray-700">Phone Number</label>
                        <input type="tel" id="phoneNo" name="phoneNo" value="{{ $application->phone_no }}" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                    </div>
                    
                    <div class="space-y-2">
                        <label for="emailAddress" class="block text-sm font-medium text-gray-700">Email Address</label>
                        <input type="email" id="emailAddress" name="emailAddress" value="{{ $application->email_address }}" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                    </div>
                    
                    <div class="space-y-2">
                        <label for="occupation" class="block text-sm font-medium text-gray-700">Occupation</label>
                        <input type="text" id="occupation" name="occupation" value="{{ $application->occupation }}" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                    </div>
                    
                    <div class="space-y-2">
                        <label for="nationality" class="block text-sm font-medium text-gray-700">Nationality</label>
                        <input type="text" id="nationality" name="nationality" value="{{ $application->nationality }}" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                    </div>
                    
                    <!-- Company RC Number - Only for Corporate/Government -->
                    <div class="space-y-2" id="companyRCSection" style="display: {{ in_array(strtolower($application->applicant_type ?? ''), ['corporate', 'government']) ? 'block' : 'none' }};">
                        <label for="CompanyRCNo" class="block text-sm font-medium text-gray-700">Company RC Number</label>
                        <input type="text" id="CompanyRCNo" name="CompanyRCNo" value="{{ $application->cac_registration_no }}" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                    </div>
                </div>
            </div>
            
            <!-- Document Attachments Section -->
            <div class="bg-gray-50 p-4 rounded-lg">
                <h3 class="text-lg font-semibold mb-4 text-gray-700">Document Attachments</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="space-y-2">
                        <label for="transactionDocument" class="block text-sm font-medium text-gray-700">Transaction Document</label>
                        <input type="file" id="transactionDocument" name="transactionDocument" class="block w-full text-sm text-gray-500
                          file:mr-4 file:py-2 file:px-4
                          file:rounded-md file:border-0
                          file:text-sm file:font-semibold
                          file:bg-blue-50 file:text-blue-700
                          hover:file:bg-blue-100">
                    </div>
                    
                    <!-- Passport Photo - Hidden for Corporate/Government -->
                    <div class="space-y-2" id="passportPhotoSection" style="display: {{ in_array(strtolower($application->applicant_type ?? ''), ['corporate', 'government']) ? 'none' : 'block' }};">
                        <label for="passportPhoto" class="block text-sm font-medium text-gray-700">Passport Photo</label>
                        <input type="file" id="passportPhoto" name="passportPhoto" class="block w-full text-sm text-gray-500
                          file:mr-4 file:py-2 file:px-4
                          file:rounded-md file:border-0
                          file:text-sm file:font-semibold
                          file:bg-blue-50 file:text-blue-700
                          hover:file:bg-blue-100">
                    </div>
                    
                    <div class="space-y-2">
                        <label for="nationalId" class="block text-sm font-medium text-gray-700">National ID</label>
                        <input type="file" id="nationalId" name="nationalId" class="block w-full text-sm text-gray-500
                          file:mr-4 file:py-2 file:px-4
                          file:rounded-md file:border-0
                          file:text-sm file:font-semibold
                          file:bg-blue-50 file:text-blue-700
                          hover:file:bg-blue-100">
                    </div>
                    
                    <div class="space-y-2">
                        <label for="internationalPassport" class="block text-sm font-medium text-gray-700">International Passport</label>
                        <input type="file" id="internationalPassport" name="internationalPassport" class="block w-full text-sm text-gray-500
                          file:mr-4 file:py-2 file:px-4
                          file:rounded-md file:border-0
                          file:text-sm file:font-semibold
                          file:bg-blue-50 file:text-blue-700
                          hover:file:bg-blue-100">
                    </div>
                    
                    <!-- Business Registration Certificate - Only for Corporate/Government -->
                    <div class="space-y-2" id="businessRegCertSection" style="display: {{ in_array(strtolower($application->applicant_type ?? ''), ['corporate', 'government']) ? 'block' : 'none' }};">
                        <label for="businessRegCert" class="block text-sm font-medium text-gray-700">Business Registration Certificate</label>
                        <input type="file" id="businessRegCert" name="businessRegCert" class="block w-full text-sm text-gray-500
                          file:mr-4 file:py-2 file:px-4
                          file:rounded-md file:border-0
                          file:text-sm file:font-semibold
                          file:bg-blue-50 file:text-blue-700
                          hover:file:bg-blue-100">
                    </div>
                    
                    <div class="space-y-2">
                        <label for="formCO7AndCO4" class="block text-sm font-medium text-gray-700">Form CO7 and CO4</label>
                        <input type="file" id="formCO7AndCO4" name="formCO7AndCO4" class="block w-full text-sm text-gray-500
                          file:mr-4 file:py-2 file:px-4
                          file:rounded-md file:border-0
                          file:text-sm file:font-semibold
                          file:bg-blue-50 file:text-blue-700
                          hover:file:bg-blue-100">
                    </div>
                    
                    <!-- Certificate of Incorporation - Only for Corporate/Government -->
                    <div class="space-y-2" id="certOfIncorporationSection" style="display: {{ in_array(strtolower($application->applicant_type ?? ''), ['corporate', 'government']) ? 'block' : 'none' }};">
                        <label for="certOfIncorporation" class="block text-sm font-medium text-gray-700">Certificate of Incorporation</label>
                        <input type="file" id="certOfIncorporation" name="certOfIncorporation" class="block w-full text-sm text-gray-500
                          file:mr-4 file:py-2 file:px-4
                          file:rounded-md file:border-0
                          file:text-sm file:font-semibold
                          file:bg-blue-50 file:text-blue-700
                          hover:file:bg-blue-100">
                    </div>
                    
                    <div class="space-y-2">
                        <label for="memorandumAndArticle" class="block text-sm font-medium text-gray-700">Memorandum and Articles</label>
                        <input type="file" id="memorandumAndArticle" name="memorandumAndArticle" class="block w-full text-sm text-gray-500
                          file:mr-4 file:py-2 file:px-4
                          file:rounded-md file:border-0
                          file:text-sm file:font-semibold
                          file:bg-blue-50 file:text-blue-700
                          hover:file:bg-blue-100">
                    </div>
                    
                    <div class="space-y-2">
                        <label for="letterOfAdmin" class="block text-sm font-medium text-gray-700">Letter of Administration</label>
                        <input type="file" id="letterOfAdmin" name="letterOfAdmin" class="block w-full text-sm text-gray-500
                          file:mr-4 file:py-2 file:px-4
                          file:rounded-md file:border-0
                          file:text-sm file:font-semibold
                          file:bg-blue-50 file:text-blue-700
                          hover:file:bg-blue-100">
                    </div>
                    
                    <div class="space-y-2">
                        <label for="courtAffidavit" class="block text-sm font-medium text-gray-700">Court Affidavit</label>
                        <input type="file" id="courtAffidavit" name="courtAffidavit" class="block w-full text-sm text-gray-500
                          file:mr-4 file:py-2 file:px-4
                          file:rounded-md file:border-0
                          file:text-sm file:font-semibold
                          file:bg-blue-50 file:text-blue-700
                          hover:file:bg-blue-100">
                    </div>
                    
                    <div class="space-y-2">
                        <label for="policeReport" class="block text-sm font-medium text-gray-700">Police Report</label>
                        <input type="file" id="policeReport" name="policeReport" class="block w-full text-sm text-gray-500
                          file:mr-4 file:py-2 file:px-4
                          file:rounded-md file:border-0
                          file:text-sm file:font-semibold
                          file:bg-blue-50 file:text-blue-700
                          hover:file:bg-blue-100">
                    </div>
                    
                    <div class="space-y-2">
                        <label for="newspaperAdvert" class="block text-sm font-medium text-gray-700">Newspaper Advertisement</label>
                        <input type="file" id="newspaperAdvert" name="newspaperAdvert" class="block w-full text-sm text-gray-500
                          file:mr-4 file:py-2 file:px-4
                          file:rounded-md file:border-0
                          file:text-sm file:font-semibold
                          file:bg-blue-50 file:text-blue-700
                          hover:file:bg-blue-100">
                    </div>
                    
                    <div class="space-y-2">
                        <label for="picture" class="block text-sm font-medium text-gray-700">Picture</label>
                        <input type="file" id="picture" name="picture" class="block w-full text-sm text-gray-500
                          file:mr-4 file:py-2 file:px-4
                          file:rounded-md file:border-0
                          file:text-sm file:font-semibold
                          file:bg-blue-50 file:text-blue-700
                          hover:file:bg-blue-100">
                    </div>
                </div>
            </div>

            <!-- Upload Survey Plan Button -->
            <div class="flex justify-end space-x-3">
                <button type="button" onclick="window.history.back()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                    Cancel
                </button>
                <button type="button" id="uploadSurveyPlanBtn" onclick="toggleSurveyPlanSection()" 
                        class="inline-flex items-center px-4 py-2 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors duration-200">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                    </svg>
                    Upload Survey Plan
                </button>
            </div>
            
            <!-- Survey Plan Upload Section -->
            <div id="surveyPlanSection" class="bg-gradient-to-r from-blue-50 to-indigo-50 p-6 rounded-lg border border-blue-200 hidden">
                <h4 class="text-lg font-semibold mb-4 text-blue-800 flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    Survey Plan Upload <span class="text-red-600">*</span>
                </h4>
                <div class="space-y-4">
                    <div class="relative">
                        <input type="file" id="surveyPlan" name="SurveyPlan" accept=".pdf,.jpg,.jpeg,.png,.dwg,.dxf" 
                               class="hidden" required onchange="handleSurveyPlanUpload(this)">
                        
                        <!-- File Upload Area -->
                        <div class="border-2 border-dashed border-blue-300 rounded-lg p-6 text-center hover:border-blue-400 transition-colors duration-200 cursor-pointer" 
                             onclick="document.getElementById('surveyPlan').click()">
                            <div class="flex flex-col items-center">
                                <svg class="w-12 h-12 text-blue-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                                </svg>
                                <h3 class="text-lg font-medium text-blue-700 mb-2">Upload Survey Plan</h3>
                                <p class="text-sm text-blue-600 mb-4">Drag and drop your survey plan file here, or click to browse</p>
                                <p class="text-xs text-blue-500">Supported formats: PDF, JPG, PNG, DWG, DXF (Max: 10MB)</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- File Preview Area -->
                    <div id="surveyPlanPreview" class="hidden">
                        <div class="bg-white p-4 rounded-lg border border-gray-200">
                            <div class="flex items-center justify-between mb-3">
                                <h5 class="text-sm font-medium text-gray-700">Survey Plan Preview</h5>
                                <button type="button" onclick="removeSurveyPlan()" class="text-red-600 hover:text-red-800 text-sm">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                </button>
                            </div>
                            <div id="previewContent" class="text-center">
                                <!-- Preview content will be inserted here -->
                            </div>
                            <div id="fileInfo" class="mt-3 text-xs text-gray-500">
                                <!-- File info will be inserted here -->
                            </div>
                        </div>
                    </div>
                    
                    <!-- Submit Button (appears after survey plan upload) -->
                    <div class="flex justify-end space-x-3 mt-4">
                        <button type="submit" id="saveButton" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition-colors duration-200 disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                            Save GIS Data
                        </button>
                    </div>
                </div>
            </div>
        </form>
      </div>
    </div>
    <!-- Footer -->
    @include('admin.footer')
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const saveButton = document.getElementById('saveButton');
    
    // Initially disable save button
    if (saveButton) saveButton.disabled = true;
    
    // Initialize document visibility based on applicant type
    initializeDocumentVisibility();
});

// Initialize document visibility based on applicant type
function initializeDocumentVisibility() {
    const applicantType = '{{ strtolower($application->applicant_type ?? "") }}';
    const passportSection = document.getElementById('passportPhotoSection');
    const certIncorporationSection = document.getElementById('certOfIncorporationSection');
    const businessRegCertSection = document.getElementById('businessRegCertSection');
    const companyRCSection = document.getElementById('companyRCSection');
    
    if (applicantType === 'corporate' || applicantType === 'government') {
        // Hide passport photo for corporate/government
        if (passportSection) passportSection.style.display = 'none';
        // Show certificate of incorporation for corporate/government
        if (certIncorporationSection) certIncorporationSection.style.display = 'block';
        // Show business registration certificate for corporate/government
        if (businessRegCertSection) businessRegCertSection.style.display = 'block';
        // Show company RC number for corporate/government
        if (companyRCSection) companyRCSection.style.display = 'block';
    } else {
        // Show passport photo for individuals
        if (passportSection) passportSection.style.display = 'block';
        // Hide certificate of incorporation for individuals
        if (certIncorporationSection) certIncorporationSection.style.display = 'none';
        // Hide business registration certificate for individuals
        if (businessRegCertSection) businessRegCertSection.style.display = 'none';
        // Hide company RC number for individuals
        if (companyRCSection) companyRCSection.style.display = 'none';
    }
}

// Survey Plan Upload Functions
function toggleSurveyPlanSection() {
    const section = document.getElementById('surveyPlanSection');
    const button = document.getElementById('uploadSurveyPlanBtn');
    
    if (section.classList.contains('hidden')) {
        section.classList.remove('hidden');
        button.textContent = 'Hide Upload Section';
    } else {
        section.classList.add('hidden');
        button.innerHTML = `
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
            </svg>
            Upload Survey Plan
        `;
    }
}

function handleSurveyPlanUpload(input) {
    const file = input.files[0];
    if (!file) return;
    
    // Validate file size (10MB limit)
    const maxSize = 10 * 1024 * 1024;
    if (file.size > maxSize) {
        alert('File size must be less than 10MB');
        input.value = '';
        return;
    }
    
    // Validate file type
    const allowedExtensions = ['.pdf', '.jpg', '.jpeg', '.png', '.dwg', '.dxf'];
    const fileExtension = '.' + file.name.split('.').pop().toLowerCase();
    
    if (!allowedExtensions.includes(fileExtension)) {
        alert('Please select a valid file type (PDF, JPG, PNG, DWG, DXF)');
        input.value = '';
        return;
    }
    
    // Show preview
    showSurveyPlanPreview(file);
    
    // Enable save button
    const saveButton = document.getElementById('saveButton');
    if (saveButton) saveButton.disabled = false;
}

function showSurveyPlanPreview(file) {
    const preview = document.getElementById('surveyPlanPreview');
    const previewContent = document.getElementById('previewContent');
    const fileInfo = document.getElementById('fileInfo');
    
    // Show file info
    fileInfo.innerHTML = `
        <strong>File:</strong> ${file.name}<br>
        <strong>Size:</strong> ${formatFileSize(file.size)}<br>
        <strong>Type:</strong> ${file.type || 'Unknown'}
    `;
    
    // Show preview based on file type
    if (file.type.startsWith('image/')) {
        const reader = new FileReader();
        reader.onload = function(e) {
            previewContent.innerHTML = `
                <img src="${e.target.result}" alt="Survey Plan Preview" 
                     class="max-w-full h-auto max-h-64 mx-auto rounded border">
            `;
        };
        reader.readAsDataURL(file);
    } else if (file.type === 'application/pdf') {
        previewContent.innerHTML = `
            <div class="flex flex-col items-center p-8 bg-gray-100 rounded">
                <svg class="w-16 h-16 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <p class="text-gray-600">PDF Document</p>
                <p class="text-sm text-gray-500">Preview not available</p>
            </div>
        `;
    } else {
        previewContent.innerHTML = `
            <div class="flex flex-col items-center p-8 bg-gray-100 rounded">
                <svg class="w-16 h-16 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <p class="text-gray-600">File Uploaded</p>
                <p class="text-sm text-gray-500">Preview not available for this file type</p>
            </div>
        `;
    }
    
    preview.classList.remove('hidden');
}

function removeSurveyPlan() {
    const input = document.getElementById('surveyPlan');
    const preview = document.getElementById('surveyPlanPreview');
    const saveButton = document.getElementById('saveButton');
    
    input.value = '';
    preview.classList.add('hidden');
    if (saveButton) saveButton.disabled = true;
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

// Form submission handling
document.querySelector('form').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const saveButton = document.getElementById('saveButton');
    
    // Disable button and show loading
    saveButton.disabled = true;
    saveButton.innerHTML = 'Saving...';
    
    fetch(this.action, {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('GIS data captured successfully!');
            window.location.href = '{{ route("recertification.details", $application->id) }}';
        } else {
            alert('Error: ' + (data.message || 'Failed to save GIS data'));
            saveButton.disabled = false;
            saveButton.innerHTML = 'Save GIS Data';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while saving GIS data');
        saveButton.disabled = false;
        saveButton.innerHTML = 'Save GIS Data';
    });
});
</script>

@endsection