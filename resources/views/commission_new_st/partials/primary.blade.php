        <!-- Primary Tab - NPFN Generation -->
        <div x-show="activeTab === 'primary'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 transform translate-y-4" x-transition:enter-end="opacity-100 transform translate-y-0">
           <div class="bg-white rounded-lg shadow overflow-hidden">
                <form id="commissionPrimaryForm" method="POST" action="javascript:void(0)" enctype="multipart/form-data" onsubmit="return false;">
                    @csrf
                    <div class="p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h2 class="text-xl font-bold text-center text-gray-800">Commission New ST File Number - Primary Application</h2>
                        </div>

                        <div class="mb-6">

                            <p class="text-gray-600">Generate and commission a new primary file number for sectional titling</p>
                        </div>

                        {{-- Allocation tabs: the single control for Allocation Type. The
                             radios further down mirror the choice but are not clickable,
                             so there is only one way to switch. --}}
                        <div class="mb-6 border-b border-gray-200" id="primaryAllocationTabs">
                            <nav class="-mb-px flex gap-3" aria-label="Allocation type">
                                <button type="button"
                                        data-allocation="Direct Allocation"
                                        data-active-class="border-blue-600 bg-blue-50 text-blue-700"
                                        data-idle-class="border-transparent text-blue-400 hover:text-blue-600 hover:bg-blue-50/60"
                                        onclick="selectPrimaryAllocationTab('Direct Allocation')"
                                        class="primary-allocation-tab flex items-center gap-2 rounded-t-lg border-b-2 px-4 pb-3 pt-2 text-sm font-semibold transition-colors">
                                    <i data-lucide="file-plus" class="w-4 h-4"></i>
                                    <span>Direct Allocation Primary (DAP)</span>
                                </button>
                                <button type="button"
                                        data-allocation="Conversion"
                                        data-active-class="border-indigo-600 bg-indigo-50 text-indigo-700"
                                        data-idle-class="border-transparent text-indigo-400 hover:text-indigo-600 hover:bg-indigo-50/60"
                                        onclick="selectPrimaryAllocationTab('Conversion')"
                                        class="primary-allocation-tab flex items-center gap-2 rounded-t-lg border-b-2 px-4 pb-3 pt-2 text-sm font-semibold transition-colors">
                                    <i data-lucide="refresh-cw" class="w-4 h-4"></i>
                                    <span>Conversion Primary (CON-P)</span>
                                </button>
                            </nav>
                        </div>

                        {{-- Conversion sub-type — sits directly under the CON-P tab it
                             belongs to: commission a brand-new CON land file, or hang the
                             ST primary off a conversion that already exists. --}}
                        <div id="conversionModeBlock" class="hidden mb-6 bg-indigo-50/60 border border-indigo-200 rounded-xl p-4">
                            <label class="block text-sm font-medium text-gray-700 mb-3">
                                <i data-lucide="git-branch" class="w-4 h-4 inline mr-1"></i>
                                Conversion Type <span class="text-red-500">*</span>
                            </label>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <label class="flex items-start p-3 bg-white rounded-lg border-2 border-indigo-200 cursor-pointer hover:border-indigo-400 transition-colors">
                                    <input type="radio" name="conversion_mode" value="new" checked
                                           onchange="handleConversionModeChange(this)"
                                           class="mt-0.5 w-4 h-4 text-indigo-600 border-gray-300 focus:ring-indigo-500">
                                    <span class="ml-2">
                                        <span class="block text-sm font-medium text-gray-900">New CON-P</span>
                                        <span class="block text-xs text-gray-500">Commission a new CON land file as the mother</span>
                                    </span>
                                </label>
                                <label class="flex items-start p-3 bg-white rounded-lg border-2 border-gray-200 cursor-pointer hover:border-indigo-400 transition-colors">
                                    <input type="radio" name="conversion_mode" value="existing"
                                           onchange="handleConversionModeChange(this)"
                                           class="mt-0.5 w-4 h-4 text-indigo-600 border-gray-300 focus:ring-indigo-500">
                                    <span class="ml-2">
                                        <span class="block text-sm font-medium text-gray-900">Existing / Extant Conversion</span>
                                        <span class="block text-xs text-gray-500">Pick a CON, KANGIS or SLTR file already in the registry</span>
                                    </span>
                                </label>
                            </div>
                        </div>

                        {{-- Land Use Selection Section --}}
                        <div class="bg-gradient-to-r from-purple-50 to-pink-50 border border-purple-200 rounded-xl p-6 shadow-sm mb-6">
                            <div class="flex items-center mb-4">
                                <div class="bg-purple-500 p-2 rounded-lg mr-3">
                                    <i data-lucide="map" class="w-5 h-5 text-white"></i>
                                </div>
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-900">Land Use Selection</h3>
                                    <p class="text-sm text-gray-600">Select the land use type for this ST file number</p>
                                </div>
                            </div>
                            
                            <div class="space-y-4">
                                <label class="block text-sm font-medium text-gray-700 mb-3">
                                    Land Use Type <span class="text-red-500">*</span>
                                </label>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3">
                                    {{-- Commercial Option --}}
                                    <label class="relative flex items-center p-3 bg-white rounded-lg border-2 border-blue-400 cursor-pointer hover:border-blue-500 hover:bg-blue-50 transition-all group selected">
                                        <input type="checkbox" name="selectedLandUse" class="sr-only" value="COMMERCIAL" checked onchange="handleLandUseChange(this)">
                                        <div class="flex items-center space-x-3">
                                            <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center group-hover:bg-blue-200 transition-all">
                                                <i data-lucide="building-2" class="w-4 h-4 text-blue-600"></i>
                                            </div>
                                            <div class="flex items-center space-x-2">
                                                <span class="font-medium text-gray-900 text-sm">Commercial</span>
                                                <span class="text-xs text-blue-600 font-semibold bg-blue-100 px-2 py-1 rounded">COM</span>
                                            </div>
                                        </div>
                                        <div class="absolute top-1 right-1 w-4 h-4 bg-blue-600 rounded-full items-center justify-center hidden group-[.selected]:flex">
                                            <i data-lucide="check" class="w-2.5 h-2.5 text-white"></i>
                                        </div>
                                    </label>
                                    
                                    {{-- Residential Option --}}
                                    <label class="relative flex items-center p-3 bg-white rounded-lg border-2 border-gray-200 cursor-pointer hover:border-green-500 hover:bg-green-50 transition-all group">
                                        <input type="checkbox" name="selectedLandUse" class="sr-only" value="RESIDENTIAL" onchange="handleLandUseChange(this)">
                                        <div class="flex items-center space-x-3">
                                            <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center group-hover:bg-green-200 transition-all">
                                                <i data-lucide="home" class="w-4 h-4 text-green-600"></i>
                                            </div>
                                            <div class="flex items-center space-x-2">
                                                <span class="font-medium text-gray-900 text-sm">Residential</span>
                                                <span class="text-xs text-green-600 font-semibold bg-green-100 px-2 py-1 rounded">RES</span>
                                            </div>
                                        </div>
                                        <div class="absolute top-1 right-1 w-4 h-4 bg-green-600 rounded-full items-center justify-center hidden group-[.selected]:flex">
                                            <i data-lucide="check" class="w-2.5 h-2.5 text-white"></i>
                                        </div>
                                    </label>
                                    
                                    {{-- Industrial Option --}}
                                    <label class="relative flex items-center p-3 bg-white rounded-lg border-2 border-gray-200 cursor-pointer hover:border-orange-500 hover:bg-orange-50 transition-all group">
                                        <input type="checkbox" name="selectedLandUse" class="sr-only" value="INDUSTRIAL" onchange="handleLandUseChange(this)">
                                        <div class="flex items-center space-x-3">
                                            <div class="w-8 h-8 bg-orange-100 rounded-full flex items-center justify-center group-hover:bg-orange-200 transition-all">
                                                <i data-lucide="factory" class="w-4 h-4 text-orange-600"></i>
                                            </div>
                                            <div class="flex items-center space-x-2">
                                                <span class="font-medium text-gray-900 text-sm">Industrial</span>
                                                <span class="text-xs text-orange-600 font-semibold bg-orange-100 px-2 py-1 rounded">IND</span>
                                            </div>
                                        </div>
                                        <div class="absolute top-1 right-1 w-4 h-4 bg-orange-600 rounded-full items-center justify-center hidden group-[.selected]:flex">
                                            <i data-lucide="check" class="w-2.5 h-2.5 text-white"></i>
                                        </div>
                                    </label>
                                    
                                    {{-- Mixed Use Option --}}
                                    <label class="relative flex items-center p-3 bg-white rounded-lg border-2 border-gray-200 cursor-pointer hover:border-purple-500 hover:bg-purple-50 transition-all group">
                                        <input type="checkbox" name="selectedLandUse" class="sr-only" value="MIXED" onchange="handleLandUseChange(this)">
                                        <div class="flex items-center space-x-3">
                                            <div class="w-8 h-8 bg-purple-100 rounded-full flex items-center justify-center group-hover:bg-purple-200 transition-all">
                                                <i data-lucide="layers" class="w-4 h-4 text-purple-600"></i>
                                            </div>
                                            <div class="flex items-center space-x-2">
                                                <span class="font-medium text-gray-900 text-sm">Mixed Use</span>
                                                <span class="text-xs text-purple-600 font-semibold bg-purple-100 px-2 py-1 rounded">MIXED</span>
                                            </div>
                                        </div>
                                        <div class="absolute top-1 right-1 w-4 h-4 bg-purple-600 rounded-full items-center justify-center hidden group-[.selected]:flex">
                                            <i data-lucide="check" class="w-2.5 h-2.5 text-white"></i>
                                        </div>
                                    </label>
                                </div>
                            </div>
                            
                            <div class="mt-4 p-3 bg-purple-50 border border-purple-200 rounded-lg">
                                <div class="flex items-start">
                                    <i data-lucide="info" class="w-4 h-4 text-purple-600 mr-2 mt-0.5 flex-shrink-0"></i>
                                    <p class="text-xs text-purple-800">
                                        Select one land use type. The file number format will update to reflect your selection (ST-{CODE}-{YEAR}-{SERIAL}).
                                    </p>
                                </div>
                            </div>
                        </div>

                        {{-- Hidden Fields --}}
                        <input type="hidden" name="land_use" value="COMMERCIAL" id="hiddenLandUse">
                        <input type="hidden" name="np_fileno" value="{{ $npFileNo ?? '' }}">
                        <input type="hidden" name="serial_no" value="{{ $serialNo ?? '' }}">
                        <input type="hidden" name="current_year" value="{{ $currentYear ?? date('Y') }}">
                        <input type="hidden" name="draft_id" id="draftIdInput" value="{{ $draftMeta['draft_id'] ?? '' }}">
                        <input type="hidden" name="draft_version" id="draftVersionInput" value="{{ $draftMeta['version'] ?? 1 }}">
                        <input type="hidden" name="draft_last_completed_step" id="draftStepInput" value="{{ $draftMeta['last_completed_step'] ?? 1 }}">
                        <input type="hidden" name="tracking_id" id="primary-tracking-id" value="{{ $trackingId ?? '' }}">
                        
                        <div class="mb-6">
                             
                            <hr class="my-4">
                          

                            {{-- File Numbers Section --}}
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 mb-6 px-4">
                                {{-- New Primary FileNo (NPFN) Card --}}
                                <div class="bg-gradient-to-br from-blue-50 via-indigo-50 to-blue-100 border border-blue-200 rounded-xl p-10 shadow-lg min-h-[450px] w-full max-w-none">
                                    <div class="flex items-center mb-4">
                                        <div class="bg-blue-500 p-3 rounded-full mr-4 shadow-md">
                                            <i data-lucide="file-text" class="w-6 h-6 text-white"></i>
                                        </div>
                                        <div class="flex-1">
                                            <h3 class="text-lg font-semibold text-gray-900">New Primary FileNo</h3>
                                            <p class="text-sm text-gray-600">Auto-generated identifier</p>
                                        </div>
                                        <div class="bg-green-500 px-3 py-1 rounded-full shadow-sm">
                                            <span class="text-white text-xs font-medium">AUTO</span>
                                        </div>
                                    </div>
                                    
                                    <div class="bg-white rounded-lg border border-blue-100 p-6 shadow-inner">
                                                                            <label class="block text-sm font-medium text-gray-700 mb-3">Generated FileNo (NPFN)</label>
                                                                            <div class="mb-4">
                                                                                <div class="w-full px-4 py-2 bg-gray-100 rounded-lg">
                                                                                    <div class="text-gray-700 font-mono text-sm font-bold">
                                                                                        <div class="flex items-center">
                                                                                            <i data-lucide="file-search" class="h-4 w-4 mr-1 flex-shrink-0"></i>
                                                                                            <span class="whitespace-nowrap">Tracking ID: <span id="primary-tracking-display" class="text-red-600 font-bold">{{ 'Awaiting selection' }}</span></span>
                                                                                        </div>        </div>
                                                                                </div>
                                                                            </div>

                                                                            <div class="relative">
                                                                                <input type="text" 
                                                                                       name="np_fileno"
                                                                                       id="np-fileno-display"
                                                                                       class="w-full px-4 py-4 bg-blue-50 border-2 border-blue-200 rounded-lg text-blue-900 font-mono text-xl font-bold cursor-not-allowed transition-all" 
                                                                                       value="{{ $npFileNo ?? 'ST-COM-'.date('Y').'-01' }}" 
                                                                                       readonly
                                                                                       title="New Primary FileNo - Auto Generated">
                                                                                <div class="absolute inset-y-0 right-0 flex items-center pr-4">
                                                                                    <i data-lucide="lock" class="w-6 h-6 text-blue-500"></i>
                                                                                </div>
                                                                            </div>
                                                                            
                                                                            {{-- Components Display --}}
                                                                            <div class="mt-6 space-y-3">
                                                                                <div class="flex justify-between items-center text-sm bg-gray-50 px-3 py-2 rounded">
                                                                                    <span class="text-gray-600">Prefix:</span>
                                                                                    <span class="font-semibold text-blue-700">ST</span>
                                                                                </div>
                                                                                <div class="flex justify-between items-center text-sm bg-gray-50 px-3 py-2 rounded">
                                                                                    <span class="text-gray-600">Land Use:</span>
                                                                                    <span class="font-semibold text-blue-700 land-use-code-display">
                                                                                        COM
                                                                                    </span>
                                                                                </div>
                                                                                <div class="flex justify-between items-center text-sm bg-gray-50 px-3 py-2 rounded">
                                                                                    <span class="text-gray-600">Year:</span>
                                                                                    <span class="font-semibold text-blue-700">{{ $currentYear ?? date('Y') }}</span>
                                                                                </div>
                                                                                <div class="flex justify-between items-center text-sm bg-gray-50 px-3 py-2 rounded">
                                                                                    <span class="text-gray-600">Serial:</span>
                                                                                    <span id="serial-number-display" class="font-semibold text-blue-700">{{ $serialNo ?? '1' }}</span>
                                                                                </div>
                                                                            </div>
                                                                            
                                                                            <div class="mt-4 p-3 bg-blue-50 rounded-lg border border-blue-200">
                                                                                <div class="flex items-start">
                                                                                    <i data-lucide="info" class="w-4 h-4 text-blue-600 mr-2 mt-0.5 flex-shrink-0"></i>
                                                                                    <p class="text-xs text-blue-800">
                                                                                        Primary identifier for this application and all unit applications.
                                                                                    </p>
                                                                                </div>
                                                                            </div>
                                                                        </div>        </div>

                                {{-- File Number Information Card --}}
                                <div class="bg-gradient-to-br from-green-50 via-emerald-50 to-green-100 border border-green-200 rounded-xl p-10 shadow-lg min-h-[450px]">
                                    <div class="flex items-center mb-4">
                                        <div class="bg-green-500 p-3 rounded-full mr-4 shadow-md">
                                            <i data-lucide="search" class="w-6 h-6 text-white"></i>
                                        </div>
                                        <div class="flex-1">
                                            <h3 class="text-lg font-semibold text-gray-900">File Number Information</h3>
                                            <p class="text-sm text-gray-600">Select existing file number</p>
                                        </div>
                                        <div class="bg-orange-500 px-3 py-1 rounded-full shadow-sm">
                                            <span class="text-white text-xs font-medium">SELECT</span>
                                        </div>
                                    </div>
                                    
                                    <div class="bg-white rounded-lg border border-green-100 p-6 shadow-inner">
                                        {{-- Allocation Type comes first: it decides whether the rest of
                                             this card asks for an existing file or shows the CON number
                                             that will be commissioned. --}}
                                        <div class="mb-6 pb-6 border-b border-green-200">
                                            <div class="flex items-center mb-4">
                                                <div class="bg-orange-500 p-2 rounded-lg mr-2">
                                                    <i data-lucide="clipboard-list" class="w-4 h-4 text-white"></i>
                                                </div>
                                                <div>
                                                    <h4 class="text-sm font-semibold text-gray-900">Allocation Type <span class="text-red-500">*</span></h4>
                                                    <p class="text-xs text-gray-600">Set by the DAP / CON-P tab at the top of this form</p>
                                                </div>
                                            </div>

                                            {{-- Mirrors the tab; disabled so the tab stays the only control.
                                                 A disabled radio still answers :checked, which is how the
                                                 payload and the preview read the selection. --}}
                                            <div class="flex items-center space-x-6 bg-gray-50 rounded-lg p-3 border border-gray-200">
                                                {{-- Direct Allocation Option --}}
                                                <label class="flex items-center cursor-not-allowed">
                                                    <input type="radio"
                                                           name="application_type"
                                                           value="Direct Allocation"
                                                           checked
                                                           disabled
                                                           class="w-4 h-4 text-green-600 border-gray-300 focus:ring-green-500">
                                                    <span class="ml-2 text-sm font-medium text-gray-700">Direct Allocation</span>
                                                </label>

                                                {{-- Conversion Option --}}
                                                <label class="flex items-center cursor-not-allowed">
                                                    <input type="radio"
                                                           name="application_type"
                                                           value="Conversion"
                                                           disabled
                                                           class="w-4 h-4 text-green-600 border-gray-300 focus:ring-green-500">
                                                    <span class="ml-2 text-sm font-medium text-gray-700">ST Conversion</span>
                                                </label>
                                            </div>

                                            <div class="mt-3 p-3 bg-yellow-50 rounded-lg border border-yellow-200">
                                                <div class="flex items-start">
                                                    <i data-lucide="info" class="w-4 h-4 text-yellow-700 mr-2 mt-0.5 flex-shrink-0"></i>
                                                    <p class="text-xs text-yellow-800">
                                                        <strong>Direct Allocation:</strong> New application linked to an existing file number.
                                                        <strong>ST Conversion:</strong> Raised on a CON land file — newly commissioned or extant — which becomes the mother file.
                                                        Either way the ST number is ST-{CODE}-{YEAR}-{SERIAL} (e.g. ST-COM-{{ date('Y') }}-18).
                                                    </p>
                                                </div>
                                            </div>
                                        </div>

                                        {{-- Direct Allocation: link to a file that already exists in the registry. --}}
                                        <div id="appliedFileNumberBlock">
                                            <div class="flex items-center justify-between mb-4">
                                                <label class="block text-sm font-medium text-gray-700">
                                                    <i data-lucide="hash" class="w-4 h-4 inline mr-1"></i>
                                                    <span id="appliedFileNumberLabel">Applied File Number</span>
                                                </label>
                                            </div>

                                            <div class="relative">
                                                <select id="applied-file-number"
                                                        name="applied_file_number"
                                                        class="w-full bg-gray-50 border-2 border-green-200 rounded-lg text-gray-900 font-mono text-lg select2-file-number"
                                                        style="width:100%">
                                                    <option value="">Search and select file number...</option>
                                                </select>
                                            </div>

                                            <div class="mt-4 p-3 bg-green-50 rounded-lg border border-green-200">
                                                <div class="flex items-start">
                                                    <i data-lucide="info" class="w-4 h-4 text-green-600 mr-2 mt-0.5 flex-shrink-0"></i>
                                                    <p class="text-xs text-green-800" id="appliedFileNumberHint">
                                                        Link this application to an existing file number from the registry.
                                                    </p>
                                                </div>
                                            </div>
                                        </div>

                                        {{-- New CON-P: the CON land file does not exist yet — it is
                                             commissioned here, as the mother file of the ST primary. --}}
                                        <div id="conversionFileNumberBlock" class="hidden">
                                            <div class="flex items-center justify-between mb-4">
                                                <label class="block text-sm font-medium text-gray-700">
                                                    <i data-lucide="refresh-cw" class="w-4 h-4 inline mr-1"></i>
                                                    Conversion File Number (Mother File)
                                                </label>
                                                <span class="bg-green-500 px-2 py-0.5 rounded-full text-white text-[10px] font-medium">AUTO</span>
                                            </div>

                                            <div class="relative">
                                                <input type="text"
                                                       id="conversion-fileno-display"
                                                       class="w-full px-4 py-4 bg-green-50 border-2 border-green-200 rounded-lg text-green-900 font-mono text-xl font-bold cursor-not-allowed"
                                                       value=""
                                                       readonly
                                                       title="Conversion File Number - Auto Generated">
                                                <div class="absolute inset-y-0 right-0 flex items-center pr-4">
                                                    <i data-lucide="lock" class="w-6 h-6 text-green-500"></i>
                                                </div>
                                            </div>

                                            <div class="mt-4 p-3 bg-green-50 rounded-lg border border-green-200">
                                                <div class="flex items-start">
                                                    <i data-lucide="info" class="w-4 h-4 text-green-600 mr-2 mt-0.5 flex-shrink-0"></i>
                                                    <p class="text-xs text-green-800">
                                                        Commissioned together with the ST file number: this CON file becomes the mother file, and the ST number on the left is created under it.
                                                    </p>
                                                </div>
                                            </div>
                                        </div>

                                    </div>
                                </div>
                            </div>
                        </div>
                          
                            {{-- Primary Tab Applicant Information --}}
                            @include('commission_new_st.partials.primary-applicant', ['titles' => $titles ?? []])

                            {{-- Location Details Section --}}
                            <div class="bg-gray-50 border border-gray-200 rounded-xl p-6 mb-6">
                                <div class="flex items-center justify-between mb-4">
                                    <div class="flex items-center">
                                        <div class="bg-teal-500 p-2 rounded-lg mr-3">
                                            <i data-lucide="map-pin" class="w-5 h-5 text-white"></i>
                                        </div>
                                        <div>
                                            <h3 class="text-lg font-semibold text-gray-900">Location Details</h3>
                                            <p id="locationDetailsHint" class="text-sm text-gray-600">Backfilled automatically from the selected existing file number (read-only)</p>
                                        </div>
                                    </div>
                                    <button type="button" id="locationDetailsEditBtn" onclick="toggleLocationDetailsEdit()" class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-teal-700 bg-teal-50 border border-teal-200 rounded-md hover:bg-teal-100">
                                        <i data-lucide="pencil" class="w-4 h-4 mr-1"></i>
                                        Edit
                                    </button>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                                    <div>
                                        <label class="block text-sm mb-1">House No.</label>
                                        <input type="text" id="propertyHouseNo" name="property_house_no" class="location-detail-field w-full p-2 border border-gray-300 rounded-md bg-gray-100 text-gray-500 cursor-not-allowed" placeholder="ENTER HOUSE NUMBER" style="text-transform:uppercase" oninput="this.value = this.value.toUpperCase(); updatePropertyAddressDisplay();" disabled>
                                    </div>
                                    <div>
                                        <label class="block text-sm mb-1">Plot No.</label>
                                        <input type="text" id="propertyPlotNo" name="property_plot_no" class="location-detail-field w-full p-2 border border-gray-300 rounded-md bg-gray-100 text-gray-500 cursor-not-allowed" placeholder="ENTER PLOT NUMBER" style="text-transform:uppercase" oninput="this.value = this.value.toUpperCase(); updatePropertyAddressDisplay();" disabled>
                                    </div>
                                    <div class="reference-select-wrap">
                                        <label class="block text-sm mb-1">Street Name</label>
                                        <select id="propertyStreetName" name="property_street_name" data-reference-source="streets" class="location-detail-field w-full p-2 border border-gray-300 rounded-md bg-gray-100 text-gray-500 cursor-not-allowed" onchange="updatePropertyAddressDisplay();" disabled>
                                            <option value="">Search street name...</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                                    <div class="reference-select-wrap">
                                        <label class="block text-sm mb-1">District</label>
                                        <select id="propertyDistrict" name="property_district" data-reference-source="districts" class="location-detail-field w-full p-2 border border-gray-300 rounded-md bg-gray-100 text-gray-500 cursor-not-allowed" onchange="updatePropertyAddressDisplay();" disabled>
                                            <option value="">Search district...</option>
                                        </select>
                                    </div>
                                    @php
                                        // Rendered server-side (and defaulted to Kano) so the dropdowns work
                                        // on first paint instead of waiting on the states-lga.js fetch.
                                        $stStatesList = json_decode(@file_get_contents(public_path('js/primaryform/nigerian_states.json')) ?: '[]', true) ?: [];
                                        $stDefaultState = 'Kano';
                                        $stDefaultLgas = collect($stStatesList)->firstWhere('name', $stDefaultState)['local_governments'] ?? [];
                                    @endphp
                                    <div>
                                        <label class="block text-sm mb-1">State</label>
                                        <select id="propertyState" name="property_state" class="location-detail-field w-full p-2 border border-gray-300 rounded-md bg-gray-100 text-gray-500 cursor-not-allowed" onchange="selectPropertyLGA(this); updatePropertyAddressDisplay();" disabled>
                                            <option value="">Select State</option>
                                            @foreach($stStatesList as $stState)
                                                <option value="{{ $stState['name'] }}" @selected($stState['name'] === $stDefaultState)>{{ $stState['name'] }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-sm mb-1">LGA</label>
                                        <select id="propertyLga" name="property_lga" class="location-detail-field w-full p-2 border border-gray-300 rounded-md bg-gray-100 text-gray-500 cursor-not-allowed" onchange="updatePropertyAddressDisplay();" disabled>
                                            <option value="">Select LGA</option>
                                            @foreach($stDefaultLgas as $stLga)
                                                <option value="{{ $stLga }}">{{ $stLga }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-sm mb-1">Property Address:</label>
                                    <div id="propertyAddressPreview" class="p-2 bg-gray-100 border border-gray-300 rounded-md min-h-[40px]">
                                        <span id="fullPropertyAddress" style="display: block; padding: 4px; text-transform: uppercase;"></span>
                                    </div>
                                    <input type="hidden" name="property_address" id="propertyAddressDisplay">
                                </div>

                                {{-- Property Location Map --}}
                                <div class="mt-6 pt-6 border-t border-gray-200">
                                    <div class="flex items-center justify-between gap-4 mb-3">
                                        <div class="flex items-center gap-2 min-w-0">
                                            <i data-lucide="map" class="h-4 w-4 text-blue-600 flex-shrink-0"></i>
                                            <h4 class="text-sm font-bold uppercase tracking-wide text-gray-700 whitespace-nowrap">
                                                Property Location Map
                                            </h4>
                                            <span id="propertyMapCoordSource" class="text-xs font-medium text-gray-400 whitespace-nowrap ml-1"></span>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <span id="propertyMapDragHint" class="hidden items-center gap-1.5 text-xs font-medium text-blue-700 bg-blue-50 border border-blue-100 rounded-full px-3 py-1">
                                                <i data-lucide="move" class="h-3.5 w-3.5"></i>
                                                Drag the pin or click the map to fine-tune
                                            </span>
                                            <button type="button" id="propertyMapPinBtn" onclick="geocodePropertyLocation()"
                                                    class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-blue-700 bg-blue-50 border border-blue-200 rounded-md hover:bg-blue-100">
                                                <i data-lucide="map-pin" class="w-4 h-4 mr-1"></i>
                                                Pin on Map
                                            </button>
                                        </div>
                                    </div>

                                    {{-- Map shown once coordinates are known --}}
                                    <div id="propertyMapWrapper" class="hidden rounded-md overflow-hidden border border-gray-200 shadow-sm">
                                        <div id="propertyMapCanvas" style="height: 380px; width: 100%;"></div>
                                        <div class="text-sm text-gray-600 px-3 py-2 bg-gray-50 flex flex-wrap gap-x-6 gap-y-1">
                                            <span>Latitude: <strong id="propertyLatDisplay">—</strong></span>
                                            <span>Longitude: <strong id="propertyLngDisplay">—</strong></span>
                                        </div>
                                    </div>

                                    {{-- Empty state before a pin exists --}}
                                    <div id="propertyMapEmpty"
                                         class="flex flex-col items-center justify-center gap-2 rounded-md border border-dashed border-gray-300 bg-gray-50 text-gray-400"
                                         style="height: 380px;">
                                        <i data-lucide="map-pin" class="h-8 w-8"></i>
                                        <p class="text-sm">No location pinned yet.</p>
                                        <p class="text-xs">Select an existing file number to backfill its coordinates, or click <strong>Pin on Map</strong>.</p>
                                    </div>

                                    <input type="hidden" name="latitude" id="propertyLatitude">
                                    <input type="hidden" name="longitude" id="propertyLongitude">
                                </div>
                            </div>

                        {{-- Commission Information & Generate ST FileNo Card --}}
                        <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-xl p-8 shadow-sm mt-6">
                            {{-- Grid Layout --}}
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                                
                                {{-- Left Column: Commission Information --}}
                                <div class="space-y-6">
                                    <div class="flex items-center mb-4">
                                        <div class="bg-green-500 p-2 rounded-lg mr-3">
                                            <i data-lucide="user-check" class="w-5 h-5 text-white"></i>
                                        </div>
                                        <div>
                                            <h3 class="text-lg font-semibold text-gray-900">Commission Information</h3>
                                            <p class="text-sm text-gray-600">Process details</p>
                                        </div>
                                    </div>
                                    
                                    <div class="space-y-4">
                                        {{-- Commissioned By --}}
                                        <div class="relative">
                                            <label class="flex items-center text-sm font-medium text-gray-700 mb-2">
                                                <i data-lucide="user" class="w-4 h-4 mr-2 text-green-600"></i>
                                                Commissioned By
                                            </label>
                                            <div class="relative">
                                                <input type="text" 
                                                       class="w-full pl-10 pr-4 py-3 bg-white border border-blue-200 rounded-lg cursor-not-allowed" 
                                                       value="{{ Auth::user()->name ?? 'System User' }}" 
                                                       name="commissioned_by" 
                                                       readonly
                                                       disabled>
                                                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                                    <i data-lucide="lock" class="w-4 h-4 text-blue-400"></i>
                                                </div>
                                            </div>
                                            <p class="text-xs text-gray-500 mt-1">Current user commissioning the file number</p>
                                        </div>
                                        
                                        {{-- Commissioned Date --}}
                                        <div class="relative">
                                            <label class="flex items-center text-sm font-medium text-gray-700 mb-2">
                                                <i data-lucide="calendar-clock" class="w-4 h-4 mr-2 text-blue-600"></i>
                                                Commissioned Date
                                            </label>
                                            <div class="relative">
                                                <input type="date" 
                                                       class="w-full pl-10 pr-4 py-3 bg-white border border-blue-200 rounded-lg cursor-not-allowed" 
                                                       value="{{ date('Y-m-d') }}" 
                                                       name="commissioned_date" 
                                                       readonly
                                                       disabled>
                                                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                                    <i data-lucide="lock" class="w-4 h-4 text-blue-400"></i>
                                                </div>
                                            </div>
                                            <p class="text-xs text-gray-500 mt-1">Date when file number is commissioned</p>
                                        </div>
                                    </div>
                                    
                                    {{-- Info Alert --}}
                                    <div class="p-3 bg-blue-100 border border-blue-300 rounded-lg">
                                        <div class="flex items-start">
                                            <i data-lucide="info" class="w-4 h-4 text-blue-600 mr-2 mt-0.5 flex-shrink-0"></i>
                                            <p class="text-xs text-blue-800">
                                                Commission information is automatically captured when the file number is generated.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                
                                {{-- Right Column: Ready to Generate --}}
                                <div class="flex flex-col justify-center items-center text-center space-y-6">
                                    <!-- Status Indicator -->
                                    <div class="flex items-center justify-center">
                                        <div class="bg-blue-500 p-4 rounded-full shadow-md">
                                            <i data-lucide="shield-check" class="w-8 h-8 text-white"></i>
                                        </div>
                                    </div>
                                    
                                    <!-- Info Text -->
                                    <div class="space-y-3">
                                        <h4 class="text-xl font-semibold text-gray-900">Ready to Generate</h4>
                                        <div class="flex items-center justify-center text-sm text-blue-700 bg-blue-200 px-4 py-2 rounded-full">
                                            <i data-lucide="info" class="w-4 h-4 mr-2"></i>
                                            File number will be reserved upon generation
                                        </div>
                                        <p class="text-sm text-gray-600">This action will create a new ST file number in the system</p>
                                    </div>
                                    
                                    <!-- Action Button -->
                                    <button type="button" 
                                            class="group relative px-10 py-4 bg-gradient-to-r from-blue-600 to-indigo-600 text-white text-lg font-semibold rounded-xl hover:from-blue-700 hover:to-indigo-700 transition-all duration-300 shadow-lg hover:shadow-xl transform hover:-translate-y-0.5 focus:outline-none focus:ring-4 focus:ring-blue-300"
                                            onclick="event.preventDefault(); commissionFileNumber(); return false;"
                                            id="generateSTFileNoBtn">
                                        <div class="flex items-center justify-center">
                                            <div class="bg-white/20 p-2 rounded-lg mr-3 group-hover:bg-white/30 transition-all">
                                                <i data-lucide="file-plus" class="w-5 h-5"></i>
                                            </div>
                                            <span>Generate ST FileNo</span>
                                        </div>
                                        
                                        <!-- Loading State (hidden by default) -->
                                        <div class="absolute inset-0 bg-blue-700 rounded-xl flex items-center justify-center opacity-0 group-disabled:opacity-100 transition-opacity">
                                            <div class="flex items-center">
                                                <div class="animate-spin rounded-full h-5 w-5 border-2 border-white border-t-transparent mr-3"></div>
                                                <span>Generating...</span>
                                            </div>
                                        </div>
                                    </button>
                                    
                                    <!-- Security Notice -->
                                    <div class="p-4 bg-white/60 border border-blue-200 rounded-lg">
                                        <div class="flex items-start text-xs text-gray-600">
                                            <i data-lucide="lock" class="w-4 h-4 mr-2 mt-0.5 text-blue-500 flex-shrink-0"></i>
                                            <div class="text-left">
                                                <p class="font-medium text-gray-700 mb-1">Secure Generation Process</p>
                                                <p>File numbers are generated using atomic reservation to prevent duplicates and ensure data integrity.</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>        </div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    setTimeout(function () {
        if (typeof $ === 'undefined' || !$.fn.select2) {
            console.warn('Select2 or jQuery not loaded for primary file number dropdown.');
            return;
        }

        $('#applied-file-number').select2({
            placeholder: 'Search file number...',
            allowClear: true,
            width: '100%',
            ajax: {
                url: '{{ route("file-indexing.search") }}',
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    // Direct Allocation lists everything except CON- files; an
                    // Existing/Extant conversion lists the files a conversion can
                    // stand on — CON-, KANGIS and SLTR.
                    return {
                        search: params.term,
                        per_page: 20,
                        file_class: (typeof getAppliedFileClass === 'function') ? getAppliedFileClass() : 'direct'
                    };
                },
                processResults: function (data) {
                    return {
                        results: $.map(data.data || [], function (item) {
                            return {
                                id: item.file_number,
                                text: item.file_number + (item.file_title ? ' - ' + item.file_title : ''),
                                file_title: item.file_title || '',
                                tracking_id: item.tracking_id || '',
                                district: item.district || '',
                                lga: item.lga || '',
                                street_name: item.street_name || '',
                                plot_number: item.plot_number || '',
                                location: item.location || '',
                                latitude: item.latitude || '',
                                longitude: item.longitude || '',
                                rc_no: item.rc_no || ''
                            };
                        })
                    };
                },
                cache: true
            },
            minimumInputLength: 2
        }).on('select2:select', function (e) {
            var data = e.params.data;
            var el = document.getElementById('applied-file-number');
            if (el) {
                el.dispatchEvent(new Event('input', { bubbles: true }));
                el.dispatchEvent(new Event('change', { bubbles: true }));
            }
            if (typeof loadPrimaryTrackingId === 'function') {
                loadPrimaryTrackingId(data.id);
            }

            
            // ---- Populate Tracking ID from indexing record ----
            var trackingDisplay = document.getElementById('primary-tracking-display');
            if (trackingDisplay) {
                if (data.tracking_id) {
                    trackingDisplay.textContent = data.tracking_id;
                    trackingDisplay.classList.remove('text-red-600');
                    trackingDisplay.classList.add('text-green-600');
                } else {
                    trackingDisplay.textContent = 'Not found in registry';
                    trackingDisplay.classList.remove('text-green-600');
                    trackingDisplay.classList.add('text-red-600');
                }
            }

            // ---- Auto-fill Applicant Information ----
            // Picks the Applicant Type from the file title (company names and
            // "&"-joined owners are detected) and splits personal names into
            // first / middle / surname. See js/commission_new_st/applicant-autofill.js
            if (typeof applyApplicantBackfill === 'function') {
                applyApplicantBackfill('primary', data);
            }

            // ---- Backfill Location Details from the selected file's indexing record ----
            if (typeof backfillLocationDetails === 'function') {
                backfillLocationDetails(data);
            }

        }).on('select2:unselect', function () {
            var el = document.getElementById('applied-file-number');
            if (el) {
                el.dispatchEvent(new Event('input', { bubbles: true }));
                el.dispatchEvent(new Event('change', { bubbles: true }));
            }
            if (typeof resetPrimaryTracking === 'function') resetPrimaryTracking();

            // Reset tracking display
            var trackingDisplay2 = document.getElementById('primary-tracking-display');
            if (trackingDisplay2) {
                trackingDisplay2.textContent = 'Awaiting selection';
                trackingDisplay2.classList.remove('text-green-600');
                trackingDisplay2.classList.add('text-red-600');
            }

            // Clear applicant fields and fall back to the default type
            ['primary_first_name','primary_middle_name','primary_last_name',
             'primary_corporate_name','primary_rc_number',
             'primary_owner_first_name','primary_owner_middle_name','primary_owner_last_name'].forEach(function(id) {
                var el = document.getElementById(id);
                if (el) el.value = '';
            });
            ['primary_title','primary_owner_title'].forEach(function(id) {
                var el = document.getElementById(id);
                if (el) el.value = '';
            });
            if (typeof setApplicantType === 'function') {
                setApplicantType('primary', 'Individual');
            }

            // Clear location fields
            ['propertyHouseNo','propertyPlotNo'].forEach(function(id) {
                var el = document.getElementById(id);
                if (el) el.value = '';
            });
            if (typeof clearReferenceSelect === 'function') {
                clearReferenceSelect('propertyStreetName', true);
                clearReferenceSelect('propertyDistrict', true);
            }
            var lgaClear = document.getElementById('propertyLga');
            if (lgaClear) lgaClear.value = '';
            var stateClear = document.getElementById('propertyState');
            if (stateClear) stateClear.value = '';
            if (typeof updatePropertyAddressDisplay === 'function') {
                updatePropertyAddressDisplay();
            }

            // Clear the pinned coordinates / map
            if (typeof clearPropertyMap === 'function') clearPropertyMap();
        });
    }, 500);
});

/**
 * Update the Property Address preview from the Location Details fields.
 */
function updatePropertyAddressDisplay() {
    var houseNo = document.getElementById('propertyHouseNo')?.value || '';
    var streetName = document.getElementById('propertyStreetName')?.value || '';
    var district = document.getElementById('propertyDistrict')?.value || '';
    var lga = document.getElementById('propertyLga')?.value || '';
    var state = document.getElementById('propertyState')?.value || '';

    var fullAddress = [houseNo, streetName, district, lga, state]
        .filter(function (part) { return part && part.trim() !== ''; })
        .join(', ');

    var addressHidden = document.getElementById('propertyAddressDisplay');
    if (addressHidden) addressHidden.value = fullAddress;

    var addressSpan = document.getElementById('fullPropertyAddress');
    if (addressSpan) addressSpan.textContent = fullAddress || 'Enter property details above';
}
window.updatePropertyAddressDisplay = updatePropertyAddressDisplay;

/**
 * Toggle the Location Details fields between the default read-only
 * (backfilled) state and an editable state, via the section's Edit button.
 */
function toggleLocationDetailsEdit() {
    var btn = document.getElementById('locationDetailsEditBtn');
    var hint = document.getElementById('locationDetailsHint');
    var fields = document.querySelectorAll('.location-detail-field');
    var willEnable = fields.length ? fields[0].disabled : true;

    fields.forEach(function (field) {
        field.disabled = !willEnable;
        field.classList.toggle('bg-gray-100', !willEnable);
        field.classList.toggle('text-gray-500', !willEnable);
        field.classList.toggle('cursor-not-allowed', !willEnable);
        field.classList.toggle('bg-white', willEnable);
        field.classList.toggle('text-gray-900', willEnable);
        field.classList.toggle('cursor-text', willEnable);

        // Keep the Select2 widget in step with the underlying select
        if (field.tagName === 'SELECT' && typeof setReferenceSelectDisabled === 'function') {
            setReferenceSelectDisabled(field, !willEnable);
        }
    });

    if (btn) {
        btn.innerHTML = willEnable
            ? '<i data-lucide="lock" class="w-4 h-4 mr-1"></i>Done'
            : '<i data-lucide="pencil" class="w-4 h-4 mr-1"></i>Edit';
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }

    if (hint) {
        hint.textContent = willEnable
            ? 'Editing enabled - values will be used as entered'
            : 'Backfilled automatically from the selected existing file number (read-only)';
    }
}
window.toggleLocationDetailsEdit = toggleLocationDetailsEdit;

/**
 * Backfill the Location Details section from the selected file's indexing
 * record (district/lga/street_name/plot_number). The State select
 * has no source value here, so the LGA's parent state is resolved by
 * reverse-lookup against the Nigerian states/LGA reference data before the
 * LGA option list is populated and selected.
 */
function backfillLocationDetails(data) {
    // District / Street are searchable reference dropdowns — set them through
    // the helper so values missing from the reference tables still stick.
    if (typeof setReferenceSelectValue === 'function') {
        setReferenceSelectValue('propertyStreetName', data.street_name || '', true);
        setReferenceSelectValue('propertyDistrict', data.district || '', true);
    }

    var plotEl = document.getElementById('propertyPlotNo');
    if (plotEl) plotEl.value = data.plot_number || '';

    applyLgaBackfill(data.lga || '', 10);
    updatePropertyAddressDisplay();

    // Coordinates already indexed for this file? Pin them straight away.
    var lat = parseFloat(data.latitude);
    var lng = parseFloat(data.longitude);
    if (!isNaN(lat) && !isNaN(lng) && (lat !== 0 || lng !== 0)) {
        setPropertyPin(lat, lng, true, 'Backfilled from file indexing');
    } else {
        clearPropertyMap();
    }
}

/* =======================================================================
 * Property Location Map
 * -----------------------------------------------------------------------
 * Coordinates come from the selected file's indexing record when it has
 * them; otherwise the user geocodes the typed address with "Pin on Map".
 * Either way the pin is draggable so the exact spot can be fine-tuned, and
 * the live values are mirrored into the hidden latitude/longitude inputs
 * that get posted with the commission form.
 * ======================================================================= */
var propertyMap = null;
var propertyMarker = null;

function roundPropertyCoord(v) {
    return Math.round(parseFloat(v) * 1e7) / 1e7;
}

function getPropertyMarkerIcon() {
    var FILL = '#2563eb';
    var svg = '<svg xmlns="http://www.w3.org/2000/svg" width="42" height="50" viewBox="0 0 42 50">' +
              '<path d="M21 0C10 0 1 9 1 20c0 14 20 30 20 30s20-16 20-30C41 9 32 0 21 0z" fill="' + FILL + '" stroke="#ffffff" stroke-width="2"/>' +
              '<circle cx="21" cy="20" r="13" fill="#ffffff"/>' +
              '<path d="M21 12l-8 7h2.4v8.4h11.2V19H29z" fill="' + FILL + '"/></svg>';
    return {
        url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(svg),
        scaledSize: new google.maps.Size(42, 50),
        anchor: new google.maps.Point(21, 50),
        labelOrigin: new google.maps.Point(21, 20)
    };
}

/**
 * Move (or create) the property pin and mirror the coordinates into the
 * hidden form inputs. `recenter` pans the map; `sourceLabel` annotates
 * where the coordinates came from.
 */
function setPropertyPin(lat, lng, recenter, sourceLabel) {
    lat = roundPropertyCoord(lat);
    lng = roundPropertyCoord(lng);

    var latInput = document.getElementById('propertyLatitude');
    var lngInput = document.getElementById('propertyLongitude');
    if (latInput) latInput.value = lat;
    if (lngInput) lngInput.value = lng;

    var latDisplay = document.getElementById('propertyLatDisplay');
    var lngDisplay = document.getElementById('propertyLngDisplay');
    if (latDisplay) latDisplay.textContent = lat;
    if (lngDisplay) lngDisplay.textContent = lng;

    if (typeof sourceLabel === 'string') {
        var srcEl = document.getElementById('propertyMapCoordSource');
        if (srcEl) srcEl.textContent = sourceLabel ? '(' + sourceLabel + ')' : '';
    }

    var wrapper = document.getElementById('propertyMapWrapper');
    var empty = document.getElementById('propertyMapEmpty');
    var hint = document.getElementById('propertyMapDragHint');
    if (wrapper) wrapper.classList.remove('hidden');
    if (empty) empty.classList.add('hidden');
    if (hint) { hint.classList.remove('hidden'); hint.classList.add('inline-flex'); }

    renderPropertyMap(lat, lng, recenter);
}
window.setPropertyPin = setPropertyPin;

// Where the map opens when an address cannot be resolved and the officer has
// to find the plot on the satellite imagery.
var PROPERTY_MAP_KANO_CENTER = { lat: 12.0022, lng: 8.5920 };

/**
 * Create (or recentre) the map itself. Split out from renderPropertyMap so the
 * map can also be opened with no marker for manual pinning.
 */
function ensurePropertyMap(lat, lng, zoom, recenter) {
    var mapEl = document.getElementById('propertyMapCanvas');
    if (!mapEl) return false;

    var pos = { lat: lat, lng: lng };

    if (!propertyMap) {
        propertyMap = new google.maps.Map(mapEl, {
            center: pos,
            zoom: zoom || 17,
            mapTypeId: 'satellite',
            streetViewControl: true,
            fullscreenControl: true
        });
        // Click anywhere on the map to move the pin there.
        propertyMap.addListener('click', function (e) {
            setPropertyPin(e.latLng.lat(), e.latLng.lng(), false, 'Adjusted manually');
        });
    } else {
        if (recenter) propertyMap.panTo(pos);
        // Container may have been display:none while hidden — nudge a resize.
        google.maps.event.trigger(propertyMap, 'resize');
        propertyMap.setCenter(pos);
    }
    return true;
}

/**
 * Open the map with no pin, centred on Kano, so the officer can click the
 * property directly. Used when address lookup finds nothing.
 */
function openPropertyMapForManualPin() {
    if (typeof google === 'undefined' || !google.maps) {
        setTimeout(openPropertyMapForManualPin, 400);
        return;
    }

    var wrapper = document.getElementById('propertyMapWrapper');
    var empty = document.getElementById('propertyMapEmpty');
    var hint = document.getElementById('propertyMapDragHint');
    var srcEl = document.getElementById('propertyMapCoordSource');
    if (wrapper) wrapper.classList.remove('hidden');
    if (empty) empty.classList.add('hidden');
    if (hint) { hint.classList.remove('hidden'); hint.classList.add('inline-flex'); }
    if (srcEl) srcEl.textContent = '(click the property on the map to pin it)';

    ensurePropertyMap(PROPERTY_MAP_KANO_CENTER.lat, PROPERTY_MAP_KANO_CENTER.lng, 13, true);
}
window.openPropertyMapForManualPin = openPropertyMapForManualPin;

function renderPropertyMap(lat, lng, recenter) {
    if (typeof google === 'undefined' || !google.maps) {
        // Map library still loading — retry shortly.
        setTimeout(function () { renderPropertyMap(lat, lng, recenter); }, 400);
        return;
    }

    if (!ensurePropertyMap(lat, lng, 17, recenter)) return;

    var pos = { lat: lat, lng: lng };

    if (!propertyMarker) {
        propertyMarker = new google.maps.Marker({
            position: pos,
            map: propertyMap,
            draggable: true,
            title: 'Drag to adjust the exact property location',
            animation: google.maps.Animation.DROP,
            icon: getPropertyMarkerIcon()
        });
        propertyMarker.addListener('dragend', function (e) {
            setPropertyPin(e.latLng.lat(), e.latLng.lng(), false, 'Adjusted manually');
        });
    } else {
        propertyMarker.setPosition(pos);
        propertyMarker.setMap(propertyMap);
    }
}

/**
 * Reset the map back to its empty state (no coordinates posted).
 */
function clearPropertyMap() {
    var latInput = document.getElementById('propertyLatitude');
    var lngInput = document.getElementById('propertyLongitude');
    if (latInput) latInput.value = '';
    if (lngInput) lngInput.value = '';

    if (propertyMarker) {
        propertyMarker.setMap(null);
        propertyMarker = null;
    }

    var wrapper = document.getElementById('propertyMapWrapper');
    var empty = document.getElementById('propertyMapEmpty');
    var hint = document.getElementById('propertyMapDragHint');
    var srcEl = document.getElementById('propertyMapCoordSource');
    if (wrapper) wrapper.classList.add('hidden');
    if (empty) empty.classList.remove('hidden');
    if (hint) { hint.classList.add('hidden'); hint.classList.remove('inline-flex'); }
    if (srcEl) srcEl.textContent = '';
}
window.clearPropertyMap = clearPropertyMap;

/**
 * Geocode the Location Details fields and drop a pin. Used when the
 * selected file has no stored coordinates, or after the address is edited.
 */
function geocodePropertyLocation() {
    var parts = [
        document.getElementById('propertyHouseNo')?.value || '',
        document.getElementById('propertyPlotNo')?.value ? 'Plot ' + document.getElementById('propertyPlotNo').value : '',
        document.getElementById('propertyStreetName')?.value || '',
        document.getElementById('propertyDistrict')?.value || '',
        document.getElementById('propertyLga')?.value || '',
        document.getElementById('propertyState')?.value || '',
        'Nigeria'
    ].filter(function (p) { return p && p.trim() !== ''; });

    if (parts.length <= 1) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({ icon: 'warning', title: 'No address yet', text: 'Fill in the Location Details above (or select a file number) before pinning on the map.' });
        }
        return;
    }

    if (typeof google === 'undefined' || !google.maps) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({ icon: 'error', title: 'Map not ready', text: 'The map is still loading. Please try again in a moment.' });
        }
        return;
    }

    var address = parts.join(', ');
    new google.maps.Geocoder().geocode({ address: address, region: 'NG' }, function (results, status) {
        if (status === 'OK' && results[0]) {
            var pos = results[0].geometry.location;
            setPropertyPin(pos.lat(), pos.lng(), true, 'Geocoded from address');
            return;
        }

        // Address lookup has poor coverage of Kano plot/street addresses, so a
        // miss opens the map for manual pinning rather than stopping.
        openPropertyMapForManualPin();

        if (typeof Swal === 'undefined') return;

        if (status === 'ZERO_RESULTS') {
            Swal.fire({
                icon: 'info',
                title: 'Address not found — pin it manually',
                text: 'No match for: ' + address + '. The satellite map is now open at Kano; '
                    + 'zoom to the property and click it to drop the pin.'
            });
        } else {
            Swal.fire({
                icon: 'warning',
                title: 'Address lookup unavailable (' + status + ')',
                text: 'The address lookup service could not be reached. '
                    + 'Zoom to the property on the satellite map and click it to drop the pin.'
            });
        }
    });
}
window.geocodePropertyLocation = geocodePropertyLocation;

function applyLgaBackfill(lgaName, attemptsLeft) {
    if (!lgaName) return;

    if (typeof nigerianStatesData === 'undefined' || !nigerianStatesData.length) {
        if (attemptsLeft > 0) {
            setTimeout(function () { applyLgaBackfill(lgaName, attemptsLeft - 1); }, 300);
        }
        return;
    }

    var matchedState = nigerianStatesData.find(function (state) {
        return (state.local_governments || []).some(function (lgaOption) {
            return lgaOption.toLowerCase() === lgaName.toLowerCase();
        });
    });

    if (!matchedState) return;

    var stateSelect = document.getElementById('propertyState');
    var lgaSelect = document.getElementById('propertyLga');
    if (!stateSelect || !lgaSelect) return;

    stateSelect.value = matchedState.name;
    if (typeof selectPropertyLGA === 'function') {
        selectPropertyLGA(stateSelect);
    }

    var matchedOption = Array.from(lgaSelect.options).find(function (opt) {
        return opt.value.toLowerCase() === lgaName.toLowerCase();
    });
    if (matchedOption) {
        lgaSelect.value = matchedOption.value;
    }

    updatePropertyAddressDisplay();
}
</script>

                </form>
            </div>
        </div>


