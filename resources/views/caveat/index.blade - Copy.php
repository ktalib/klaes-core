@extends('layouts.app')
@section('page-title')
    {{ __('Caveat') }}  
@endsection
@section('content')
<script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('css/global-fileno-modal.css') }}">
    
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .command-list {
            max-height: 200px;
            overflow-y: auto;
        }
        .file-number-popover {
            width: 100%;
            max-width: 400px;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        
        /* Enhanced Tab Styles */

        .tab-trigger.active {
            color: #1f2937 !important;
            background-color: #f9fafb;
        }
        
        .tab-trigger.active .tab-indicator {
            transform: scaleX(1);
        }
        
        .tab-trigger.active div:first-child > div {
            background-color: #3b82f6 !important;
        }
        
        .tab-trigger.active div:first-child > div > i {
            color: white !important;
        }
        
        .tab-trigger[data-tab="place"].active div:first-child > div {
            background-color: #3b82f6 !important;
        }
        
        .tab-trigger[data-tab="lift"].active div:first-child > div {
            background-color: #ea580c !important;
        }
        
        .tab-trigger[data-tab="log"].active div:first-child > div {
            background-color: #059669 !important;
        }
        
        .tab-indicator {
            transition: transform 0.2s ease-in-out;
        }
        
        /* Mobile responsive adjustments */
        @media (max-width: 640px) {
            .tab-trigger {
                margin-bottom: 0.5rem;
            }
            .tab-trigger:last-child {
                margin-bottom: 0;
            }
            .tab-indicator {
                display: none; /* Hide indicator on mobile for cleaner look */
            }
        }
        .status-badge {
            display: inline-flex;
            align-items: center;
            border-radius: 0.375rem;
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
            line-height: 1rem;
            font-weight: 500;
        }
        .status-badge i {
            margin-right: 0.25rem;
            font-size: 0.75rem;
        }
        .badge-active {
            background-color: #DCFCE7;
            color: #166534;
            border: 1px solid #BBF7D0;
        }
        .badge-released {
            background-color: #DBEAFE;
            color: #1E40AF;
            border: 1px solid #BFDBFE;
        }
        .badge-lifted {
            background-color: #EDE9FE;
            color: #5B21B6;
            border: 1px solid #DDD6FE;
        }
        .badge-expired {
            background-color: #FEE2E2;
            color: #991B1B;
            border: 1px solid #FECACA;
        }
        .badge-draft {
            background-color: #F3F4F6;
            color: #374151;
            border: 1px solid #E5E7EB;
        }
        .required-asterisk {
            color: #dc2626;
        }
        .cursor-help {
            cursor: help;
        }
        .cursor-help:hover {
            color: #2563eb;
            transform: scale(1.1);
            transition: all 0.2s ease;
        }
        #registration-number {
            transition: all 0.3s ease;
        }
    </style>
    <!-- Main Content -->
    <div class="flex-1 overflow-auto">
        <!-- Header --> 
        @include('admin.header') 
        <!-- Dashboard Content -->
        <div class="p-6">
   
        
         <div class="container mx-auto px-4 py-8 space-y-6">
        <!-- Page Header -->
        <div class="space-y-2">
            <h1 class="text-3xl font-bold text-gray-900">Encumbrance Sub Module</h1>
            <p class="text-gray-600">Data Capture Form for Caveat Placement and Lifting</p>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-white rounded-lg shadow p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Total Caveats</p>
                        <p class="text-2xl font-bold" id="total-caveats">
                            <i class="fa-solid fa-spinner fa-spin text-gray-400"></i>
                        </p>
                    </div>
                    <i class="fa-regular fa-file-lines text-blue-600 text-2xl"></i>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Active Caveats</p>
                       <p class="text-2xl font-bold" id="active-caveats">
                            <i class="fa-solid fa-spinner fa-spin text-gray-400"></i>
                        </p>
                    </div>
                    <i class="fa-regular fa-circle-check text-green-600 text-2xl"></i>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Released Caveats</p>
                        <p class="text-2xl font-bold" id="released-caveats">
                            <i class="fa-solid fa-spinner fa-spin text-gray-400"></i>
                        </p>
                    </div>
                    <i class="fa-regular fa-file-check text-blue-600 text-2xl"></i>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Draft Caveats</p>
                        <p class="text-2xl font-bold" id="draft-caveats">
                            <i class="fa-solid fa-spinner fa-spin text-gray-400"></i>
                        </p>
                    </div>
                    <i class="fa-regular fa-floppy-disk text-gray-600 text-2xl"></i>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="space-y-6">
            <div class="bg-white border-b border-gray-200 shadow-sm rounded-lg">
                <nav class="flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-8 p-2 sm:p-0" aria-label="Tabs">
                    <button class="tab-trigger group relative min-w-0 flex-1 overflow-hidden bg-white py-4 px-6 text-center text-sm font-medium text-gray-500 hover:text-gray-700 focus:z-10 transition-all duration-200 ease-in-out hover:bg-gray-50 active rounded-lg sm:rounded-none" data-tab="place">
                        <div class="flex flex-col items-center space-y-2">
                            <div class="flex items-center justify-center w-10 h-10 rounded-full bg-blue-600 group-hover:bg-blue-200 transition-colors duration-200">
                                <i class="fa-solid fa-plus text-white text-lg"></i>
                            </div>
                            <span class="text-sm font-medium">Place New Caveat</span>
                            <span class="text-xs text-gray-400 hidden sm:block">Create a new caveat</span>
                        </div>
                        <div class="tab-indicator absolute inset-x-0 bottom-0 h-0.5 bg-blue-600 transform scale-x-1 origin-left transition-transform duration-200"></div>
                    </button>
                    
                    <button class="tab-trigger group relative min-w-0 flex-1 overflow-hidden bg-white py-4 px-6 text-center text-sm font-medium text-gray-500 hover:text-gray-700 focus:z-10 transition-all duration-200 ease-in-out hover:bg-gray-50 rounded-lg sm:rounded-none" data-tab="lift">
                        <div class="flex flex-col items-center space-y-2">
                            <div class="flex items-center justify-center w-10 h-10 rounded-full bg-orange-100 group-hover:bg-orange-200 transition-colors duration-200">
                                <i class="fa-solid fa-arrow-up text-orange-600 text-lg"></i>
                            </div>
                            <span class="text-sm font-medium">Lift Existing Caveat</span>
                            <span class="text-xs text-gray-400 hidden sm:block">Remove a caveat</span>
                        </div>
                        <div class="tab-indicator absolute inset-x-0 bottom-0 h-0.5 bg-orange-600 transform scale-x-0 origin-left transition-transform duration-200"></div>
                    </button>
                    
                    <button class="tab-trigger group relative min-w-0 flex-1 overflow-hidden bg-white py-4 px-6 text-center text-sm font-medium text-gray-500 hover:text-gray-700 focus:z-10 transition-all duration-200 ease-in-out hover:bg-gray-50 rounded-lg sm:rounded-none" data-tab="log">
                        <div class="flex flex-col items-center space-y-2">
                            <div class="flex items-center justify-center w-10 h-10 rounded-full bg-green-100 group-hover:bg-green-200 transition-colors duration-200">
                                <i class="fa-solid fa-list-ul text-green-600 text-lg"></i>
                            </div>
                            <span class="text-sm font-medium">Existing Caveats Log</span>
                            <span class="text-xs text-gray-400 hidden sm:block">View all caveats</span>
                        </div>
                        <div class="tab-indicator absolute inset-x-0 bottom-0 h-0.5 bg-green-600 transform scale-x-0 origin-left transition-transform duration-200"></div>
                    </button>
                </nav>
            </div>

            <!-- Place New Caveat Tab -->
            <div id="tab-place" class="tab-content active">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Left Panel - Search Existing Caveats -->
                    <div class="bg-white rounded-lg shadow">
                        <div class="p-6 border-b">
                            <h2 class="text-lg font-semibold flex items-center gap-2">
                                <i class="fa-solid fa-magnifying-glass text-blue-600"></i>
                                Search Existing Caveats
                            </h2>
                            <p class="text-sm text-gray-600">Search by File Number or Applicant/ Solicitor</p>
                        </div>
                        <div class="p-6 space-y-4">
                            <div class="space-y-2">
                                <label class="text-sm font-medium">Search</label>
                                <div class="relative">
                                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                                    <input type="text" id="search-input" placeholder="File Number, Applicant/Solicitor, Party 2..." class="w-full pl-10 pr-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                </div>
                            </div>

                            <div class="space-y-2">
                                <label class="text-sm font-medium">Filter by Status</label>
                                <select id="status-filter" class="w-full p-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="all">All Status</option>
                                    <option value="active">Active</option>
                                    <option value="released">Released</option>
                                    <option value="lifted">Lifted</option>
                                    <option value="expired">Expired</option>
                                    <option value="draft">Draft</option>
                                </select>
                            </div>

                            <!-- Search Results -->
                            <div class="space-y-2">
                                <label class="text-sm font-medium">Search Results (<span id="search-results-count">5</span>)</label>
                                <div class="space-y-2" id="search-results">
                                    <!-- Results will be populated here -->
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Panel - Data Capture Form -->
                    <div class="bg-white rounded-lg shadow lg:col-span-2">
                        <div class="p-6 border-b">
                            <h2 class="text-lg font-semibold flex items-center gap-2">
                                <i class="fa-solid fa-plus text-blue-600"></i>
                                Place New Caveat Form
                            </h2>
                            <p class="text-sm text-gray-600">Fill in the form below to place a new caveat on a property</p>
                        </div>
                        <div class="p-6">
                            <div class="space-y-6">
                                <!-- Hidden input for file number -->
                                <input type="hidden" id="file_number" name="file_number" value="">
                                
                                <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                                    <div class="flex items-center space-x-2">
                                        <i class="fa-solid fa-plus text-green-600"></i>
                                        <h4 class="font-semibold text-green-800">Place New Caveat Mode</h4>
                                    </div>
                                    <p class="text-sm text-green-700 mt-2">
                                        You are in place new caveat mode. Fill in the form below to place a new caveat on a property.
                                    </p>
                                </div>

                                <!-- Section A: Caveat Information -->
                                <div class="space-y-4">
                                    <h3 class="text-lg font-semibold border-b pb-2">Section A: Caveat Information</h3>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div class="space-y-2">
                                            <label class="text-sm font-medium">
                                                Encumbrance Type <span class="required-asterisk">*</span>
                                                <i id="encumbrance-info-icon" class="fa-solid fa-info-circle text-blue-500 ml-2 cursor-help hidden" title="Click to see description"></i>
                                            </label>
                                            <div class="relative">
                                                <select id="encumbrance-type" class="w-full p-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                                    <option value="">Select encumbrance type</option>
                                                    <option value="Mortgage">Mortgage</option>
                                                    <option value="Lien">Lien</option>
                                                    <option value="Charge">Charge</option>
                                                    <option value="Leasehold Interest">Leasehold Interest</option>
                                                    <option value="Sub-Lease/Sub-Under Lease">Sub-Lease/Sub-Under Lease</option>
                                                    <option value="Easement/Right of Way">Easement/Right of Way</option>
                                                    <option value="Court Order/Restraining Order">Court Order/Restraining Order</option>
                                                    <option value="Pending Litigation (Lis Pendens)">Pending Litigation (Lis Pendens)</option>
                                                    <option value="Power of Attorney">Power of Attorney</option>
                                                    <option value="Caution (General or Specific)">Caution (General or Specific)</option>
                                                    <option value="Dispute/Investigation Tag">Dispute/Investigation Tag</option>
                                                    <option value="Deed of Assignment/Transfer Not Completed">Deed of Assignment/Transfer Not Completed</option>
                                                    <option value="Probate/Letters of Administration">Probate/Letters of Administration</option>
                                                    <option value="Government Acquisition/Reservation">Government Acquisition/Reservation</option>
                                                    <option value="Unpaid Land Charges/Fees">Unpaid Land Charges/Fees</option>
                                                </select>
                                            </div>
                                            <div id="encumbrance-description" class="text-sm text-blue-700 bg-blue-50 p-3 rounded border-l-4 border-blue-400 hidden">
                                                <div class="flex items-start">
                                                    <i class="fa-solid fa-info-circle text-blue-500 mr-2 mt-0.5"></i>
                                                    <span id="encumbrance-description-text"></span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="space-y-2">
                                            <label class="text-sm font-medium">Instrument Type <span class="required-asterisk">*</span></label>
                                            <select id="instrument-type" class="w-full p-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                                <option value="">Select instrument type</option>
                                                @foreach($instrumentTypes as $instrumentType)
                                                    <option value="{{ $instrumentType->InstrumentTypeID }}" title="{{ $instrumentType->Description }}">
                                                        {{ $instrumentType->InstrumentName }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <!-- File Number - Global Selector -->
                                        <div class="space-y-2">
                                            <label class="text-sm font-medium">File Number <span class="required-asterisk">*</span></label>
                                            <div id="file-number-selector" class="bg-white border border-slate-200 rounded-lg p-1 shadow-sm flex items-center gap-2">
                                                <div class="w-8 h-8 rounded-lg bg-slate-100 border border-slate-200 flex items-center justify-center text-slate-600 shrink-0">
                                                    <i class="fa-solid fa-file-lines text-sm"></i>
                                                </div>
                                                <div class="flex-1 flex gap-2 items-center">
                                                    <div class="flex-1">
                                                        <input type="text" id="file_number_display" readonly
                                                            placeholder="NO FILE SELECTED"
                                                            class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-1.5 text-slate-900 font-bold font-mono placeholder:text-slate-400 text-sm outline-none focus:border-slate-400 transition cursor-pointer">
                                                    </div>
                                                    <button type="button" id="select-fileno-btn"
                                                        class="px-3 py-1.5 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 transition flex items-center gap-1.5 shadow-sm text-xs whitespace-nowrap">
                                                        <i class="fa-solid fa-search text-xs"></i>
                                                        Select
                                                    </button>
                                                </div>
                                            </div>
                                            <!-- Selected file info badge -->
                                            <div id="selected-file-info" class="mt-1 p-2 bg-blue-50 border border-blue-200 rounded text-xs text-blue-700 hidden">
                                                <div class="flex items-center justify-between">
                                                    <span id="selected-file-details">
                                                        <i class="fa-solid fa-check-circle mr-1 text-green-600"></i>
                                                        Selected: <span id="selected-file-number" class="font-bold"></span>
                                                    </span>
                                                    <button type="button" id="change-file-number" class="text-blue-600 hover:text-blue-800 underline text-xs">
                                                        Change
                                                    </button>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="space-y-2">
                                            <label class="text-sm font-medium">Location <span class="required-asterisk">*</span></label>
                                            <div class="relative">
                                                <input type="text" id="location" placeholder="Text/Map integration" class="w-full p-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                                <i class="fa-solid fa-location-dot absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                                            </div>
                                            <div id="current-selection-preview" class="mt-2 p-2 bg-slate-50 border border-slate-200 rounded text-xs text-slate-700 hidden">
                                                <i class="fa-solid fa-circle-info mr-1 text-slate-500"></i>
                                                Land Use:
                                                <span id="preview-land-use" class="font-semibold font-mono"></span>
                                            </div>
                                            <div id="file-number-status" class="hidden"></div>
                                        </div>
                                    </div>

                                    <!-- Auto-filled from Lookup - Full Width -->
                                    <div id="autofill-summary" class="mt-6 p-4 bg-indigo-50 border border-indigo-100 rounded-lg hidden">
                                        <p class="text-xs font-semibold text-indigo-700 mb-3">Auto-filled from lookup</p>
                                        <p class="text-xs text-indigo-600 mb-3">Select one record to autofill:</p>
                                        <ul id="autofill-summary-list" class="space-y-2 max-h-64 overflow-y-auto"></ul>
                                    </div>
                                </div>

                                <!-- Section B: Parties Involved -->
                                <div class="space-y-4">
                                    <h3 class="text-lg font-semibold border-b pb-2">Section B: Parties Involved</h3>
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                        <div class="space-y-2">
                                            <label class="text-sm font-medium">Applicant/Solicitor <span class="required-asterisk">*</span></label>
                                            <input type="text" id="petitioner" placeholder="Name of person/organization entering caveat" class="w-full p-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        </div>
                                        <div class="space-y-2">
                                            <label class="text-sm font-medium">Party 1</label>
                                            <input type="text" id="grantor" placeholder="Name of party 1" class="w-full p-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        </div>
                                        <div class="space-y-2">
                                            <label class="text-sm font-medium">Party 2 <span class="required-asterisk">*</span></label>
                                            <input type="text" id="grantee" placeholder="Name of party 2" class="w-full p-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        </div>
                                    </div>
                                </div>

                                <!-- Section C: Registration & Tracking -->
                                <div class="space-y-4">
                                    <h3 class="text-lg font-semibold border-b pb-2">Section C: Registration & Tracking</h3>
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                        <div class="space-y-2">
                                            <label class="text-sm font-medium">Serial No.<span class="required-asterisk">*</span></label>
                                            <input type="text" id="serial-no" placeholder="Enter serial number" class="w-full p-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                           
                                        </div>
                                        <div class="space-y-2">
                                            <label class="text-sm font-medium text-gray-500">Page No. (Auto-filled)</label>
                                            <input type="text" id="page-no" placeholder="Auto-filled from Serial No." class="w-full p-2 border rounded-md bg-gray-100 text-gray-500" readonly oninput="this.value=''" title="Page number is automatically set to match Serial number">
                                            <p class="text-xs text-gray-500">
                                                <i class="fa-solid fa-info-circle mr-1"></i>
                                                Automatically matches Serial No.
                                            </p>
                                        </div>
                                        <div class="space-y-2">
                                            <label class="text-sm font-medium">Volume No.<span class="required-asterisk">*</span></label>
                                            <input type="text" id="volume-no" placeholder="Enter volume number" class="w-full p-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-1 gap-4">
                                        <div class="space-y-2">
                                            <label class="text-sm font-medium">
                                                <i class="fa-solid fa-info-circle text-blue-500 mr-1"></i>
                                                Registration Number
                                            </label>
                                            <div class="text-sm font-mono bg-gray-50 p-3 rounded border border-gray-200 min-h-[40px] flex items-center" id="registration-number" style="color: #6b7280; font-weight: normal;">
                                                Enter Serial No. and Volume No. to generate
                                            </div>
                                            <p class="text-xs text-gray-500">Format: [Serial No]/[Page No]/[Volume No]</p>
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div class="space-y-2">
                                            <label class="text-sm font-medium">Date Placed<span class="required-asterisk">*</span></label>
                                            <input type="datetime-local" id="start-date" class="w-full p-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        </div>
                                        <div class="space-y-2">
                                            <label class="text-sm font-medium">Release Date  </label>
                                            <input type="text" id="release-date" class="w-full p-2 border rounded-md bg-gray-100 text-gray-500" readonly>
                                            <p class="text-xs text-gray-500">Automatically set to 6 months after Date Placed</p>            
                                        </div>
                                    </div>
                                    <script>
                                        document.addEventListener('DOMContentLoaded', function () {
                                            var startDateInput = document.getElementById('start-date');
                                            var releaseDateInput = document.getElementById('release-date');
                                            if (!startDateInput || !releaseDateInput) return;

                                            function addMonths(date, months) {
                                                var d = new Date(date);
                                                var day = d.getDate();
                                                d.setMonth(d.getMonth() + months);

                                                // Handle month overflow (e.g., Feb 31st -> Mar 3rd)
                                                if (d.getDate() < day) {
                                                    d.setDate(0);
                                                }
                                                return d;
                                            }

                                            function updateReleaseDate() {
                                                var val = startDateInput.value;
                                                if (!val) {
                                                    releaseDateInput.value = '';
                                                    return;
                                                }
                                                var start = new Date(val);
                                                if (isNaN(start.getTime())) {
                                                    releaseDateInput.value = '';
                                                    return;
                                                }
                                                var release = addMonths(start, 6);
                                                // Format as yyyy-mm-dd
                                                var yyyy = release.getFullYear();
                                                var mm = String(release.getMonth() + 1).padStart(2, '0');
                                                var dd = String(release.getDate()).padStart(2, '0');
                                                releaseDateInput.value = yyyy + '-' + mm + '-' + dd;
                                            }

                                            startDateInput.addEventListener('change', updateReleaseDate);
                                            startDateInput.addEventListener('blur', updateReleaseDate);

                                            // If value is pre-filled (edit mode), set release date
                                            updateReleaseDate();
                                        });
                                    </script>
                                </div>

                                <!-- Section D: Administrative Details -->
                                <div class="space-y-4">
                                    <h3 class="text-lg font-semibold border-b pb-2">Section D: Administrative Details</h3>
                                    <div class="space-y-4">
                                        <div class="space-y-2">
                                            <label class="text-sm font-medium">Instructions</label>
                                            <textarea id="instructions" placeholder="Enter detailed instructions..." rows="3" class="w-full p-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                                        </div>
                                        <div class="space-y-2">
                                            <label class="text-sm font-medium">Remarks</label>
                                            <textarea id="remarks" placeholder="Enter any additional remarks..." rows="3" class="w-full p-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                                        </div>
                                    </div>
                                </div>

                                <!-- System Metadata (Display Only) -->
                                <div class="space-y-4">
                                    <h3 class="text-lg font-semibold border-b pb-2">System Metadata (Auto-Generated)</h3>
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                        <div class="space-y-2">
                                            <label class="text-sm font-medium">Caveat Number</label>
                                            <input type="text" id="caveat-number" disabled class="w-full p-2 border rounded-md bg-gray-100">
                                        </div>
                                        <div class="space-y-2">
                                            <label class="text-sm font-medium">Placed By</label>
                                            <div >
                                                @auth
                                                    <input type="text" id="created-by-first-name" value="{{ auth()->user()->first_name ?? auth()->user()->last_name ?? '' }}" disabled class="w-full p-2 border rounded-md bg-gray-100" placeholder="First name">
                                                    <input type="hidden" id="created-by-last-name" value="{{ auth()->user()->last_name ?? '' }}" disabled  >
                                                    <input type="hidden" id="created-by-id" value="{{ auth()->user()->id }}">
                                                @else
                                                    <input type="text" value="Guest" disabled class="w-full p-2 border rounded-md bg-gray-100 col-span-2">
                                                @endauth
                                            </div>
                                        </div>
                                        <div class="space-y-2">
                                            <label class="text-sm font-medium">Date Created</label>
                                            <input type="text" id="date-created" disabled class="w-full p-2 border rounded-md bg-gray-100">
                                        </div>
                                    </div>
                                </div>

                                <!-- Form Actions -->
                                <div class="flex flex-wrap gap-3 pt-6 border-t">
                                    <button id="save-draft" class="px-4 py-2 border rounded-md flex items-center bg-gray-300 text-gray-600 cursor-not-allowed" disabled>
                                        <i class="fa-regular fa-floppy-disk mr-2"></i>
                                        Save Record to Database
                                    </button>
                                    <button id="place-caveat" class="px-4 py-2 bg-gray-300 text-gray-600 rounded-md flex items-center cursor-not-allowed" disabled>
                                        <i class="fa-regular fa-paper-plane mr-2"></i>
                                        Place Caveat
                                    </button>
                                    <button id="generate-acknowledgement" class="px-4 py-2 border rounded-md flex items-center">
                                        <i class="fa-solid fa-download mr-2"></i>
                                        Generate Acknowledgement Sheet
                                    </button>
                                    <button id="reset-form" class="px-4 py-2 border rounded-md flex items-center">
                                        <i class="fa-solid fa-rotate-left mr-2"></i>
                                        Cancel / Reset
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Lift Existing Caveat Tab -->
            <div id="tab-lift" class="tab-content">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Left Panel - Active Caveats to Lift -->
                    <div class="bg-white rounded-lg shadow">
                        <div class="p-6 border-b">
                            <h2 class="text-lg font-semibold flex items-center gap-2">
                                <i class="fa-solid fa-list text-green-600"></i>
                                Active Caveats to Lift
                            </h2>
                            <p class="text-sm text-gray-600">Select an active caveat to lift</p>
                        </div>
                        <div class="p-6 space-y-4">
                            <div class="space-y-2">
                                <label class="text-sm font-medium">Search</label>
                                <div class="relative">
                                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                                    <input type="text" id="lift-search-input" placeholder="Search active caveats..." class="w-full pl-10 pr-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                </div>
                            </div>

                            <!-- Search Results -->
                            <div class="space-y-2">
                                <label class="text-sm font-medium">Active Caveats (<span id="active-caveats-count">3</span>)</label>
                                <div class="space-y-2 max-h-96 overflow-y-auto" id="active-caveats-list">
                                    <!-- Active caveats will be populated here -->
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Panel - Lift Caveat Form -->
                    <div class="bg-white rounded-lg shadow lg:col-span-2">
                        <div class="p-6 border-b">
                            <h2 class="text-lg font-semibold flex items-center gap-2">
                                <i class="fa-solid fa-arrow-up text-orange-600"></i>
                                Lift Existing Caveat
                            </h2>
                            <p class="text-sm text-gray-600">Select an active caveat to lift from the left panel</p>
                        </div>
                        <div class="p-6">
                            <div class="space-y-6">
                                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                                    <div class="flex items-center space-x-2">
                                        <i class="fa-solid fa-triangle-exclamation text-yellow-600"></i>
                                        <h4 class="font-semibold text-yellow-800">Lift Caveat Mode</h4>
                                    </div>
                                    <p class="text-sm text-yellow-700 mt-2">
                                        You are in lift caveat mode. Select an existing active caveat from the left panel to lift it. The
                                        release date field will be automatically populated with today's date.
                                    </p>
                                </div>

                                <div id="selected-caveat-info">
                                    <!-- Selected caveat info will be shown here -->
                                </div>

                                <div id="no-caveat-selected" class="text-center py-12">
                                    <i class="fa-solid fa-triangle-exclamation text-gray-400 text-4xl mb-4"></i>
                                    <h3 class="text-lg font-medium text-gray-900 mb-2">No Caveat Selected</h3>
                                    <p class="text-gray-600">
                                        Please select an active caveat from the left panel to proceed with lifting.
                                    </p>
                                </div>

                                <!-- Lifting Details (shown when caveat is selected) -->
                                <div id="lifting-details" class="space-y-4 hidden">
                                    <h3 class="text-lg font-semibold border-b pb-2">Lifting Details</h3>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div class="space-y-2">
                                            <label class="text-sm font-medium">Release Date (Auto-filled)</label>
                                            <input type="date" id="lift-release-date" class="w-full p-2 border rounded-md bg-yellow-50 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                            <p class="text-xs text-yellow-600">Auto-filled with today's date for lifting</p>
                                        </div>
                                    </div>
                                    <div class="space-y-4">
                                        <div class="space-y-2">
                                            <label class="text-sm font-medium">Lifting Instructions</label>
                                            <textarea id="lift-instructions" placeholder="Enter detailed instructions for lifting this caveat..." rows="3" class="w-full p-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                                        </div>
                                        <div class="space-y-2">
                                            <label class="text-sm font-medium">Lifting Remarks</label>
                                            <textarea id="lift-remarks" placeholder="Enter any additional remarks for lifting this caveat..." rows="3" class="w-full p-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                                        </div>
                                    </div>

                                    <!-- Form Actions -->
                                    <div class="flex flex-wrap gap-3 pt-6 border-t">
                                        <!-- <button id="save-lift-draft" class="px-4 py-2 border rounded-md flex items-center">
                                            <i class="fa-regular fa-floppy-disk mr-2"></i>
                                            Save Draft
                                        </button> -->
                                        <button id="lift-caveat" class="px-4 py-2 bg-blue-600 text-white rounded-md flex items-center">
                                            <i class="fa-regular fa-paper-plane mr-2"></i>
                                            Lift Caveat
                                        </button>
                                        <button id="generate-lift-acknowledgement" class="px-4 py-2 border rounded-md flex items-center">
                                            <i class="fa-solid fa-download mr-2"></i>
                                            Generate Acknowledgement Sheet
                                        </button>
                                        <button id="reset-lift-form" class="px-4 py-2 border rounded-md flex items-center">
                                            <i class="fa-solid fa-rotate-left mr-2"></i>
                                            Cancel / Reset
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Existing Caveats Log Tab -->
            <div id="tab-log" class="tab-content">
                <div class="bg-white rounded-lg shadow">
                    <div class="p-6 border-b">
                        <h2 class="text-lg font-semibold flex items-center gap-2">
                            <i class="fa-regular fa-file-lines"></i>
                            Existing Caveats Log
                        </h2>
                        <p class="text-sm text-gray-600">Complete log of all caveats in the system</p>
                    </div>
                    <div class="p-6">
                        <div class="space-y-4">
                            <!-- Search and Filter Controls -->
                            <div class="flex flex-col sm:flex-row gap-4">
                                <div class="flex-1">
                                    <div class="relative">
                                        <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                                        <input type="text" id="log-search-input" placeholder="Search caveats by number, file number, petitioner, grantee..." class="w-full pl-10 pr-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    </div>
                                </div>
                                <div class="w-full sm:w-48">
                                    <select id="log-status-filter" class="w-full p-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        <option value="all">All Status</option>
                                        <option value="active">Active</option>
                                        <option value="released">Released</option>
                                        <option value="lifted">Lifted</option>
                                        <option value="expired">Expired</option>
                                        <option value="draft">Draft</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Results Summary -->
                            <div class="text-sm text-gray-600">
                                Showing <span id="log-results-count">5</span> of <span id="total-caveats-count">5</span> caveats
                            </div>

                            <!-- Caveats Table -->
                            <div class="border rounded-lg overflow-hidden">
                                <table class="w-full">
                                    <thead>
                                        <tr class="bg-gray-100">
                                            <th class="p-3 text-left text-sm font-medium">Caveat Number</th>
                                            <th class="p-3 text-left text-sm font-medium">File Number</th>
                                            <th class="p-3 text-left text-sm font-medium">Applicant/Solicitor</th>
                                            <th class="p-3 text-left text-sm font-medium">Party 2</th>
                                            <th class="p-3 text-left text-sm font-medium">Encumbrance Type</th>
                                            <th class="p-3 text-left text-sm font-medium">Status</th>
                                            <th class="p-3 text-left text-sm font-medium">Start Date</th>
                                            <th class="p-3 text-left text-sm font-medium">Release Date</th>
                                            <th class="p-3 text-left text-sm font-medium">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="caveats-table-body">
                                        <!-- Table rows will be populated here -->
                                    </tbody>
                                </table>
                            </div>

                            <div id="no-caveats-found" class="text-center py-12 hidden">
                                <i class="fa-regular fa-file-lines text-gray-400 text-4xl mb-4"></i>
                                <h3 class="text-lg font-medium text-gray-900 mb-2">No Caveats Found</h3>
                                <p class="text-gray-600">No caveats match your current search criteria.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- View Caveat Modal -->
    <div id="view-caveat-modal" class="fixed inset-0 bg-black bg-opacity-50 items-center justify-center p-4 hidden z-50">
        <div class="bg-white rounded-lg shadow-lg max-w-4xl w-full max-h-[90vh] overflow-y-auto">
            <div class="p-6 border-b">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-xl font-semibold text-gray-900">Caveat Details</h2>
                        <p class="text-sm text-gray-600">View detailed information about this caveat</p>
                    </div>
                    <button id="close-modal-x" class="text-gray-400 hover:text-gray-600 transition-colors">
                        <i class="fa-solid fa-times text-xl"></i>
                    </button>
                </div>
            </div>
            <div class="p-6 space-y-6" id="caveat-details">
                <!-- Caveat details will be populated here -->
            </div>
            <div class="p-6 border-t flex justify-end space-x-3">
                <button id="close-modal" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50 transition-colors">
                    <i class="fa-solid fa-times mr-2"></i>
                    Close
                </button>
                <button id="edit-caveat-btn" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">
                    <i class="fa-solid fa-edit mr-2"></i>
                    Edit Caveat
                </button>
            </div>
        </div>
    </div>

    <!-- Toast Notification -->
    <div id="toast" class="fixed top-4 right-4 p-4 rounded-lg shadow-lg bg-white border border-blue-500 hidden transition-opacity duration-300 z-50">
        <div class="flex items-center">
            <i id="toast-icon" class="mr-3"></i>
            <div>
                <h4 id="toast-title" class="font-semibold"></h4>
                <p id="toast-description" class="text-sm text-gray-600"></p>
            </div>
        </div>
    </div>

    <!-- Global File Number Selector Modal -->
    @include('components.global-fileno-modal')

        </div>
     
        <!-- Footer -->
        @include('admin.footer')

        <!-- JavaScript Files - Load in Order -->
        <!-- 1. Core data and state management -->
        <script src="{{ asset('js/caveat-data.js') }}"></script>
        
        <!-- 2. Rendering functions -->
        <script src="{{ asset('js/caveat-rendering.js') }}"></script>
        
        <!-- 3. File number lookup + autofill -->
        <script src="{{ asset('js/file-number-search.js') }}"></script>

        <!-- 4. Event listeners -->
        <script src="{{ asset('js/caveat-events.js') }}"></script>
        
        <!-- 5. Global File Number Modal -->
        <script src="{{ asset('js/global-fileno-modal.js') }}"></script>
        
        <!-- 6. Main application initialization -->
        @include('caveat.assets.js_dynamic')

        <!-- 7. Global File Number Modal Integration -->
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Initialize the global file number modal
                if (window.GlobalFileNoModal) {
                    window.GlobalFileNoModal.init();
                }

                const fileDisplay = document.getElementById('file_number_display');
                const fileHidden = document.getElementById('file_number');
                const pickBtn = document.getElementById('select-fileno-btn');
                const changeBtn = document.getElementById('change-file-number');
                const selectedInfo = document.getElementById('selected-file-info');
                const selectedFileNumber = document.getElementById('selected-file-number');

                function openFileNoModal() {
                    if (!window.GlobalFileNoModal) {
                        console.error('GlobalFileNoModal not loaded');
                        return;
                    }
                    window.GlobalFileNoModal.open({
                        targetFields: ['#file_number', '#file_number_display'],
                        callback: function(data) {
                            console.log('Caveat file selected:', data);
                            if (data && data.fileNumber) {
                                // Update display
                                if (fileDisplay) fileDisplay.value = data.fileNumber;
                                if (fileHidden) fileHidden.value = data.fileNumber;

                                // Show selected info badge
                                if (selectedInfo) selectedInfo.classList.remove('hidden');
                                if (selectedFileNumber) selectedFileNumber.textContent = data.fileNumber;

                                if (typeof window.handleFileNumberChange === 'function') {
                                    window.handleFileNumberChange();
                                } else if (typeof window.searchFileNumberInTables === 'function') {
                                    window.searchFileNumberInTables(data.fileNumber);
                                }

                                // Auto-fill location if available
                                if (data.record) {
                                    const r = data.record;
                                    const locationInput = document.getElementById('location');
                                    if (locationInput && !locationInput.value && (r.location || r.address)) {
                                        locationInput.value = String(r.location || r.address).toUpperCase();
                                        if (typeof lockAutofilledField === 'function') {
                                            lockAutofilledField(locationInput, { event: 'input' });
                                        }
                                    }
                                    // Auto-fill applicant/solicitor from file indexing title and keep editable
                                    const petitionerInput = document.getElementById('petitioner');
                                    const fileTitle = r.file_title || r.file_name || r.petitioner;
                                    if (petitionerInput && fileTitle) {
                                        petitionerInput.value = String(fileTitle).toUpperCase();
                                        petitionerInput.disabled = false;
                                        petitionerInput.readOnly = false;
                                        petitionerInput.classList.remove('bg-gray-100', 'text-gray-500', 'cursor-not-allowed');
                                    }
                                }
                            }
                        }
                    });
                }

                // Open modal on button click
                if (pickBtn) {
                    pickBtn.addEventListener('click', openFileNoModal);
                }
                // Also open on display input click
                if (fileDisplay) {
                    fileDisplay.addEventListener('click', openFileNoModal);
                }

                // Change file number - reopen modal
                if (changeBtn) {
                    changeBtn.addEventListener('click', function() {
                        // Clear current selection
                        if (fileDisplay) fileDisplay.value = '';
                        if (fileHidden) fileHidden.value = '';
                        if (selectedInfo) selectedInfo.classList.add('hidden');
                        openFileNoModal();
                    });
                }

                // Initialize lucide icons if available
                if (window.lucide) window.lucide.createIcons();
            });
        </script>
        
        <!-- Auto-fill Grantor for certificate instruments -->
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var instrumentSelect = document.getElementById('instrument-type');
                var grantorInput = document.getElementById('grantor');

                if (!instrumentSelect || !grantorInput) return;

                function isCertificateOption(option) {
                    if (!option) return false;
                    var txt = (option.text || option.title || '').toLowerCase();
                    var val = (option.value || '').toLowerCase();
                    return txt.indexOf('certificate') !== -1 || val === 'certificate' || val.indexOf('certificate') !== -1;
                }

                function applyGrantorBehavior() {
                    if (grantorInput.dataset.autofilledLocked === 'true') {
                        return;
                    }
                    var selected = instrumentSelect.options[instrumentSelect.selectedIndex];
                    if (isCertificateOption(selected)) {
                        grantorInput.value = 'KANO STATE GOVERNMENT';
                        grantorInput.setAttribute('disabled', 'disabled');
                        // visually grey out to match disabled appearance
                        grantorInput.classList.add('bg-gray-100', 'text-gray-500');
                    } else {
                        // only clear the grantor if it was auto-set previously
                        if (grantorInput.value.toLowerCase() === 'kano state government') {
                            grantorInput.value = '';
                        }
                        grantorInput.removeAttribute('disabled');
                        grantorInput.classList.remove('bg-gray-100', 'text-gray-500');
                    }
                }

                // run on change and on load (in case the form is populated server-side)
                instrumentSelect.addEventListener('change', applyGrantorBehavior);
                applyGrantorBehavior();
            });
        </script>
    </div>
@endsection
