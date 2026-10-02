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
        
        <form action="{{ route('gis_record.store') }}" method="POST" enctype="multipart/form-data" class="space-y-8" onsubmit="return validateFormSubmission(event)">
            @csrf
            <input type="hidden" name="application_id" id="application_id" value="">
            <input type="hidden" name="sub_application_id" id="sub_application_id" value="">
            
            <!-- Include the file summary header -->
            @include('gis_record.file_summary_header')
            
            <!-- Smart File Number Selection -->
            <div class="border border-gray-200 rounded-lg p-4 bg-gray-50 mb-4">
                <div>
                    <!-- Smart File Number Selection -->
                    <div>
                        @include('components.smart_fileno_selector')
                    </div>
                </div>
            </div>
            
           
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
                    
                 
                      @include('components.landuse')
                      @include('components.specifically')
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
                    
                      @include('components.District2') 
                    
                     @include('components.lga')
                    
                    <div class="space-y-2">
                        <label for="StateName" class="block text-sm font-medium text-gray-700">State Name</label>
                        <input type="text" id="StateName" name="StateName" class="w-full p-2 border border-gray-300 rounded-md text-sm">
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
                        <select id="title" name="titleOfCurrentAllottee" class="w-full p-2 border border-gray-300 rounded-md text-sm" onchange="toggleOtherTitle()" required>
                            <option value="">Select Title</option>
                            <option value="CofO">CofO</option>
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
                        <input type="tel" id="phoneNo" name="phoneNo" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                    </div>
                    
                    <div class="space-y-2">
                        <label for="emailAddress" class="block text-sm font-medium text-gray-700">Email Address</label>
                        <input type="email" id="emailAddress" name="emailAddress" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                    </div>
                    
                   @include('components.Occupation') 
                   @include('components.nationality')
                    
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
                </div>
            </div>
            
            <!-- Debug form fields -->
            <div class="bg-gray-50 p-4 rounded-lg mt-4 hidden">
                <h3 class="text-lg font-semibold mb-4 text-gray-700">Debug Information</h3>
                <div class="p-2 bg-gray-100 rounded">
                    <pre id="formDebug" class="whitespace-pre-wrap text-xs"></pre>
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
 @include('gis_record.script')

<!-- Include Select2 CSS and JS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Get all form inputs and disable them initially
    const formInputs = document.querySelectorAll('form input:not([type="hidden"]):not([type="submit"]), form select:not(#fileno-select), form textarea');
    
    const filenoSelect = document.getElementById('fileno-select');
    const saveButton = document.getElementById('saveButton');
    const filenoInput = document.getElementById('fileno'); // Manual file number input
    
    // IDs of dropdowns/fields to control
    const controlledFields = [
        'plotNo',
        'blockNo',
        'approvedPlanNo',
        'tpPlanNo',
        'layoutName',
        'districtName',
        'lga_name'
    ];
    
    // Required fields for form validation
    const requiredFields = [
        'fileno',
        'title', // Title is marked as required
        'plotNo',
        'blockNo',
        'currentAllottee'
    ];
    
    let selectedApplication = null;
    let formUnlocked = false;
    const isSecondary = '{{ request()->get('is') }}' === 'secondary';
    
    // Initially disable save button
    if (saveButton) saveButton.disabled = true;
    
    // Function to enable/disable form inputs
    function toggleFormInputs(enable) {
        formInputs.forEach(input => {
            if (input.id !== 'fileno') { // Don't disable the manual fileno input
                input.disabled = !enable;
            }
        });
        controlledFields.forEach(id => {
            const el = document.getElementById(id);
            if (el) el.disabled = !enable;
        });
        formUnlocked = enable;
        
        if (enable) {
            checkFormValidity(); // Check if save button should be enabled
        } else {
            if (saveButton) saveButton.disabled = true;
        }
    }
    
    // Function to check if all required fields are filled
    function checkFormValidity() {
        if (!formUnlocked) {
            if (saveButton) saveButton.disabled = true;
            return;
        }
        
        let allRequiredFilled = true;
        
        requiredFields.forEach(fieldId => {
            const field = document.getElementById(fieldId);
            if (field) {
                if (fieldId === 'title') {
                    // Special handling for title field
                    const titleSelect = document.getElementById('title');
                    const otherTitleInput = document.getElementById('otherTitle');
                    if (titleSelect && titleSelect.value === '') {
                        allRequiredFilled = false;
                    } else if (titleSelect && titleSelect.value === 'other' && otherTitleInput && otherTitleInput.value.trim() === '') {
                        allRequiredFilled = false;
                    }
                } else if (field.value.trim() === '') {
                    allRequiredFilled = false;
                }
            }
        });
        
        if (saveButton) {
            saveButton.disabled = !allRequiredFilled;
        }
    }
    
    // Monitor manual file number input
    if (filenoInput) {
        filenoInput.addEventListener('input', function() {
            const value = this.value.trim();
            if (value.length > 0) {
                // Enable form when file number is manually entered
                toggleFormInputs(true);
            } else {
                // Disable form when file number is cleared and no dropdown selection
                if (!selectedApplication) {
                    toggleFormInputs(false);
                }
            }
        });
    }
    
    // Monitor all form inputs for validation
    formInputs.forEach(input => {
        input.addEventListener('input', checkFormValidity);
        input.addEventListener('change', checkFormValidity);
    });
    
    // Initially disable all form inputs except fileno
    toggleFormInputs(false);

    // Initialize Select2 if filenoSelect exists
    if (filenoSelect) {
        $(filenoSelect).select2({
            placeholder: "Search for a file number...",
            allowClear: true,
            minimumInputLength: 0,
            ajax: {
                url: '{{ route('survey_cadastral.search-fileno') }}',
                dataType: 'json',
                delay: 250,
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                data: function(params) {
                    return {
                        fileno: params.term || '',
                        type: isSecondary ? 'secondary' : 'primary',
                        initial: params.term ? false : true
                    };
                },
                processResults: function(data, params) {
                    let results = [];
                    
                    if (data.success && data.application) {
                        results.push({
                            id: data.application.id,
                            text: data.application.fileno,
                            application: data.application
                        });
                    } else if (data.success && data.applications) {
                        results = data.applications.map(app => {
                            return {
                                id: app.id,
                                text: app.fileno + (app.applicant_type === 'individual' ? 
                                       ' - ' + app.first_name + ' ' + app.surname : 
                                       app.applicant_type === 'corporate' ? 
                                       ' - ' + app.corporate_name : ''),
                                application: app
                            };
                        });
                    }
                    
                    return {
                        results: results,
                        pagination: {
                            more: data.pagination && data.pagination.more
                        }
                    };
                },
                cache: true
            }
        });

        // Trigger initial data load when dropdown is opened for the first time
        $(filenoSelect).on('select2:open', function() {
            if (!$(filenoSelect).data('initial-load-done')) {
                const $search = $('.select2-search__field');
                $search.val('');
                $search.trigger('input');
                $(filenoSelect).data('initial-load-done', true);
            }
        });

        // Handle select change
        $(filenoSelect).on('select2:select', function(e) {
            const data = e.params.data;
            selectedApplication = data.application;
            
            if (selectedApplication) {
                // Use the handleDropdownSelection function from smart fileno selector
                if (typeof window.handleDropdownSelection === 'function') {
                    window.handleDropdownSelection(selectedApplication);
                }
                
                // Populate hidden fields based on type
                if (isSecondary) {
                    document.getElementById('sub_application_id').value = selectedApplication.id;
                    document.getElementById('application_id').value = '';
                    selectedApplication.isSecondary = true;
                } else {
                    document.getElementById('application_id').value = selectedApplication.id;
                    document.getElementById('sub_application_id').value = '';
                    selectedApplication.isSecondary = false;
                }
                
                // Enable form
                toggleFormInputs(true);
                
                // Auto-populate form fields from application data
                populateFormFields(selectedApplication);
                
                // Show success message
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: 'Application Selected',
                        text: 'The form has been unlocked. You can now enter GIS data.',
                        icon: 'success',
                        confirmButtonText: 'Continue'
                    });
                }
            }
        });

        // Handle clear event
        $(filenoSelect).on('select2:clear', function() {
            // Disable form
            toggleFormInputs(false);
            
            // Clear hidden fields
            document.getElementById('application_id').value = '';
            document.getElementById('sub_application_id').value = '';
            
            selectedApplication = null;
        });
    }
    
    // Function to populate form fields from application data
    function populateFormFields(application) {
        // Populate plot information if available
        if (application.plot_no) {
            const plotNoInput = document.getElementById('plotNo');
            if (plotNoInput) plotNoInput.value = application.plot_no;
        }
        
        if (application.block_no) {
            const blockNoInput = document.getElementById('blockNo');
            if (blockNoInput) blockNoInput.value = application.block_no;
        }
        
        if (application.approved_plan_no) {
            const approvedPlanNoInput = document.getElementById('approvedPlanNo');
            if (approvedPlanNoInput) approvedPlanNoInput.value = application.approved_plan_no;
        }
        
        if (application.tp_plan_no) {
            const tpPlanNoInput = document.getElementById('tpPlanNo');
            if (tpPlanNoInput) tpPlanNoInput.value = application.tp_plan_no;
        }
        
        // Populate location information
        if (application.layout_name) {
            const layoutNameInput = document.getElementById('layoutName');
            if (layoutNameInput) layoutNameInput.value = application.layout_name;
        }
        
        if (application.district_name) {
            const districtNameInput = document.getElementById('districtName');
            if (districtNameInput) districtNameInput.value = application.district_name;
        }
        
        if (application.lga_name) {
            const lgaNameInput = document.getElementById('lga_name');
            if (lgaNameInput) lgaNameInput.value = application.lga_name;
        }
        
        // Populate land use if available
        if (application.land_use) {
            const landUseInput = document.getElementById('landuse');
            if (landUseInput) landUseInput.value = application.land_use;
        }
        
        // Populate applicant information
        if (application.applicant_type === 'individual') {
            const currentAllotteeInput = document.getElementById('currentAllottee');
            if (currentAllotteeInput) {
                currentAllotteeInput.value = `${application.applicant_title || ''} ${application.first_name || ''} ${application.surname || ''}`.trim();
            }
        } else if (application.applicant_type === 'corporate') {
            const currentAllotteeInput = document.getElementById('currentAllottee');
            if (currentAllotteeInput) {
                currentAllotteeInput.value = application.corporate_name || '';
            }
        }
        
        // Populate contact information if available
        if (application.phone_number) {
            const phoneNoInput = document.getElementById('phoneNo');
            if (phoneNoInput) phoneNoInput.value = application.phone_number;
        }
        
        if (application.email) {
            const emailInput = document.getElementById('emailAddress');
            if (emailInput) emailInput.value = application.email;
        }
    }
});

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
    
    // Don't automatically enable save button here - let form validation handle it
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
    
    input.value = '';
    preview.classList.add('hidden');
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

// Form validation function
function validateFormSubmission(event) {
    // Ensure file numbers are populated before validation
    if (typeof ensureFileNumbersPopulated === 'function') {
        ensureFileNumbersPopulated();
    }
    
    // Validate file numbers
    if (typeof validateFileNumbers === 'function') {
        const validation = validateFileNumbers();
        if (!validation.isValid) {
            event.preventDefault();
            
            // Show error message
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Validation Error',
                    text: validation.message,
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
            } else {
                alert(validation.message);
            }
            return false;
        }
    }
    
    return true;
}
</script>
@endsection
