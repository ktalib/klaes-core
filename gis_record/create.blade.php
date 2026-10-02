@extends('layouts.app')
@section('page-title')
    {{ __('GIS Data Capture') }}
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
          <h2 class="text-xl font-bold">
            @if(request()->get('is') == 'secondary')
              {{ __('Capture Unit GIS Data') }}
            @elseif(request()->get('is') == 'primary')
              {{ __('Create New GIS  Record') }}
            @else
              {{ __('GIS Data Capture') }}
            @endif
          </h2>
        </div>
        
        <form action="{{ route('gis_record.store') }}" method="POST" enctype="multipart/form-data" class="space-y-8">
            @csrf
            
            <!-- Include the file summary header -->
            @include('gis_record.file_summary_header')
            
            <!-- File Information Section -->
            @if(request()->get('is') == 'secondary')
                <!-- Unit File Information Section -->
                @include('gis_record.secondary_fileno')
                <!-- Unit Form Section -->
                @include('gis_record.unit_form')
            @elseif(request()->get('is') == 'primary')
                <!-- Primary File Information Section -->
                @include('primaryform.gis_fileno')
            @else
                <!-- Default File Information Section -->
                @include('gis_record.secondary_fileno')
            @endif
            <!-- Plot Information Section -->
            <input type="hidden" name="gis_type" value="{{ request()->get('is') == 'secondary' ? 'Unit GIS' : 'Primary GIS' }}" class="">
            <div class="bg-gray-50 p-4 rounded-lg">
                <h3 class="text-lg font-semibold mb-4 text-gray-700">Plot Information</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div class="space-y-2">
                        <label for="plotNo" class="block text-sm font-medium text-gray-700">Plot Number</label>
                        <input type="text" id="plotNo" name="plotNo" class="w-full p-2 border border-gray-300 rounded-md text-sm">
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
                        <input type="number" step="0.0001" id="areaInHectares" name="areaInHectares" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                    </div>
                    
                    <div class="space-y-2">
                        <label for="landUse" class="block text-sm font-medium text-gray-700">Land Use</label>
                        <select id="landUse" name="landUse" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                            <option value="">Select land use</option>
                            <option value="Residential">Residential</option>
                            <option value="Commercial">Commercial</option>
                            <option value="Industrial">Industrial</option>  
                        </select>
                    </div>
                    
                    <div class="space-y-2">
                        <label for="specifically" class="block text-sm font-medium text-gray-700">Specific Use</label>
                        <input type="text" id="specifically" name="specifically" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                    </div>
                </div>
            </div>
            
            <!-- Location Information Section -->
            <div class="bg-gray-50 p-4 rounded-lg">
                <h3 class="text-lg font-semibold mb-4 text-gray-700">Location Information</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div class="space-y-2">
                        <label for="layoutName" class="block text-sm font-medium text-gray-700">Layout Name</label>
                        <input type="text" id="layoutName" name="layoutName" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                    </div>
                    
                    <div class="space-y-2">
                        <label for="districtName" class="block text-sm font-medium text-gray-700">District Name</label>
                        <input type="text" id="districtName" name="districtName" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                    </div>
                    
                     @include('components.lga')
                    
                    <div class="space-y-2">
                        <label for="StateName" class="block text-sm font-medium text-gray-700">State Name</label>
                        <input type="text" id="StateName" name="StateName" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                    </div>
                    
                    <div class="space-y-2">
                        <label for="streetName" class="block text-sm font-medium text-gray-700">Street Name</label>
                        <input type="text" id="streetName" name="streetName" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                    </div>
                    
                    <div class="space-y-2">
                        <label for="houseNo" class="block text-sm font-medium text-gray-700">House Number</label>
                        <input type="text" id="houseNo" name="houseNo" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                    </div>
                    
                    <div class="space-y-2">
                        <label for="houseType" class="block text-sm font-medium text-gray-700">House Type</label>
                        <input type="text" id="houseType" name="houseType" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                    </div>

                       <div class="space-y-2">
                        <label for="tenancy" class="block text-sm font-medium text-gray-700">Tenancy</label>
                        <input type="text" id="tenancy" name="tenancy" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                    </div>
                    
                     
                 
                </div>
            </div>
            
            <!-- Survey Information Section -->
            {{-- <div class="bg-gray-50 p-4 rounded-lg">
                <h3 class="text-lg font-semibold mb-4 text-gray-700">Survey Information</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div class="space-y-2">
                        <label for="surveyedBy" class="block text-sm font-medium text-gray-700">Surveyed By</label>
                        <input type="text" id="surveyedBy" name="surveyedBy" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                    </div>
                    
                    <div class="space-y-2">
                        <label for="drawnBy" class="block text-sm font-medium text-gray-700">Drawn By</label>
                        <input type="text" id="drawnBy" name="drawnBy" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                    </div>
                    
                    <div class="space-y-2">
                        <label for="checkedBy" class="block text-sm font-medium text-gray-700">Checked By</label>
                        <input type="text" id="checkedBy" name="checkedBy" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                    </div>
                    
                    <div class="space-y-2">
                        <label for="passedBy" class="block text-sm font-medium text-gray-700">Passed By</label>
                        <input type="text" id="passedBy" name="passedBy" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                    </div>
                    
                    <div class="space-y-2">
                        <label for="beaconControlName" class="block text-sm font-medium text-gray-700">Beacon Control Name</label>
                        <input type="text" id="beaconControlName" name="beaconControlName" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                    </div>
                    
                    <div class="space-y-2">
                        <label for="beaconControlX" class="block text-sm font-medium text-gray-700">Beacon Control X</label>
                        <input type="text" id="beaconControlX" name="beaconControlX" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                    </div>
                    
                    <div class="space-y-2">
                        <label for="beaconControlY" class="block text-sm font-medium text-gray-700">Beacon Control Y</label>
                        <input type="text" id="beaconControlY" name="beaconControlY" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                    </div>
                    
                    <div class="space-y-2">
                        <label for="metricSheetIndex" class="block text-sm font-medium text-gray-700">Metric Sheet Index</label>
                        <input type="text" id="metricSheetIndex" name="metricSheetIndex" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                    </div>
                    
                    <div class="space-y-2">
                        <label for="metricSheetNo" class="block text-sm font-medium text-gray-700">Metric Sheet Number</label>
                        <input type="text" id="metricSheetNo" name="metricSheetNo" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                    </div>
                    
                    <div class="space-y-2">
                        <label for="imperialSheet" class="block text-sm font-medium text-gray-700">Imperial Sheet</label>
                        <input type="text" id="imperialSheet" name="imperialSheet" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                    </div>
                    
                    <div class="space-y-2">
                        <label for="imperialSheetNo" class="block text-sm font-medium text-gray-700">Imperial Sheet Number</label>
                        <input type="text" id="imperialSheetNo" name="imperialSheetNo" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                    </div>
                    
                    <div class="space-y-2">
                        <label for="SurveyorGeneralSignatureDate" class="block text-sm font-medium text-gray-700">Surveyor General Signature Date</label>
                        <input type="date" id="SurveyorGeneralSignatureDate" name="SurveyorGeneralSignatureDate" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                    </div>
                </div>
            </div> --}}
            
            <!-- Title Information Section -->
            <div class="bg-gray-50 p-4 rounded-lg">
                <h3 class="text-lg font-semibold mb-4 text-gray-700">Title Information</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div class="space-y-2">
                        <label for="oldTitleSerialNo" class="block text-sm font-medium text-gray-700">Old Title Serial No</label>
                        <input type="text" id="oldTitleSerialNo" name="oldTitleSerialNo" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                    </div>
                    
                    <div class="space-y-2">
                        <label for="oldTitlePageNo" class="block text-sm font-medium text-gray-700">Old Title Page No</label>
                        <input type="text" id="oldTitlePageNo" name="oldTitlePageNo" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                    </div>
                    
                    <div class="space-y-2">
                        <label for="oldTitleVolumeNo" class="block text-sm font-medium text-gray-700">Old Title Volume No</label>
                        <input type="text" id="oldTitleVolumeNo" name="oldTitleVolumeNo" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                    </div>
                    
                    <div class="space-y-2">
                        <label for="deedsDate" class="block text-sm font-medium text-gray-700">Deeds Date</label>
                        <input type="date" id="deedsDate" name="deedsDate" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                    </div>
                    
                    <div class="space-y-2">
                        <label for="deedsTime" class="block text-sm font-medium text-gray-700">Deeds Time</label>
                        <input type="text" id="deedsTime" name="deedsTime" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                    </div>
                    
                    <div class="space-y-2">
                        <label for="certificateDate" class="block text-sm font-medium text-gray-700">Certificate Date</label>
                        <input type="date" id="certificateDate" name="certificateDate" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                    </div>
                    
                    <div class="space-y-2">
                        <label for="CofOSerialNo" class="block text-sm font-medium text-gray-700">CofO Serial No</label>
                        <input type="text" id="CofOSerialNo" name="CofOSerialNo" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                    </div>
                    
                    <div class="space-y-2">
                        <label for="titleIssuedYear" class="block text-sm font-medium text-gray-700">Title Issued Year</label>
                        <input type="number" id="titleIssuedYear" name="titleIssuedYear" min="1900" max="2099" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                    </div>
                </div>
            </div>
            
            <!-- Owner Information Section -->
            <div class="bg-gray-50 p-4 rounded-lg">
                <h3 class="text-lg font-semibold mb-4 text-gray-700">Owner Information</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div class="space-y-2">
                        <label for="originalAllottee" class="block text-sm font-medium text-gray-700">Original Allottee</label>
                        <input type="text" id="originalAllottee" name="originalAllottee" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                    </div>
                    
                    <div class="space-y-2">
                        <label for="addressOfOriginalAllottee" class="block text-sm font-medium text-gray-700">Address of Original Allottee</label>
                        <textarea id="addressOfOriginalAllottee" name="addressOfOriginalAllottee" rows="2" class="w-full p-2 border border-gray-300 rounded-md text-sm"></textarea>
                    </div>
                    
                    <div class="space-y-2">
                        <label for="changeOfOwnership" class="block text-sm font-medium text-gray-700">Change of Ownership</label>
                        <select id="changeOfOwnership" name="changeOfOwnership" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                            <option value="No">No</option>
                            <option value="Yes">Yes</option>
                        </select>
                    </div>
                    
                    <div class="space-y-2">
                        <label for="reasonForChange" class="block text-sm font-medium text-gray-700">Reason for Change</label>
                        <input type="text" id="reasonForChange" name="reasonForChange" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                    </div>
                    
                    <div class="space-y-2">
                        <label for="currentAllottee" class="block text-sm font-medium text-gray-700">Current Allottee</label>
                        <input type="text" id="currentAllottee" name="currentAllottee" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                    </div>
                    
                    <div class="space-y-2">
                        <label for="addressOfCurrentAllottee" class="block text-sm font-medium text-gray-700">Address of Current Allottee</label>
                        <textarea id="addressOfCurrentAllottee" name="addressOfCurrentAllottee" rows="2" class="w-full p-2 border border-gray-300 rounded-md text-sm"></textarea>
                    </div>
                    
                    <div class="space-y-2">
                        <label for="title" class="block text-sm font-medium text-gray-700">Title <span class="text-red-600">*</span></label>
                        <select id="title" name="titleOfCurrentAllottee" class="w-full p-2 border border-gray-300 rounded-md text-sm" required>
                            <option value="">Select Title</option>
                            <option value="CofO">CofO</option>
                            <option value="RofO">RofO</option>
                            <option value="Grant">Grant</option>
                            <option value="Occupancy Permit">Occupancy Permit</option>
                            <option value="SLTR">SLTR</option>
                            <option value="GIS">GIS</option>
                            <option value="Customary">Customary</option>
                            <option value="other">other</option>
                        </select>
                    </div>
                    
                    
                    <div class="space-y-2">
                        <label for="phoneNo" class="block text-sm font-medium text-gray-700">Phone Number</label>
                        <input type="tel" id="phoneNo" name="phoneNo" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                    </div>
                    
                    <div class="space-y-2">
                        <label for="emailAddress" class="block text-sm font-medium text-gray-700">Email Address</label>
                        <input type="email" id="emailAddress" name="emailAddress" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                    </div>
                    
                    <div class="space-y-2">
                        <label for="occupation" class="block text-sm font-medium text-gray-700">Occupation</label>
                        <input type="text" id="occupation" name="occupation" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                    </div>
                    
                    <div class="space-y-2">
                        <label for="nationality" class="block text-sm font-medium text-gray-700">Nationality</label>
                        <input type="text" id="nationality" name="nationality" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                    </div>
                    
                    <div class="space-y-2">
                        <label for="CompanyRCNo" class="block text-sm font-medium text-gray-700">Company RC Number</label>
                        <input type="text" id="CompanyRCNo" name="CompanyRCNo" class="w-full p-2 border border-gray-300 rounded-md text-sm">
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
                    
                    <div class="space-y-2">
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
                    
                    <div class="space-y-2">
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
                    
                    <div class="space-y-2">
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
                    
                    <div class="space-y-2">
                        <label for="SurveyPlan" class="block text-sm font-medium text-gray-700">Survey Plan</label>
                        <input type="file" id="SurveyPlan" name="SurveyPlan" class="block w-full text-sm text-gray-500
                          file:mr-4 file:py-2 file:px-4
                          file:rounded-md file:border-0
                          file:text-sm file:font-semibold
                          file:bg-blue-50 file:text-blue-700
                          hover:file:bg-blue-100">
                    </div>
                </div>
            </div>
            
            <!-- Debug form fields -->
            <div class="bg-gray-50 p-4 rounded-lg mt-4 hidden">
                <h3 class="text-lg font-semibold mb-4 text-gray-700">Debug Information</h3>
                <div class="p-2 bg-gray-100 rounded">
                    <pre id="formDebug" class="whitespace-pre-wrap text-xs"></pre>
                </div>
            </div>

            <!-- Submit Button -->
            <div class="flex justify-end space-x-3">
                <button type="button" onclick="window.history.back()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                    Cancel
                </button>
                <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                    Save GIS Data
                </button>
            </div>
        </form>
      </div>
    </div>
    <!-- Footer -->
    @include('admin.footer')
  </div>
</div>
 @include('gis_record.script                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                        ')
@endsection
