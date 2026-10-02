@extends('layouts.app')
@section('page-title')
    {{ __('Edit GIS Data') }}
@endsection

@include('sectionaltitling.partials.assets.css')
@section('content')
<div class="flex-1 overflow-auto">
    <!-- Header -->
    @include('admin.header')
    <!-- Dashboard Content -->
    <div class="p-6">
      <!-- GIS Data Edit Form -->
      <div class="bg-white rounded-md shadow-sm border border-gray-200 p-6">
        <div class="flex justify-between items-center mb-6">
          <h2 class="text-xl font-bold">Edit GIS Data</h2>
        </div>
        
        <form action="{{ route('gis_record.update', $gisData->id) }}" method="POST" enctype="multipart/form-data" class="space-y-8">
            @csrf
            @method('PUT')
            
            <!-- File Information Section -->
            <div class="bg-gray-50 p-4 rounded-lg">
                <h3 class="text-lg font-semibold mb-4 text-gray-700">File Information</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div class="space-y-2">
                        <label for="mlsfNo" class="block text-sm font-medium text-gray-700">MLSF Number</label>
                        <input type="text" id="mlsfNo" name="mlsfNo" value="{{ $gisData->mlsfNo ?? '' }}" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                    </div>
                    
                    <div class="space-y-2">
                        <label for="kangisFileNo" class="block text-sm font-medium text-gray-700">KANGIS File Number</label>
                        <input type="text" id="kangisFileNo" name="kangisFileNo" value="{{ $gisData->kangisFileNo ?? '' }}" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                    </div>
                    
                    <div class="space-y-2">
                        <label for="NewKANGISFileno" class="block text-sm font-medium text-gray-700">New KANGIS File Number</label>
                        <input type="text" id="NewKANGISFileno" name="NewKANGISFileno" value="{{ $gisData->NewKANGISFileno ?? '' }}" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                    </div>
                </div>
            </div>
            
            <!-- Plot Information Section -->
            <div class="bg-gray-50 p-4 rounded-lg">
                <h3 class="text-lg font-semibold mb-4 text-gray-700">Plot Information</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div class="space-y-2">
                        <label for="plotNo" class="block text-sm font-medium text-gray-700">Plot Number</label>
                        <input type="text" id="plotNo" name="plotNo" value="{{ $gisData->plotNo ?? '' }}" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                    </div>
                    
                    <div class="space-y-2">
                        <label for="blockNo" class="block text-sm font-medium text-gray-700">Block Number</label>
                        <input type="text" id="blockNo" name="blockNo" value="{{ $gisData->blockNo ?? '' }}" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                    </div>
                    
                    <div class="space-y-2">
                        <label for="approvedPlanNo" class="block text-sm font-medium text-gray-700">Approved Plan Number</label>
                        <input type="text" id="approvedPlanNo" name="approvedPlanNo" value="{{ $gisData->approvedPlanNo ?? '' }}" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                    </div>
                    
                    <div class="space-y-2">
                        <label for="tpPlanNo" class="block text-sm font-medium text-gray-700">TP Plan Number</label>
                        <input type="text" id="tpPlanNo" name="tpPlanNo" value="{{ $gisData->tpPlanNo ?? '' }}" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                    </div>
                    
                    <div class="space-y-2">
                        <label for="areaInHectares" class="block text-sm font-medium text-gray-700">Area (in Hectares)</label>
                        <input type="number" step="0.0001" id="areaInHectares" name="areaInHectares" value="{{ $gisData->areaInHectares ?? '' }}" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                    </div>
                    
                    <div class="space-y-2">
                        <label for="landUse" class="block text-sm font-medium text-gray-700">Land Use</label>
                        <select id="landUse" name="landUse" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                            <option value="">Select land use</option>
                            <option value="Residential" {{ $gisData->landUse == 'Residential' ? 'selected' : '' }}>Residential</option>
                            <option value="Commercial" {{ $gisData->landUse == 'Commercial' ? 'selected' : '' }}>Commercial</option>
                            <option value="Industrial" {{ $gisData->landUse == 'Industrial' ? 'selected' : '' }}>Industrial</option>  
                        </select>
                    </div>
                    
                    <div class="space-y-2">
                        <label for="specifically" class="block text-sm font-medium text-gray-700">Specific Use</label>
                        <input type="text" id="specifically" name="specifically" value="{{ $gisData->specifically ?? '' }}" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                    </div>
                </div>
            </div>
            
            <!-- Location Information Section -->
            <div class="bg-gray-50 p-4 rounded-lg">
                <h3 class="text-lg font-semibold mb-4 text-gray-700">Location Information</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div class="space-y-2">
                        <label for="layoutName" class="block text-sm font-medium text-gray-700">Layout Name</label>
                        <input type="text" id="layoutName" name="layoutName" value="{{ $gisData->layoutName ?? '' }}" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                    </div>
                    
                    <div class="space-y-2">
                        <label for="districtName" class="block text-sm font-medium text-gray-700">District Name</label>
                        <input type="text" id="districtName" name="districtName" value="{{ $gisData->districtName ?? '' }}" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                    </div>
                    
                    <div class="space-y-2">
                        <label for="lgaName" class="block text-sm font-medium text-gray-700">LGA Name</label>
                        <input type="text" id="lgaName" name="lgaName" value="{{ $gisData->lgaName ?? '' }}" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                    </div>
                    
                    <div class="space-y-2">
                        <label for="StateName" class="block text-sm font-medium text-gray-700">State Name</label>
                        <input type="text" id="StateName" name="StateName" value="{{ $gisData->StateName ?? '' }}" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                    </div>
                    
                    <div class="space-y-2">
                        <label for="streetName" class="block text-sm font-medium text-gray-700">Street Name</label>
                        <input type="text" id="streetName" name="streetName" value="{{ $gisData->streetName ?? '' }}" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                    </div>
                    
                    <div class="space-y-2">
                        <label for="houseNo" class="block text-sm font-medium text-gray-700">House Number</label>
                        <input type="text" id="houseNo" name="houseNo" value="{{ $gisData->houseNo ?? '' }}" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                    </div>
                    
                    <div class="space-y-2">
                        <label for="houseType" class="block text-sm font-medium text-gray-700">House Type</label>
                        <input type="text" id="houseType" name="houseType" value="{{ $gisData->houseType ?? '' }}" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                    </div>
                    
                    <div class="space-y-2">
                        <label for="tenancy" class="block text-sm font-medium text-gray-700">Tenancy</label>
                        <select id="tenancy" name="tenancy" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                            <option value="">Select tenancy type</option>
                            <option value="Owner Occupied" {{ $gisData->tenancy == 'Owner Occupied' ? 'selected' : '' }}>Owner Occupied</option>
                            <option value="Rented" {{ $gisData->tenancy == 'Rented' ? 'selected' : '' }}>Rented</option>
                            <option value="Leased" {{ $gisData->tenancy == 'Leased' ? 'selected' : '' }}>Leased</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <!-- Title Information Section -->
            <div class="bg-gray-50 p-4 rounded-lg">
                <h3 class="text-lg font-semibold mb-4 text-gray-700">Title Information</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div class="space-y-2">
                        <label for="oldTitleSerialNo" class="block text-sm font-medium text-gray-700">Old Title Serial No</label>
                        <input type="text" id="oldTitleSerialNo" name="oldTitleSerialNo" value="{{ $gisData->oldTitleSerialNo ?? '' }}" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                    </div>
                    
                    <div class="space-y-2">
                        <label for="oldTitlePageNo" class="block text-sm font-medium text-gray-700">Old Title Page No</label>
                        <input type="text" id="oldTitlePageNo" name="oldTitlePageNo" value="{{ $gisData->oldTitlePageNo ?? '' }}" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                    </div>
                    
                    <div class="space-y-2">
                        <label for="oldTitleVolumeNo" class="block text-sm font-medium text-gray-700">Old Title Volume No</label>
                        <input type="text" id="oldTitleVolumeNo" name="oldTitleVolumeNo" value="{{ $gisData->oldTitleVolumeNo ?? '' }}" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                    </div>
                    
                    <div class="space-y-2">
                        <label for="deedsDate" class="block text-sm font-medium text-gray-700">Deeds Date</label>
                        <input type="date" id="deedsDate" name="deedsDate" value="{{ $gisData->deedsDate ? date('Y-m-d', strtotime($gisData->deedsDate)) : '' }}" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                    </div>
                    
                    <div class="space-y-2">
                        <label for="deedsTime" class="block text-sm font-medium text-gray-700">Deeds Time</label>
                        <input type="time" id="deedsTime" name="deedsTime" value="{{ $gisData->deedsTime ?? '' }}" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                    </div>
                    
                    <div class="space-y-2">
                        <label for="certificateDate" class="block text-sm font-medium text-gray-700">Certificate Date</label>
                        <input type="date" id="certificateDate" name="certificateDate" value="{{ $gisData->certificateDate ? date('Y-m-d', strtotime($gisData->certificateDate)) : '' }}" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                    </div>
                    
                    <div class="space-y-2">
                        <label for="CofOSerialNo" class="block text-sm font-medium text-gray-700">CofO Serial No</label>
                        <input type="text" id="CofOSerialNo" name="CofOSerialNo" value="{{ $gisData->CofOSerialNo ?? '' }}" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                    </div>
                    
                    <div class="space-y-2">
                        <label for="titleIssuedYear" class="block text-sm font-medium text-gray-700">Title Issued Year</label>
                        <input type="number" id="titleIssuedYear" name="titleIssuedYear" min="1900" max="2099" value="{{ $gisData->titleIssuedYear ?? '' }}" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                    </div>
                </div>
            </div>
            
            <!-- Owner Information Section -->
            <div class="bg-gray-50 p-4 rounded-lg">
                <h3 class="text-lg font-semibold mb-4 text-gray-700">Owner Information</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div class="space-y-2">
                        <label for="originalAllottee" class="block text-sm font-medium text-gray-700">Original Allottee</label>
                        <input type="text" id="originalAllottee" name="originalAllottee" value="{{ $gisData->originalAllottee ?? '' }}" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                    </div>
                    
                    <div class="space-y-2">
                        <label for="addressOfOriginalAllottee" class="block text-sm font-medium text-gray-700">Address of Original Allottee</label>
                        <textarea id="addressOfOriginalAllottee" name="addressOfOriginalAllottee" rows="2" class="w-full p-2 border border-gray-300 rounded-md text-sm">{{ $gisData->addressOfOriginalAllottee ?? '' }}</textarea>
                    </div>
                    
                    <div class="space-y-2">
                        <label for="changeOfOwnership" class="block text-sm font-medium text-gray-700">Change of Ownership</label>
                        <select id="changeOfOwnership" name="changeOfOwnership" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                            <option value="No" {{ ($gisData->changeOfOwnership ?? '') == 'No' ? 'selected' : '' }}>No</option>
                            <option value="Yes" {{ ($gisData->changeOfOwnership ?? '') == 'Yes' ? 'selected' : '' }}>Yes</option>
                        </select>
                    </div>
                    
                    <div class="space-y-2">
                        <label for="reasonForChange" class="block text-sm font-medium text-gray-700">Reason for Change</label>
                        <input type="text" id="reasonForChange" name="reasonForChange" value="{{ $gisData->reasonForChange ?? '' }}" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                    </div>
                    
                    <div class="space-y-2">
                        <label for="currentAllottee" class="block text-sm font-medium text-gray-700">Current Allottee</label>
                        <input type="text" id="currentAllottee" name="currentAllottee" value="{{ $gisData->currentAllottee ?? '' }}" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                    </div>
                    
                    <div class="space-y-2">
                        <label for="addressOfCurrentAllottee" class="block text-sm font-medium text-gray-700">Address of Current Allottee</label>
                        <textarea id="addressOfCurrentAllottee" name="addressOfCurrentAllottee" rows="2" class="w-full p-2 border border-gray-300 rounded-md text-sm">{{ $gisData->addressOfCurrentAllottee ?? '' }}</textarea>
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
                        <input type="tel" id="phoneNo" name="phoneNo" value="{{ $gisData->phoneNo ?? '' }}" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                    </div>
                    
                    <div class="space-y-2">
                        <label for="emailAddress" class="block text-sm font-medium text-gray-700">Email Address</label>
                        <input type="email" id="emailAddress" name="emailAddress" value="{{ $gisData->emailAddress ?? '' }}" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                    </div>
                    
                    <div class="space-y-2">
                        <label for="occupation" class="block text-sm font-medium text-gray-700">Occupation</label>
                        <input type="text" id="occupation" name="occupation" value="{{ $gisData->occupation ?? '' }}" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                    </div>
                    
                    <div class="space-y-2">
                        <label for="nationality" class="block text-sm font-medium text-gray-700">Nationality</label>
                        <input type="text" id="nationality" name="nationality" value="{{ $gisData->nationality ?? '' }}" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                    </div>
                    
                    <div class="space-y-2">
                        <label for="CompanyRCNo" class="block text-sm font-medium text-gray-700">Company RC Number</label>
                        <input type="text" id="CompanyRCNo" name="CompanyRCNo" value="{{ $gisData->CompanyRCNo ?? '' }}" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                    </div>
                </div>
            </div>
            
            <!-- Document Attachments Section -->
            <div class="bg-gray-50 p-4 rounded-lg">
                <h3 class="text-lg font-semibold mb-4 text-gray-700">Document Attachments</h3>
                <div class="p-3 mb-4 bg-yellow-50 border-l-4 border-yellow-400 rounded">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 text-yellow-600 mr-2" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2h-1V9z" clip-rule="evenodd"></path>
                        </svg>
                        <p class="font-medium text-sm text-yellow-700">Important: Upload new files only if you want to replace the existing ones.</p>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="space-y-2">
                        <label for="transactionDocument" class="block text-sm font-medium text-gray-700">Transaction Document</label>
                        @if(isset($gisData->transactionDocument) && $gisData->transactionDocument)
                            <p class="text-xs text-blue-600 mb-1">Current file: <a href="{{ asset('storage/'.$gisData->transactionDocument) }}" target="_blank" class="underline">View Document</a></p>
                        @endif
                        <input type="file" id="transactionDocument" name="transactionDocument" class="block w-full text-sm text-gray-500
                          file:mr-4 file:py-2 file:px-4
                          file:rounded-md file:border-0
                          file:text-sm file:font-semibold
                          file:bg-blue-50 file:text-blue-700
                          hover:file:bg-blue-100">
                    </div>
                    
                    <div class="space-y-2">
                        <label for="passportPhoto" class="block text-sm font-medium text-gray-700">Passport Photo</label>
                        @if(isset($gisData->passportPhoto) && $gisData->passportPhoto)
                            <p class="text-xs text-blue-600 mb-1">Current file: <a href="{{ asset('storage/'.$gisData->passportPhoto) }}" target="_blank" class="underline">View Document</a></p>
                        @endif
                        <input type="file" id="passportPhoto" name="passportPhoto" class="block w-full text-sm text-gray-500
                          file:mr-4 file:py-2 file:px-4
                          file:rounded-md file:border-0
                          file:text-sm file:font-semibold
                          file:bg-blue-50 file:text-blue-700
                          hover:file:bg-blue-100">
                    </div>
                    
                    <div class="space-y-2">
                        <label for="nationalId" class="block text-sm font-medium text-gray-700">National ID</label>
                        @if(isset($gisData->nationalId) && $gisData->nationalId)
                            <p class="text-xs text-blue-600 mb-1">Current file: <a href="{{ asset('storage/'.$gisData->nationalId) }}" target="_blank" class="underline">View Document</a></p>
                        @endif
                        <input type="file" id="nationalId" name="nationalId" class="block w-full text-sm text-gray-500
                          file:mr-4 file:py-2 file:px-4
                          file:rounded-md file:border-0
                          file:text-sm file:font-semibold
                          file:bg-blue-50 file:text-blue-700
                          hover:file:bg-blue-100">
                    </div>
                    
                    <div class="space-y-2">
                        <label for="internationalPassport" class="block text-sm font-medium text-gray-700">International Passport</label>
                        @if(isset($gisData->internationalPassport) && $gisData->internationalPassport)
                            <p class="text-xs text-blue-600 mb-1">Current file: <a href="{{ asset('storage/'.$gisData->internationalPassport) }}" target="_blank" class="underline">View Document</a></p>
                        @endif
                        <input type="file" id="internationalPassport" name="internationalPassport" class="block w-full text-sm text-gray-500
                          file:mr-4 file:py-2 file:px-4
                          file:rounded-md file:border-0
                          file:text-sm file:font-semibold
                          file:bg-blue-50 file:text-blue-700
                          hover:file:bg-blue-100">
                    </div>
                    
                    <div class="space-y-2">
                        <label for="businessRegCert" class="block text-sm font-medium text-gray-700">Business Registration Certificate</label>
                        @if(isset($gisData->businessRegCert) && $gisData->businessRegCert)
                            <p class="text-xs text-blue-600 mb-1">Current file: <a href="{{ asset('storage/'.$gisData->businessRegCert) }}" target="_blank" class="underline">View Document</a></p>
                        @endif
                        <input type="file" id="businessRegCert" name="businessRegCert" class="block w-full text-sm text-gray-500
                          file:mr-4 file:py-2 file:px-4
                          file:rounded-md file:border-0
                          file:text-sm file:font-semibold
                          file:bg-blue-50 file:text-blue-700
                          hover:file:bg-blue-100">
                    </div>
                    
                    <div class="space-y-2">
                        <label for="formCO7AndCO4" class="block text-sm font-medium text-gray-700">Form CO7 and CO4</label>
                        @if(isset($gisData->formCO7AndCO4) && $gisData->formCO7AndCO4)
                            <p class="text-xs text-blue-600 mb-1">Current file: <a href="{{ asset('storage/'.$gisData->formCO7AndCO4) }}" target="_blank" class="underline">View Document</a></p>
                        @endif
                        <input type="file" id="formCO7AndCO4" name="formCO7AndCO4" class="block w-full text-sm text-gray-500
                          file:mr-4 file:py-2 file:px-4
                          file:rounded-md file:border-0
                          file:text-sm file:font-semibold
                          file:bg-blue-50 file:text-blue-700
                          hover:file:bg-blue-100">
                    </div>
                    
                    <div class="space-y-2">
                        <label for="certOfIncorporation" class="block text-sm font-medium text-gray-700">Certificate of Incorporation</label>
                        @if(isset($gisData->certOfIncorporation) && $gisData->certOfIncorporation)
                            <p class="text-xs text-blue-600 mb-1">Current file: <a href="{{ asset('storage/'.$gisData->certOfIncorporation) }}" target="_blank" class="underline">View Document</a></p>
                        @endif
                        <input type="file" id="certOfIncorporation" name="certOfIncorporation" class="block w-full text-sm text-gray-500
                          file:mr-4 file:py-2 file:px-4
                          file:rounded-md file:border-0
                          file:text-sm file:font-semibold
                          file:bg-blue-50 file:text-blue-700
                          hover:file:bg-blue-100">
                    </div>
                    
                    <div class="space-y-2">
                        <label for="memorandumAndArticle" class="block text-sm font-medium text-gray-700">Memorandum and Articles</label>
                        @if(isset($gisData->memorandumAndArticle) && $gisData->memorandumAndArticle)
                            <p class="text-xs text-blue-600 mb-1">Current file: <a href="{{ asset('storage/'.$gisData->memorandumAndArticle) }}" target="_blank" class="underline">View Document</a></p>
                        @endif
                        <input type="file" id="memorandumAndArticle" name="memorandumAndArticle" class="block w-full text-sm text-gray-500
                          file:mr-4 file:py-2 file:px-4
                          file:rounded-md file:border-0
                          file:text-sm file:font-semibold
                          file:bg-blue-50 file:text-blue-700
                          hover:file:bg-blue-100">
                    </div>
                    
                    <div class="space-y-2">
                        <label for="letterOfAdmin" class="block text-sm font-medium text-gray-700">Letter of Administration</label>
                        @if(isset($gisData->letterOfAdmin) && $gisData->letterOfAdmin)
                            <p class="text-xs text-blue-600 mb-1">Current file: <a href="{{ asset('storage/'.$gisData->letterOfAdmin) }}" target="_blank" class="underline">View Document</a></p>
                        @endif
                        <input type="file" id="letterOfAdmin" name="letterOfAdmin" class="block w-full text-sm text-gray-500
                          file:mr-4 file:py-2 file:px-4
                          file:rounded-md file:border-0
                          file:text-sm file:font-semibold
                          file:bg-blue-50 file:text-blue-700
                          hover:file:bg-blue-100">
                    </div>
                    
                    <div class="space-y-2">
                        <label for="courtAffidavit" class="block text-sm font-medium text-gray-700">Court Affidavit</label>
                        @if(isset($gisData->courtAffidavit) && $gisData->courtAffidavit)
                            <p class="text-xs text-blue-600 mb-1">Current file: <a href="{{ asset('storage/'.$gisData->courtAffidavit) }}" target="_blank" class="underline">View Document</a></p>
                        @endif
                        <input type="file" id="courtAffidavit" name="courtAffidavit" class="block w-full text-sm text-gray-500
                          file:mr-4 file:py-2 file:px-4
                          file:rounded-md file:border-0
                          file:text-sm file:font-semibold
                          file:bg-blue-50 file:text-blue-700
                          hover:file:bg-blue-100">
                    </div>
                    
                    <div class="space-y-2">
                        <label for="policeReport" class="block text-sm font-medium text-gray-700">Police Report</label>
                        @if(isset($gisData->policeReport) && $gisData->policeReport)
                            <p class="text-xs text-blue-600 mb-1">Current file: <a href="{{ asset('storage/'.$gisData->policeReport) }}" target="_blank" class="underline">View Document</a></p>
                        @endif
                        <input type="file" id="policeReport" name="policeReport" class="block w-full text-sm text-gray-500
                          file:mr-4 file:py-2 file:px-4
                          file:rounded-md file:border-0
                          file:text-sm file:font-semibold
                          file:bg-blue-50 file:text-blue-700
                          hover:file:bg-blue-100">
                    </div>
                    
                    <div class="space-y-2">
                        <label for="newspaperAdvert" class="block text-sm font-medium text-gray-700">Newspaper Advertisement</label>
                        @if(isset($gisData->newspaperAdvert) && $gisData->newspaperAdvert)
                            <p class="text-xs text-blue-600 mb-1">Current file: <a href="{{ asset('storage/'.$gisData->newspaperAdvert) }}" target="_blank" class="underline">View Document</a></p>
                        @endif
                        <input type="file" id="newspaperAdvert" name="newspaperAdvert" class="block w-full text-sm text-gray-500
                          file:mr-4 file:py-2 file:px-4
                          file:rounded-md file:border-0
                          file:text-sm file:font-semibold
                          file:bg-blue-50 file:text-blue-700
                          hover:file:bg-blue-100">
                    </div>
                    
                    <div class="space-y-2">
                        <label for="picture" class="block text-sm font-medium text-gray-700">Picture</label>
                        @if(isset($gisData->picture) && $gisData->picture)
                            <p class="text-xs text-blue-600 mb-1">Current file: <a href="{{ asset('storage/'.$gisData->picture) }}" target="_blank" class="underline">View Document</a></p>
                        @endif
                        <input type="file" id="picture" name="picture" class="block w-full text-sm text-gray-500
                          file:mr-4 file:py-2 file:px-4
                          file:rounded-md file:border-0
                          file:text-sm file:font-semibold
                          file:bg-blue-50 file:text-blue-700
                          hover:file:bg-blue-100">
                    </div>
                    
                    <div class="space-y-2">
                        <label for="SurveyPlan" class="block text-sm font-medium text-gray-700">Survey Plan</label>
                        @if(isset($gisData->SurveyPlan) && $gisData->SurveyPlan)
                            <p class="text-xs text-blue-600 mb-1">Current file: <a href="{{ asset('storage/'.$gisData->SurveyPlan) }}" target="_blank" class="underline">View Document</a></p>
                        @endif
                        <input type="file" id="SurveyPlan" name="SurveyPlan" class="block w-full text-sm text-gray-500
                          file:mr-4 file:py-2 file:px-4
                          file:rounded-md file:border-0
                          file:text-sm file:font-semibold
                          file:bg-blue-50 file:text-blue-700
                          hover:file:bg-blue-100">
                    </div>
                </div>
            </div>
            
            <!-- Submit Button -->
            <div class="flex justify-end space-x-3">
                <a href="{{ route('gis_record.index') }}" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                    Cancel
                </a>
                <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                    Update GIS Data
                </button>
            </div>
        </form>
      </div>
    </div>
    <!-- Footer -->
    @include('admin.footer')
  </div>
</div>
@endsection

@section('scripts')
<script>
    // Show the reason for change field only when change of ownership is Yes
    document.addEventListener('DOMContentLoaded', function() {
        const changeOfOwnershipSelect = document.getElementById('changeOfOwnership');
        const reasonForChangeField = document.getElementById('reasonForChange').parentNode;
        
        function toggleReasonField() {
            if (changeOfOwnershipSelect.value === 'Yes') {
                reasonForChangeField.style.display = 'block';
            } else {
                reasonForChangeField.style.display = 'none';
            }
        }
        
        // Initialize on page load
        toggleReasonField();
        
        // Listen for changes
        changeOfOwnershipSelect.addEventListener('change', toggleReasonField);
    });
</script>
@endsection
