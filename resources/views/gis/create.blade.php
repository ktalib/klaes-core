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
              {{ __('Capture Primary GIS Data') }}
            @else
              {{ __('GIS Data Capture') }}
            @endif
          </h2>
        </div>
        
        <form action="{{ route('gis.store') }}" method="POST" enctype="multipart/form-data" class="space-y-8" onsubmit="return updateFormFileData()" x-data="surveyPlanUpload()">
            @csrf
            
            <!-- Include the file summary header -->
            @include('gis.file_summary_header')
            
            <!-- File Information Section -->
            <div class="bg-gray-50 p-4 rounded-lg mb-4">
                <h3 class="text-lg font-medium mb-4">
                    Create {{ request()->get('is') == 'secondary' ? 'Unit' : 'Primary' }} GIS Data
                </h3>
                
                <!-- Selection Grid Layout -->
                <div class="border border-gray-200 rounded-lg p-4 bg-gray-50 mb-4">
                    @if(request()->get('is') == 'secondary')
                        <!-- Grid for Unit GIS: Primary Survey + File Number -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- Primary Survey Selection -->
                            <div>
                                <label for="primary-survey-select" class="block text-sm font-medium text-gray-700 mb-1">Select Primary Survey</label>
                                <select id="primary-survey-select" class="w-full p-2.5 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    <option value="">-- Select Primary Survey FileNo --</option>
                                </select>
                            </div>
                            
                            <!-- File Number Selection -->
                            <div>
                                <label for="fileno-select" class="block text-sm font-medium text-gray-700 mb-1">Select File Number</label>
                                <select id="fileno-select" class="w-full p-2.5 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    <option value="">-- Select File Number --</option>
                                </select>
                            </div>
                        </div>
                    @else
                        <!-- Single column for Primary GIS: Smart File Number Selector -->
                        <div>
                            @include('components.smart_fileno_selector')
                        </div>
                    @endif
                </div>
            </div>
            
            @if(request()->get('is') == 'secondary')
                <!-- Unit File Information Section -->
                @include('gis.secondary_fileno')
                <!-- Unit Form Section -->
                @include('gis.unit_form')
                <input type="hidden" name="gis_type" value="Unit GIS">
            @elseif(request()->get('is') == 'primary')
                <!-- Primary File Information Section -->
                @include('gis.gis_fileno')
                <input type="hidden" name="gis_type" value="Primary GIS">
            @else
                <!-- Default File Information Section -->
                @include('gis.secondary_fileno')
                <input type="hidden" name="gis_type" value="Unit GIS">
            @endif
            
            <!-- Plot Information Section -->
            <div class="bg-gray-50 p-4 rounded-lg">
                <h3 class="text-lg font-semibold mb-4 text-gray-700">Plot Information</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div class="space-y-2">
                        <label for="plotNo" class="block text-sm font-medium text-gray-700">Plot Number <span class="text-red-600">*</span></label>
                        <input type="text" id="plotNo" name="plotNo" class="w-full p-2 border border-gray-300 rounded-md text-sm" required>
                    </div>
                    
                    <div class="space-y-2">
                        <label for="blockNo" class="block text-sm font-medium text-gray-700">Block Number <span class="text-red-600">*</span></label>
                        <input type="text" id="blockNo" name="blockNo" class="w-full p-2 border border-gray-300 rounded-md text-sm" required>
                    </div>
                    
                    <div class="space-y-2">
                        <label for="approvedPlanNo" class="block text-sm font-medium text-gray-700">Approved Plan Number <span class="text-red-600">*</span></label>
                        <input type="text" id="approvedPlanNo" name="approvedPlanNo" class="w-full p-2 border border-gray-300 rounded-md text-sm" required>
                    </div>
                    
                    <div class="space-y-2">
                        <label for="tpPlanNo" class="block text-sm font-medium text-gray-700">TP Plan Number <span class="text-red-600">*</span></label>
                        <input type="text" id="tpPlanNo" name="tpPlanNo" class="w-full p-2 border border-gray-300 rounded-md text-sm" required>
                    </div>
                    
                    <div class="space-y-2">
                        <label for="areaInHectares" class="block text-sm font-medium text-gray-700">Area (in Hectares) <span class="text-red-600">*</span></label>
                        <input type="number" step="0.0001" id="areaInHectares" name="areaInHectares" class="w-full p-2 border border-gray-300 rounded-md text-sm" required>
                    </div>
                    
                    @include('components.landuse')
                    @include('components.specifically')
                </div>
            </div>
             
            <!-- Location Information Section -->
            <div class="bg-gray-50 p-4 rounded-lg">
                <h3 class="text-lg font-semibold mb-4 text-gray-700">Location Information</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div class="space-y-2">
                        <label for="layoutName" class="block text-sm font-medium text-gray-700">Layout Name <span class="text-red-600">*</span></label>
                        <input type="text" id="layoutName" name="layoutName" class="w-full p-2 border border-gray-300 rounded-md text-sm" required>
                    </div>
                    
                    <!-- <div class="space-y-2">
                        <label for="districtName" class="block text-sm font-medium text-gray-700">District Name <span class="text-red-600">*</span></label>
                        <input type="text" id="districtName" name="districtName" class="w-full p-2 border border-gray-300 rounded-md text-sm" required>
                    </div> -->
                    @include('components.District2')
                    @include('components.lga')
        
                    <div class="space-y-2">
                        <label for="StateName" class="block text-sm font-medium text-gray-700">State Name <span class="text-red-600">*</span></label>
                        <input type="text" id="StateName" name="StateName" class="w-full p-2 border border-gray-300 rounded-md text-sm" required>
                    </div>
                    
                    @include('components.StreetName')

                    <div class="space-y-2">
                        <label for="houseNo" class="block text-sm font-medium text-gray-700">House Number <span class="text-red-600">*</span></label>
                        <input type="text" id="houseNo" name="houseNo" class="w-full p-2 border border-gray-300 rounded-md text-sm" required>
                    </div>
                    
                    @include('components.HouseType')

                    <div class="space-y-2">
                        <label for="tenancy" class="block text-sm font-medium text-gray-700">Tenancy <span class="text-red-600">*</span></label>
                        <input type="text" id="tenancy" name="tenancy" class="w-full p-2 border border-gray-300 rounded-md text-sm" required>
                    </div>
                </div>
            </div>
            
            <!-- Title Information Section -->
            <div class="bg-gray-50 p-4 rounded-lg">
                <h3 class="text-lg font-semibold mb-4 text-gray-700">Title Information</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div class="space-y-2">
                        <label for="oldTitleSerialNo" class="block text-sm font-medium text-gray-700">Old Title Serial No <span class="text-red-600">*</span></label>
                        <input type="text" id="oldTitleSerialNo" name="oldTitleSerialNo" class="w-full p-2 border border-gray-300 rounded-md text-sm" required>
                    </div>
                    
                    <div class="space-y-2">
                        <label for="oldTitlePageNo" class="block text-sm font-medium text-gray-700">Old Title Page No <span class="text-red-600">*</span></label>
                        <input type="text" id="oldTitlePageNo" name="oldTitlePageNo" class="w-full p-2 border border-gray-300 rounded-md text-sm" required>
                    </div>
                    
                    <div class="space-y-2">
                        <label for="oldTitleVolumeNo" class="block text-sm font-medium text-gray-700">Old Title Volume No <span class="text-red-600">*</span></label>
                        <input type="text" id="oldTitleVolumeNo" name="oldTitleVolumeNo" class="w-full p-2 border border-gray-300 rounded-md text-sm" required>
                    </div>
                    
                    <div class="space-y-2">
                        <label for="deedsDate" class="block text-sm font-medium text-gray-700">Deeds Date <span class="text-red-600">*</span></label>
                        <input type="date" id="deedsDate" name="deedsDate" class="w-full p-2 border border-gray-300 rounded-md text-sm" required>
                    </div>
                    
                    <div class="space-y-2">
                        <label for="deedsTime" class="block text-sm font-medium text-gray-700">Deeds Time <span class="text-red-600">*</span></label>
                        <input type="text" id="deedsTime" name="deedsTime" class="w-full p-2 border border-gray-300 rounded-md text-sm" required>
                    </div>
                    
                    <div class="space-y-2">
                        <label for="certificateDate" class="block text-sm font-medium text-gray-700">Certificate Date <span class="text-red-600">*</span></label>
                        <input type="date" id="certificateDate" name="certificateDate" class="w-full p-2 border border-gray-300 rounded-md text-sm" required>
                    </div>
                    
                    <div class="space-y-2">
                        <label for="CofOSerialNo" class="block text-sm font-medium text-gray-700">CofO Serial No <span class="text-red-600">*</span></label>
                        <input type="text" id="CofOSerialNo" name="CofOSerialNo" class="w-full p-2 border border-gray-300 rounded-md text-sm" required>
                    </div>
                    
                    <div class="space-y-2">
                        <label for="titleIssuedYear" class="block text-sm font-medium text-gray-700">Title Issued Year <span class="text-red-600">*</span></label>
                        <input type="number" id="titleIssuedYear" name="titleIssuedYear" min="1900" max="2099" class="w-full p-2 border border-gray-300 rounded-md text-sm" required>
                    </div>
                </div>
            </div>
            
            <!-- Owner Information Section -->
            <div class="bg-gray-50 p-4 rounded-lg">
                <h3 class="text-lg font-semibold mb-4 text-gray-700">Owner Information</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div class="space-y-2">
                        <label for="originalAllottee" class="block text-sm font-medium text-gray-700">Original Allottee <span class="text-red-600">*</span></label>
                        <input type="text" id="originalAllottee" name="originalAllottee" class="w-full p-2 border border-gray-300 rounded-md text-sm" required>
                    </div>
                    
                    <div class="space-y-2">
                        <label for="addressOfOriginalAllottee" class="block text-sm font-medium text-gray-700">Address of Original Allottee <span class="text-red-600">*</span></label>
                        <textarea id="addressOfOriginalAllottee" name="addressOfOriginalAllottee" rows="2" class="w-full p-2 border border-gray-300 rounded-md text-sm" required></textarea>
                    </div>
                    
                    <div class="space-y-2">
                        <label for="changeOfOwnership" class="block text-sm font-medium text-gray-700">Change of Ownership <span class="text-red-600">*</span></label>
                        <select id="changeOfOwnership" name="changeOfOwnership" class="w-full p-2 border border-gray-300 rounded-md text-sm" required>
                            <option value="No">No</option>
                            <option value="Yes">Yes</option>
                        </select>
                    </div>
                    
                    <div class="space-y-2">
                        <label for="reasonForChange" class="block text-sm font-medium text-gray-700">Reason for Change <span class="text-red-600">*</span></label>
                        <input type="text" id="reasonForChange" name="reasonForChange" class="w-full p-2 border border-gray-300 rounded-md text-sm" required>
                    </div>
                    
                    <div class="space-y-2">
                        <label for="currentAllottee" class="block text-sm font-medium text-gray-700">Current Allottee <span class="text-red-600">*</span></label>
                        <input type="text" id="currentAllottee" name="currentAllottee" class="w-full p-2 border border-gray-300 rounded-md text-sm" required>
                    </div>
                    
                    <div class="space-y-2">
                        <label for="addressOfCurrentAllottee" class="block text-sm font-medium text-gray-700">Address of Current Allottee <span class="text-red-600">*</span></label>
                        <textarea id="addressOfCurrentAllottee" name="addressOfCurrentAllottee" rows="2" class="w-full p-2 border border-gray-300 rounded-md text-sm" required></textarea>
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
                        <label for="phoneNo" class="block text-sm font-medium text-gray-700">Phone Number <span class="text-red-600">*</span></label>
                        <input type="tel" id="phoneNo" name="phoneNo" class="w-full p-2 border border-gray-300 rounded-md text-sm" required>
                    </div>
                    
                    <div class="space-y-2">
                        <label for="emailAddress" class="block text-sm font-medium text-gray-700">Email Address <span class="text-red-600">*</span></label>
                        <input type="email" id="emailAddress" name="emailAddress" class="w-full p-2 border border-gray-300 rounded-md text-sm" required>
                    </div>
                    
                    @include('components.Occupation')
                    
                    <div class="space-y-2">
                        <label for="nationality" class="block text-sm font-medium text-gray-700">Nationality <span class="text-red-600">*</span></label>
                        <input type="text" id="nationality" name="nationality" class="w-full p-2 border border-gray-300 rounded-md text-sm" required>
                    </div>
                    
                    <div class="space-y-2">
                        <label for="CompanyRCNo" class="block text-sm font-medium text-gray-700">Company RC Number <span class="text-red-600">*</span></label>
                        <input type="text" id="CompanyRCNo" name="CompanyRCNo" class="w-full p-2 border border-gray-300 rounded-md text-sm" required>
                    </div>
                </div>
            </div>
            
            <!-- Document Attachments Section -->
            <div class="bg-gray-50 p-4 rounded-lg">
                <h3 class="text-lg font-semibold mb-4 text-gray-700">Additional Document Attachments</h3>
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

            <!-- Upload Survey Plan Button (replacing submit button position) -->
            <div class="flex justify-end space-x-3">
                <button type="button" onclick="window.history.back()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                    Cancel
                </button>
                @if(request()->get('is') == 'primary')
                    <button type="button" id="viewSurveyPlanBtn" onclick="viewSurveyPlan()" 
                            class="inline-flex items-center px-4 py-2 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors duration-200 disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                        </svg>
                        View Survey Plan
                    </button>
                    <button type="submit" id="saveButton" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition-colors duration-200">
                        Save GIS Data
                    </button>
                @else
                    <button type="button" id="uploadSurveyPlanBtn" onclick="toggleSurveyPlanSection()" 
                            class="inline-flex items-center px-4 py-2 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors duration-200">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                        </svg>
                        Upload Survey Plan
                    </button>
                @endif
            </div>
            
            @if(request()->get('is') != 'primary')
                <!-- Survey Plan Upload Section (only for secondary/unit GIS) -->
                <div id="surveyPlanSection" class="bg-gradient-to-r from-blue-50 to-indigo-50 p-6 rounded-lg border border-blue-200 hidden">
                    <h4 class="text-lg font-semibold mb-4 text-blue-800 flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        Survey Plan Upload <span class="text-red-600">*</span>
                    </h4>
                    <div class="space-y-4">
                        <div class="relative">
                            <input type="file" id="surveyPlan" name="surveyPlan" accept=".pdf,.jpg,.jpeg,.png,.dwg,.dxf" 
                                   class="hidden" required onchange="handleSurveyPlanUpload(this)">
                            
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
                            <button type="submit" id="saveButton" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition-colors duration-200">
                                Save GIS Data
                            </button>
                        </div>
                    </div>
                </div>
            @endif
            
            <!-- Survey Plan View Modal (for primary GIS) -->
            @if(request()->get('is') == 'primary')
                <div id="surveyPlanModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
                    <div class="relative top-20 mx-auto p-5 border w-11/12 max-w-4xl shadow-lg rounded-md bg-white">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-bold text-gray-900">Survey Plan</h3>
                            <button type="button" onclick="closeSurveyPlanModal()" class="text-gray-400 hover:text-gray-600">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>
                        <div id="surveyPlanContent" class="min-h-96">
                            <!-- Survey plan content will be loaded here -->
                            <div class="flex items-center justify-center h-96">
                                <div class="text-gray-500">
                                    <svg class="w-16 h-16 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                    </svg>
                                    <p>Select a file number to view survey plan</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </form>
      </div>
    </div>
    <!-- Footer -->
    @include('admin.footer')
  </div>
</div>
@include('gis.script')

<script>
// Survey Plan viewing functions for primary GIS
function viewSurveyPlan() {
    const viewSurveyPlanBtn = document.getElementById('viewSurveyPlanBtn');
    if (!viewSurveyPlanBtn || viewSurveyPlanBtn.disabled) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'No File Selected',
                text: 'Please select a file number first to view its survey plan.',
                icon: 'warning',
                confirmButtonText: 'OK'
            });
        } else {
            alert('Please select a file number first to view its survey plan.');
        }
        return;
    }
    
    const applicationId = viewSurveyPlanBtn.getAttribute('data-application-id');
    const fileNumber = viewSurveyPlanBtn.getAttribute('data-file-number');
    
    if (!applicationId || !fileNumber) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'Error',
                text: 'Unable to retrieve application information.',
                icon: 'error',
                confirmButtonText: 'OK'
            });
        } else {
            alert('Unable to retrieve application information.');
        }
        return;
    }
    
    // Show loading state
    viewSurveyPlanBtn.innerHTML = `
        <svg class="w-4 h-4 mr-2 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
        </svg>
        Loading...
    `;
    viewSurveyPlanBtn.disabled = true;
    
    // Fetch survey plan from mother applications
    fetch(`/api/survey-plan/${applicationId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.survey_plan) {
                showSurveyPlanModal(data.survey_plan, fileNumber);
            } else {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: 'No Survey Plan Found',
                        text: data.message || 'No survey plan is available for this application.',
                        icon: 'info',
                        confirmButtonText: 'OK'
                    });
                } else {
                    alert(data.message || 'No survey plan is available for this application.');
                }
            }
        })
        .catch(error => {
            console.error('Error fetching survey plan:', error);
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Error',
                    text: 'Failed to load survey plan. Please try again.',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
            } else {
                alert('Failed to load survey plan. Please try again.');
            }
        })
        .finally(() => {
            // Reset button state
            viewSurveyPlanBtn.innerHTML = `
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                </svg>
                View Survey Plan
            `;
            viewSurveyPlanBtn.disabled = false;
        });
}

function showSurveyPlanModal(surveyPlan, fileNumber) {
    const modal = document.getElementById('surveyPlanModal');
    const content = document.getElementById('surveyPlanContent');
    
    if (!modal || !content) return;
    
    // Update modal title
    const modalTitle = modal.querySelector('h3');
    if (modalTitle) {
        modalTitle.textContent = `Survey Plan - ${fileNumber}`;
    }
    
    // Clear existing content
    content.innerHTML = '';
    
    // Create survey plan content
    const surveyPlanContainer = document.createElement('div');
    surveyPlanContainer.className = 'survey-plan-container';
    
    if (surveyPlan.path) {
        const fileExtension = surveyPlan.original_name ? surveyPlan.original_name.split('.').pop().toLowerCase() : 'unknown';
        const filePath = `/storage/${surveyPlan.path}`;
        
        if (['jpg', 'jpeg', 'png', 'gif'].includes(fileExtension)) {
            // Image file
            surveyPlanContainer.innerHTML = `
                <div class="text-center">
                    <img src="${filePath}" alt="Survey Plan" class="max-w-full h-auto mx-auto border border-gray-300 rounded-lg shadow-sm">
                    <div class="mt-4 text-sm text-gray-600">
                        <p><strong>File:</strong> ${surveyPlan.original_name}</p>
                        <p><strong>Uploaded:</strong> ${new Date(surveyPlan.uploaded_at).toLocaleDateString()}</p>
                    </div>
                    <div class="mt-4">
                        <a href="${filePath}" target="_blank" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                            </svg>
                            Open in New Tab
                        </a>
                    </div>
                </div>
            `;
        } else if (fileExtension === 'pdf') {
            // PDF file
            surveyPlanContainer.innerHTML = `
                <div class="text-center">
                    <div class="bg-gray-100 border border-gray-300 rounded-lg p-8 mb-4">
                        <svg class="w-16 h-16 mx-auto mb-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        <p class="text-lg font-medium text-gray-900 mb-2">PDF Survey Plan</p>
                        <p class="text-sm text-gray-600 mb-4">${surveyPlan.original_name}</p>
                    </div>
                    <div class="text-sm text-gray-600 mb-4">
                        <p><strong>Uploaded:</strong> ${new Date(surveyPlan.uploaded_at).toLocaleDateString()}</p>
                    </div>
                    <div class="space-x-2">
                        <a href="${filePath}" target="_blank" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                            </svg>
                            Open PDF
                        </a>
                        <a href="${filePath}" download class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            Download
                        </a>
                    </div>
                </div>
            `;
        } else {
            // Other file types
            surveyPlanContainer.innerHTML = `
                <div class="text-center">
                    <div class="bg-gray-100 border border-gray-300 rounded-lg p-8 mb-4">
                        <svg class="w-16 h-16 mx-auto mb-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        <p class="text-lg font-medium text-gray-900 mb-2">Survey Plan Document</p>
                        <p class="text-sm text-gray-600 mb-4">${surveyPlan.original_name}</p>
                    </div>
                    <div class="text-sm text-gray-600 mb-4">
                        <p><strong>Type:</strong> ${fileExtension.toUpperCase()}</p>
                        <p><strong>Uploaded:</strong> ${new Date(surveyPlan.uploaded_at).toLocaleDateString()}</p>
                    </div>
                    <div class="space-x-2">
                        <a href="${filePath}" target="_blank" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                            </svg>
                            Open File
                        </a>
                        <a href="${filePath}" download class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            Download
                        </a>
                    </div>
                </div>
            `;
        }
    } else {
        surveyPlanContainer.innerHTML = `
            <div class="text-center py-12">
                <svg class="w-16 h-16 mx-auto mb-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <p class="text-gray-500">No survey plan file available</p>
            </div>
        `;
    }
    
    content.appendChild(surveyPlanContainer);
    
    // Show modal
    modal.classList.remove('hidden');
}

function closeSurveyPlanModal() {
    const modal = document.getElementById('surveyPlanModal');
    if (modal) {
        modal.classList.add('hidden');
    }
}

// Close modal when clicking outside
document.addEventListener('click', function(event) {
    const modal = document.getElementById('surveyPlanModal');
    if (modal && event.target === modal) {
        closeSurveyPlanModal();
    }
});

// Close modal with Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeSurveyPlanModal();
    }
});
</script>

@endsection