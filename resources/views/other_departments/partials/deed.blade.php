<div id="detterment-tab" class="tab-content active">
    <form id="deeds-form" method="POST" action="{{ route('primary-applications.storeDeeds') }}">
        @csrf
        <div class="bg-white border border-gray-200 rounded-lg shadow-sm">
            <div class="p-4 border-b">
                <h3 class="text-sm font-medium">Deeds</h3>
                <p class="text-xs text-gray-500">
                    {{ isset($isSecondary) && $isSecondary ? 'Secondary Application' : 'Primary Application' }}</p>
            </div>
            <input type="hidden" name="application_id" value="{{ $application->id }}">
            <input type="hidden" name="fileno" value="{{ $application->fileno ?? $application->primary_fileno ?? $application->mother_fileno ?? '' }}">
            @if(isset($isSecondary) && $isSecondary)
                <input type="hidden" name="sub_application_id" value="{{ $application->id }}">
            @endif

            
            <!-- Nested Tab Navigation -->
            <div class="flex border-b border-gray-200 px-4 pt-3 bg-gray-50">
                @if(isset($isSecondary) && $isSecondary)
                    <button type="button" class="nested-tab-button active px-4 py-3 text-sm font-semibold border-b-2 border-blue-600 text-blue-600 transition-colors" data-nested-tab="assignment-content">
                        <i data-lucide="file-text" class="w-4 h-4 inline mr-2"></i>
                        ST Assignment (Transfer of Title) Registration Particulars
                    </button>
                    <button type="button" class="nested-tab-button px-4 py-3 text-sm font-semibold border-b-2 border-transparent text-gray-600 hover:text-gray-900 hover:border-gray-300 transition-colors" data-nested-tab="cofo-content">
                        <i data-lucide="certificate" class="w-4 h-4 inline mr-2"></i>
                        ST CofO Registration Particular
                    </button>
                @else
                    <button type="button" class="nested-tab-button active px-4 py-3 text-sm font-semibold border-b-2 border-blue-600 text-blue-600 transition-colors" data-nested-tab="cofo-content">
                        <i data-lucide="certificate" class="w-4 h-4 inline mr-2"></i>
                        CofO Registration Particulars
                    </button>
                @endif
            </div>
            
            <!-- Assignment Reg Particulars Tab Content (Secondary Only) -->
            <div id="assignment-content" class="nested-tab-content @if(isset($isSecondary) && $isSecondary) active @else hidden @endif">
                <div class="p-6 space-y-5">
                    @php
                    // Fetch ST FileNo from application object
                    $stFileNo = $application->st_fileno ?? $application->STFileNo ?? $application->fileno ?? null;
                    // Query registered_instruments for ST Assignment (Transfer of Title)
                    $assignmentReg = null;
                    if ($stFileNo) {
                        $assignmentReg = DB::connection('sqlsrv')
                            ->table('registered_instruments')
                            ->select('volume_no', 'page_no', 'serial_no', 'deeds_time', 'deeds_date')
                            ->where('instrument_type', 'ST Assignment (Transfer of Title)')
                            ->where('StFileNo', $stFileNo)
                            ->first();
                    }
                    @endphp
                    
                    <div class="space-y-5">
                        <div class="grid grid-cols-3 gap-4">
                            <div class="space-y-2">
                                <label for="assignment-serial-no" class="text-xs font-semibold text-gray-700 block uppercase tracking-wide">
                                    Serial No
                                </label>
                                <input
                                    id="assignment-serial-no"
                                    name="assignment_serial_no"
                                    type="text"
                                    value="{{ $assignmentReg->serial_no ?? '' }}"
                                    class="w-full px-3 py-2.5 border border-gray-300 bg-gray-50 rounded-lg text-sm text-gray-600 cursor-not-allowed transition-colors deed-field"
                                    disabled
                                >
                            </div>
                            <div class="space-y-2">
                                <label for="assignment-page-no" class="text-xs font-semibold text-gray-700 block uppercase tracking-wide">
                                    Page No
                                </label>
                                <input
                                    id="assignment-page-no"
                                    name="assignment_page_no"
                                    type="text"
                                    value="{{ $assignmentReg->page_no ?? '' }}"
                                    class="w-full px-3 py-2.5 border border-gray-300 bg-gray-50 rounded-lg text-sm text-gray-600 cursor-not-allowed transition-colors deed-field"
                                    disabled
                                >
                            </div>
                            <div class="space-y-2">
                                <label for="assignment-volume-no" class="text-xs font-semibold text-gray-700 block uppercase tracking-wide">
                                    Volume No
                                </label>
                                <input
                                    id="assignment-volume-no"
                                    name="assignment_volume_no"
                                    type="text"
                                    value="{{ $assignmentReg->volume_no ?? '' }}"
                                    class="w-full px-3 py-2.5 border border-gray-300 bg-gray-50 rounded-lg text-sm text-gray-600 cursor-not-allowed transition-colors deed-field"
                                    disabled
                                >
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <label for="assignment-time" class="text-xs font-semibold text-gray-700 block uppercase tracking-wide">
                                    Registration Time
                                </label>
                                <input
                                    id="assignment-time"
                                    name="assignment_time"
                                    type="text"
                                    value="{{ $assignmentReg->deeds_time ?? '' }}"
                                    class="w-full px-3 py-2.5 border border-gray-300 bg-gray-50 rounded-lg text-sm text-gray-600 cursor-not-allowed transition-colors deed-field"
                                    disabled
                                >
                            </div>
                            <div class="space-y-2">
                                <label for="assignment-date" class="text-xs font-semibold text-gray-700 block uppercase tracking-wide">
                                    Registration Date
                                </label>
                                <input
                                    id="assignment-date"
                                    name="assignment_date"
                                    type="date"
                                    value="{{ $assignmentReg->deeds_date ?? '' }}"
                                    class="w-full px-3 py-2.5 border border-gray-300 bg-gray-50 rounded-lg text-sm text-gray-600 cursor-not-allowed transition-colors deed-field"
                                    disabled
                                >
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- CofO Reg Particular Tab Content -->
            <div id="cofo-content" class="nested-tab-content @if(!isset($isSecondary) || !$isSecondary) active @else hidden @endif">
                <div class="p-6 space-y-5">
                    @php
                        // Function to format deeds date from various formats to standard Y-m-d format
                        function formatDeedsDate($dateString) {
                            if (empty($dateString) || strtoupper($dateString) === 'NULL') {
                                return '';
                            }
                            
                            // Remove extra spaces and periods
                            $dateString = trim(str_replace('.', '', $dateString));
                            
                            // If already in Y-m-d format, return as is
                            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateString)) {
                                return $dateString;
                            }
                            
                            // If in d/m/Y format
                            if (preg_match('/^\d{1,2}\/\d{1,2}\/\d{4}$/', $dateString)) {
                                try {
                                    $date = DateTime::createFromFormat('d/m/Y', $dateString);
                                    return $date ? $date->format('Y-m-d') : '';
                                } catch (Exception $e) {
                                    return '';
                                }
                            }
                            
                            // Handle ordinal date formats like "17TH FEBRUARY,2025" or "5TH JUNE 2014"
                            $originalString = $dateString;
                            $dateString = preg_replace('/,\s*/', ' ', $dateString); // Remove commas
                            $dateString = preg_replace('/(\d+)(ST|ND|RD|TH)\s+/', '$1 ', $dateString); // Remove ordinal suffixes
                            $dateString = trim($dateString); // Remove extra spaces
                            
                            // Try to parse various date formats
                            $formats = [
                                'd F Y',     // 17 FEBRUARY 2025
                                'j F Y',     // 5 FEBRUARY 2025 (single digit day)
                                'd M Y',     // 17 FEB 2025
                                'j M Y',     // 5 FEB 2025
                            ];
                            
                            foreach ($formats as $format) {
                                try {
                                    $date = DateTime::createFromFormat($format, $dateString);
                                    if ($date) {
                                        // Additional validation to ensure the date was parsed correctly
                                        $errors = DateTime::getLastErrors();
                                        if ($errors['error_count'] == 0 && $errors['warning_count'] == 0) {
                                            return $date->format('Y-m-d');
                                        }
                                    }
                                } catch (Exception $e) {
                                    continue;
                                }
                            }
                            
                            // If all else fails, try a more flexible approach
                            try {
                                // Try to use strtotime as a fallback
                                $timestamp = strtotime($dateString);
                                if ($timestamp !== false) {
                                    return date('Y-m-d', $timestamp);
                                }
                            } catch (Exception $e) {
                                // Ignore and continue
                            }
                            
                            return '';
                        }
                        
                        // Function to format deeds time from datetime format to h:i A format (with AM/PM)
                        function formatDeedsTime($timeString) {
                            if (empty($timeString) || strtoupper($timeString) === 'NULL') {
                                return '';
                            }
                            
                            try {
                                // Handle datetime format like "1899-12-30 16:08:00.000"
                                if (preg_match('/\d{4}-\d{2}-\d{2}\s+(\d{2}:\d{2}:\d{2})/', $timeString, $matches)) {
                                    $time = DateTime::createFromFormat('H:i:s', $matches[1]);
                                    return $time ? $time->format('h:i A') : ''; // Return h:i A format (12-hour with AM/PM)
                                }
                                
                                // If already in H:i format, convert to 12-hour format
                                if (preg_match('/^\d{2}:\d{2}$/', $timeString)) {
                                    $time = DateTime::createFromFormat('H:i', $timeString);
                                    return $time ? $time->format('h:i A') : '';
                                }
                                
                                return '';
                            } catch (Exception $e) {
                                return '';
                            }
                        }

                        // Initialize variables for both primary and secondary applications
                        $cofoData = null;
                        $serialNo = '';
                        $pageNo = '';
                        $volumeNo = '';
                        $fileNumber = null;
                        $debugInfo = [];
                        $hasCofoData = false;
                        $displaySerialNo = '';
                        $displayPageNo = '';
                        $displayVolumeNo = '';
                        
                        // For secondary applications, get data from deeds or other sources
                        if (isset($isSecondary) && $isSecondary) {
                            // For ST CofO Registration Particular tab, fetch from registered_instruments
                            $stFileNo = $application->st_fileno ?? $application->STFileNo ?? $application->fileno ?? null;
                            $cofoReg = null;
                            if ($stFileNo) {
                                $cofoReg = DB::connection('sqlsrv')
                                    ->table('registered_instruments')
                                    ->select('volume_no', 'page_no', 'serial_no', 'deeds_time', 'deeds_date')
                                    ->where('instrument_type', 'Sectional Titling CofO')
                                    ->where('StFileNo', $stFileNo)
                                    ->first();
                            }
                            $displaySerialNo = $cofoReg->serial_no ?? '';
                            $displayPageNo = $cofoReg->page_no ?? '';
                            $displayVolumeNo = $cofoReg->volume_no ?? '';
                            $deedsTime = $cofoReg->deeds_time ?? '';
                            $deedsDate = $cofoReg->deeds_date ?? '';
                        } else {
                            // For primary applications, query Cofo table for CofO Registration Particulars
                            $debugInfo['application_properties'] = array_keys((array)$application);
                            
                            // Determine the correct file number property
                            if (isset($application->fileno)) {
                                $fileNumber = $application->fileno;
                                $debugInfo['file_source'] = 'application->fileno';
                            } elseif (isset($application->primary_fileno)) {
                                $fileNumber = $application->primary_fileno;
                                $debugInfo['file_source'] = 'application->primary_fileno';
                            } elseif (isset($application->mother_fileno)) {
                                $fileNumber = $application->mother_fileno;
                                $debugInfo['file_source'] = 'application->mother_fileno';
                            } else {
                                $debugInfo['file_source'] = 'No file number property found';
                            }
                            
                            $debugInfo['file_number'] = $fileNumber;
                            
                            if ($fileNumber) {
                                try {
                                    // First, try to find any record with the file number (less restrictive)
                                    $cofoDataAny = DB::connection('sqlsrv')
                                        ->table('Cofo')
                                        ->select('serialNo', 'pageNo', 'volumeNo', 'fileno', 'mlsFNo', 'kangisFileNo', 'NewKANGISFileno')
                                        ->where(function($query) use ($fileNumber) {
                                            $query->where('fileno', $fileNumber)
                                                  ->orWhere('mlsFNo', $fileNumber)
                                                  ->orWhere('kangisFileNo', $fileNumber)
                                                  ->orWhere('NewKANGISFileno', $fileNumber);
                                        })
                                        ->where('application_id', $application->id)
                                        ->first();
                                    
                                    $debugInfo['any_cofo_record'] = $cofoDataAny ? 'Found' : 'Not Found';
                                    
                                    // Now try the restrictive query for complete data
                                    $cofoData = DB::connection('sqlsrv')
                                        ->table('Cofo')
                                        ->select('serialNo', 'pageNo', 'volumeNo', 'fileno', 'transaction_date', 'transaction_time')
                                        ->where(function($query) use ($fileNumber) {
                                            $query->where('fileno', $fileNumber)
                                                  ->orWhere('mlsFNo', $fileNumber)
                                                  ->orWhere('kangisFileNo', $fileNumber)
                                                  ->orWhere('NewKANGISFileno', $fileNumber);
                                        })
                                        ->where('application_id', $application->id)
                                        ->whereNotNull('serialNo')
                                        ->whereNotNull('pageNo')
                                        ->whereNotNull('volumeNo')
                                        ->first();
                                    
                                    $debugInfo['query_executed'] = true;
                                    $debugInfo['cofo_data_found'] = $cofoData ? true : false;
                                    
                                    if ($cofoData) {
                                        // Format the values to remove .0 decimal places
                                        $serialNo = is_numeric($cofoData->serialNo) ? (string)intval($cofoData->serialNo) : $cofoData->serialNo;
                                        $pageNo = is_numeric($cofoData->pageNo) ? (string)intval($cofoData->pageNo) : $cofoData->pageNo;
                                        $volumeNo = is_numeric($cofoData->volumeNo) ? (string)intval($cofoData->volumeNo) : $cofoData->volumeNo;
                                        $debugInfo['cofo_data_loaded'] = true;
                                    }
                                } catch (Exception $e) {
                                    $debugInfo['query_error'] = $e->getMessage();
                                }
                            } else {
                                $debugInfo['query_executed'] = false;
                                $debugInfo['query_error'] = 'No file number available';
                            }
                            
                            // Check if we have CofO data
                            $hasCofoData = $cofoData && !empty($serialNo) && !empty($pageNo) && !empty($volumeNo);
                            
                            // Set display values
                            $displaySerialNo = $serialNo;
                            $displayPageNo = $pageNo;
                            $displayVolumeNo = $volumeNo;
                            
                            // Set deeds time and date from Cofo table for primary applications with formatting
                            $deedsTime = '';
                            $deedsDate = '';
                            if ($cofoData) {
                                $deedsTime = formatDeedsTime($cofoData->transaction_time ?? '');
                                $deedsDate = formatDeedsDate($cofoData->transaction_date ?? '');
                            }
                        }
                    @endphp
                        
                                                
                        @if(!isset($isSecondary) || !$isSecondary)
                        @if(!$hasCofoData)
                            <div class="mb-6 p-4 bg-gradient-to-r from-amber-50 to-orange-50 border-l-4 border-amber-400 rounded-r-lg shadow-sm">
                                <div class="flex items-start">
                                    <i data-lucide="alert-circle" class="w-5 h-5 text-amber-600 mr-3 mt-0.5 flex-shrink-0"></i>
                                    <div class="flex-1">
                                        <h4 class="font-semibold text-amber-900 mb-1">CofO Registration Particulars Not Found</h4>
                                        <p class="text-sm text-amber-800 mb-3">No CofO Registration Particulars have been recorded for this primary application. Click the button below to add new particulars.</p>
                                        <button type="button" id="enable-cofo-form" class="inline-flex items-center px-4 py-2 bg-amber-600 text-white text-sm font-medium rounded-lg hover:bg-amber-700 transition-all duration-200 shadow-sm hover:shadow-md">
                                            <i data-lucide="plus" class="w-4 h-4 mr-2"></i>
                                            Add CofO Registration Particulars
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @endif

                        @if($hasCofoData && $cofoData)
                            <div class="mb-6 p-4 bg-gradient-to-r from-green-50 to-emerald-50 border-l-4 border-green-500 rounded-r-lg shadow-sm">
                                <div class="flex items-center">
                                    <i data-lucide="check-circle-2" class="w-5 h-5 text-green-600 mr-3"></i>
                                    <p class="text-sm font-medium text-green-800">CofO Registration Particulars found and loaded from database</p>
                                </div>
                            </div>
                        @endif
                    @endif
                    
                    <div id="cofo-form-fields" class="space-y-5">
                        <div class="grid grid-cols-3 gap-4">
                            <div class="space-y-2">
                                <label for="serial-no" class="text-xs font-semibold text-gray-700 block uppercase tracking-wide">
                                    Serial No <span class="text-red-500">*</span>
                                </label>
                                <input
                                    id="serial-no"
                                    name="serial_no"
                                    type="text"
                                    value="{{ $displaySerialNo }}"
                                    class="w-full px-3 py-2.5 border rounded-lg text-sm transition-colors deed-field @if((!isset($isSecondary) || !$isSecondary) && $hasCofoData) bg-gray-50 border-gray-300 cursor-not-allowed text-gray-600 @else border-gray-300 @endif"
                                    @if(isset($isSecondary) && $isSecondary) 
                                        disabled 
                                    @elseif(!isset($isSecondary) || !$isSecondary)
                                        @if($hasCofoData) readonly title="This field is read-only as data exists in CofO table. Use Override button to edit." @else disabled @endif
                                    @endif
                                    placeholder="Enter serial number"
                                >
                            </div>
                            <div class="space-y-2">
                                <label for="page-no" class="text-xs font-semibold text-gray-700 block uppercase tracking-wide">
                                    Page No <span class="text-red-500">*</span>
                                </label>
                                <input
                                    id="page-no"
                                    name="page_no"
                                    type="text"
                                    value="{{ $displayPageNo }}"
                                    class="w-full px-3 py-2.5 border rounded-lg text-sm transition-colors deed-field @if((!isset($isSecondary) || !$isSecondary) && $hasCofoData) bg-gray-50 border-gray-300 cursor-not-allowed text-gray-600 @else border-gray-300 @endif"
                                    @if(isset($isSecondary) && $isSecondary) 
                                        disabled 
                                    @elseif(!isset($isSecondary) || !$isSecondary)
                                        @if($hasCofoData) readonly title="This field is read-only as data exists in CofO table. Use Override button to edit." @else disabled @endif
                                    @endif
                                    placeholder="Enter page number"
                                >
                            </div>
                            <div class="space-y-2">
                                <label for="volume-no" class="text-xs font-semibold text-gray-700 block uppercase tracking-wide">
                                    Volume No <span class="text-red-500">*</span>
                                </label>
                                <input
                                    id="volume-no"
                                    name="volume_no"
                                    type="text"
                                    value="{{ $displayVolumeNo }}"
                                    class="w-full px-3 py-2.5 border rounded-lg text-sm transition-colors deed-field @if((!isset($isSecondary) || !$isSecondary) && $hasCofoData) bg-gray-50 border-gray-300 cursor-not-allowed text-gray-600 @else border-gray-300 @endif"
                                    @if(isset($isSecondary) && $isSecondary) 
                                        disabled 
                                    @elseif(!isset($isSecondary) || !$isSecondary)
                                        @if($hasCofoData) readonly title="This field is read-only as data exists in CofO table. Use Override button to edit." @else disabled @endif
                                    @endif
                                    placeholder="Enter volume number"
                                >
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <label for="deeds-time" class="text-xs font-semibold text-gray-700 block uppercase tracking-wide">
                                    Registration Time
                                </label>
                                <input
                                    id="deeds-time"
                                    name="deeds_time"
                                    type="text"
                                    value="{{ $deedsTime }}"
                                    class="w-full px-3 py-2.5 border border-gray-300 bg-gray-50 rounded-lg text-sm text-gray-600 cursor-not-allowed transition-colors deed-field"
                                    disabled
                                    placeholder="Registration time"
                                >
                            </div>
                            <div class="space-y-2">
                                <label for="deeds-date" class="text-xs font-semibold text-gray-700 block uppercase tracking-wide">
                                    Registration Date
                                </label>
                                <input
                                    id="deeds-date"
                                    name="deeds_date"
                                    type="date"
                                    value="{{ $deedsDate }}"
                                    class="w-full px-3 py-2.5 border border-gray-300 bg-gray-50 rounded-lg text-sm text-gray-600 cursor-not-allowed transition-colors deed-field"
                                    disabled
                                >
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <hr class="my-6 border-gray-200">

            <div class="p-4 bg-gray-50 rounded-b-lg">
                <div class="flex gap-3 justify-start">
                    <a
                        href="javascript:void(0);"
                        onclick="window.history.back()"
                        class="inline-flex items-center px-4 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-all duration-200 shadow-sm hover:shadow-md"
                    >
                        <i data-lucide="arrow-left" class="w-4 h-4 mr-2"></i>
                        Back
                    </a>
                      
                    @if(!request()->has('is') || request()->get('is') !== 'secondary')
                        {{-- <button
                            type="button"
                            id="edit-deeds"
                            class="inline-flex items-center px-4 py-2.5 text-sm font-medium text-white bg-blue-600 border border-blue-600 rounded-lg hover:bg-blue-700 transition-all duration-200 shadow-sm hover:shadow-md"
                            onclick="enableDeedsEditing()"
                        >
                            <i data-lucide="edit-3" class="w-4 h-4 mr-2"></i>
                            Edit Deeds
                        </button> --}}
                    @endif
                    <button
                        type="button"
                        id="submit-deeds"
                        onclick="submitDeedsForm()"
                        class="inline-flex items-center px-4 py-2.5 text-sm font-medium text-white bg-green-600 border border-green-600 rounded-lg hover:bg-green-700 transition-all duration-200 shadow-sm hover:shadow-md hidden"
                    >
                        <i data-lucide="save" class="w-4 h-4 mr-2"></i>
                        Save Changes
                    </button>
                </div>
            </div>

            <!-- CSS for nested tabs -->
            <style>
                .nested-tab-content {
                    display: none;
                }
                .nested-tab-content.active {
                    display: block;
                }
                .nested-tab-button {
                    position: relative;
                    cursor: pointer;
                    transition: background-color 0.2s;
                }
                .nested-tab-button.active {
                    color: #1d4ed8;
                    font-weight: 500;
                }
                .nested-tab-button:hover:not(.active) {
                    color: #4b5563;
                }
            </style>

            <!-- JavaScript for nested tabs and enabling add mode for CofO -->
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    // Add nested tab functionality
                    const nestedTabButtons = document.querySelectorAll('.nested-tab-button');
                    const nestedTabContents = document.querySelectorAll('.nested-tab-content');
                    
                    nestedTabButtons.forEach(button => {
                        button.addEventListener('click', function() {
                            const tabId = this.getAttribute('data-nested-tab');
                            // Deactivate all nested tabs
                            nestedTabButtons.forEach(btn => {
                                btn.classList.remove('active');
                                btn.classList.remove('border-blue-500');
                                btn.classList.add('border-transparent');
                            });
                            nestedTabContents.forEach(content => {
                                content.classList.remove('active');
                                content.classList.add('hidden');
                            });
                            // Activate selected nested tab
                            this.classList.add('active', 'border-blue-500');
                            this.classList.remove('border-transparent');
                            const tabContent = document.getElementById(tabId);
                            if (tabContent) {
                                tabContent.classList.add('active');
                                tabContent.classList.remove('hidden');
                            }
                        });
                    });

                    // Enable add mode for CofO Registration Particulars (primary only)
                    const enableBtn = document.getElementById('enable-cofo-form');
                    if (enableBtn) {
                        enableBtn.addEventListener('click', function() {
                            enableCofoForm('add');
                        });
                    }

                    
                    // Function to enable CofO form for adding new data
                    function enableCofoForm(mode) {
                        // Hide notification messages
                        const yellowNotification = document.querySelector('#cofo-content .bg-yellow-50');
                        
                        if (yellowNotification) yellowNotification.style.display = 'none';
                        
                        // Enable all input fields in CofO tab for primary applications
                        document.querySelectorAll('#cofo-content input').forEach(function(input) {
                            input.removeAttribute('disabled');
                            input.removeAttribute('readonly');
                            input.classList.add('bg-white');
                            input.classList.remove('bg-gray-100');
                        });
                        
                        // Show the save button
                        const saveBtn = document.getElementById('submit-deeds');
                        if (saveBtn) {
                            saveBtn.classList.remove('hidden');
                        }
                        
                        // Focus on the first input field
                        const firstInput = document.getElementById('serial-no');
                        if (firstInput) {
                            firstInput.focus();
                        }
                        
                        // Show success message
                        const successMsg = document.createElement('div');
                        successMsg.className = 'mb-4 p-3 bg-green-50 border border-green-200 rounded-lg text-sm text-green-700';
                        successMsg.innerHTML = '<i data-lucide="check-circle" class="w-4 h-4 inline mr-2"></i>Form enabled. You can now add CofO Registration Particulars.';
                        
                        const cofoContent = document.getElementById('cofo-content');
                        const firstChild = cofoContent.querySelector('.p-4');
                        firstChild.insertBefore(successMsg, firstChild.firstChild);
                        
                        // Initialize lucide icons for the new elements
                        if (typeof lucide !== 'undefined') {
                            lucide.createIcons();
                        }
                    }
                });

                // Function to enable deeds editing
                function enableDeedsEditing() {
                    document.querySelectorAll('.deed-field').forEach(function(input) {
                        input.removeAttribute('disabled');
                        input.classList.add('bg-white');
                        input.classList.remove('bg-gray-100');
                    });
                    
                    document.getElementById('edit-deeds').style.display = 'none';
                    document.getElementById('submit-deeds').classList.remove('hidden');
                }

                // Function to submit deeds form
                function submitDeedsForm() {
                    const form = document.getElementById('deeds-form');
                    if (form) {
                        // Basic validation for required fields in primary applications
                        const isSecondary = {{ isset($isSecondary) && $isSecondary ? 'true' : 'false' }};
                        
                        if (!isSecondary) {
                            const serialNo = document.getElementById('serial-no').value.trim();
                            const pageNo = document.getElementById('page-no').value.trim();
                            const volumeNo = document.getElementById('volume-no').value.trim();
                            
                            // Check if fields are readonly (data exists in CofO table)
                            const serialNoField = document.getElementById('serial-no');
                            const isReadonly = serialNoField.hasAttribute('readonly');
                            
                            if (!isReadonly && (!serialNo || !pageNo || !volumeNo)) {
                                Swal.fire({
                                    icon: 'warning',
                                    title: 'Validation Error',
                                    text: 'Please fill in all required fields (Serial No, Page No, Volume No)',
                                    confirmButtonColor: '#3085d6'
                                });
                                return false;
                            }
                        }
                        
                        // Show loading state
                        const submitBtn = document.getElementById('submit-deeds');
                        const originalText = submitBtn.innerHTML;
                        submitBtn.innerHTML = '<i data-lucide="loader-2" class="w-3.5 h-3.5 mr-1.5 animate-spin"></i>Saving...';
                        submitBtn.disabled = true;
                        
                        // Prepare form data
                        const formData = new FormData(form);
                        
                        // Submit via AJAX
                        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || 
                                         document.querySelector('input[name="_token"]')?.value;
                        
                        fetch(form.action, {
                            method: 'POST',
                            body: formData,
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': csrfToken
                            }
                        })
                        .then(response => response.json())
                        .then(data => {
                            // Reset button state
                            submitBtn.innerHTML = originalText;
                            submitBtn.disabled = false;
                            
                            if (data.success) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Success!',
                                    text: data.message,
                                    confirmButtonColor: '#10b981'
                                }).then((result) => {
                                    if (result.isConfirmed) {
                                        // Reload the page to show updated data
                                        window.location.reload();
                                    }
                                });
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error!',
                                    text: data.message || 'An error occurred while saving the data.',
                                    confirmButtonColor: '#ef4444'
                                });
                            }
                        })
                        .catch(error => {
                            // Reset button state
                            submitBtn.innerHTML = originalText;
                            submitBtn.disabled = false;
                            
                            console.error('Error:', error);
                            Swal.fire({
                                icon: 'error',
                                title: 'Network Error!',
                                text: 'An error occurred while submitting the form. Please try again.',
                                confirmButtonColor: '#ef4444'
                            });
                        });
                    }
                }
            </script>
        </div>
    </form>
</div>