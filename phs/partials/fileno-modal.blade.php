<!-- Global File Number Modal -->
<div id="phs-fileno-modal"
    class="fixed inset-0 bg-black bg-opacity-60 z-[2000000] hidden items-center justify-center p-4"
    data-modal="phs-fileno" onclick="if(event.target === this) PhsFileNoModal.close()">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-5xl max-h-[90vh] flex flex-col overflow-hidden"
        onclick="event.stopPropagation()">

        <!-- Modal Header -->
        <div id="modal-header-container" class="relative px-6 py-4 bg-gradient-to-br from-blue-500 via-blue-600 to-indigo-700 text-white shrink-0 transition-all duration-300">
            <div class="absolute inset-0 bg-gradient-to-r from-blue-400/10 to-transparent"></div>
            <div class="relative flex justify-between items-start">
                <div>
                    <h3 class="text-xl font-bold flex items-center">
                        <div id="modal-header-icon-container" class="p-2 bg-white/20 rounded-lg mr-3 backdrop-blur-sm transition-all duration-300">
                            <i data-lucide="file-text" id="modal-header-icon" class="w-5 h-5"></i>
                        </div>
                        <span id="modal-header-title">File Number Selector</span>
                    </h3>
                </div>
                <button type="button" class="p-2 hover:bg-white/20 rounded transition-colors"
                    id="phs-fileno-modal-close" onclick="event.stopPropagation(); PhsFileNoModal.close();">
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>
            </div>
        </div>

        <!-- Modal Body -->
        <div class="px-6 py-4 bg-gray-50 flex-1 overflow-y-auto">
            <!-- Tab Navigation -->
            <div class="flex overflow-x-auto no-scrollbar gap-1 mb-4 bg-white p-1 rounded border border-gray-200">
                <button type="button"
                    class="fileno-tab-btn flex-1 px-5 py-3 text-base font-semibold rounded-md transition-all duration-200 bg-white text-blue-600 shadow-sm whitespace-nowrap"
                    data-tab="mls" onclick="PhsFileNoModal.switchTab('mls'); return false;">
                    <div class="flex items-center justify-center space-x-2">
                        <i data-lucide="building-2" class="w-5 h-5 text-blue-600"></i>
                        <span>MLS</span>
                    </div>
                </button>
                <button type="button"
                    class="fileno-tab-btn flex-1 px-5 py-3 text-base font-semibold rounded-md transition-all duration-200 text-gray-700 hover:text-gray-900 whitespace-nowrap"
                    data-tab="kangis" onclick="PhsFileNoModal.switchTab('kangis'); return false;">
                    <div class="flex items-center justify-center space-x-2">
                        <i data-lucide="map" class="w-5 h-5 text-green-600"></i>
                        <span>KANGIS</span>
                    </div>
                </button>
                <button type="button"
                    class="fileno-tab-btn flex-1 px-5 py-3 text-base font-semibold rounded-md transition-all duration-200 text-gray-700 hover:text-gray-900 whitespace-nowrap"
                    data-tab="newkangis" onclick="PhsFileNoModal.switchTab('newkangis'); return false;">
                    <div class="flex items-center justify-center space-x-2">
                        <i data-lucide="map-pin" class="w-5 h-5 text-purple-600"></i>
                        <span>New KANGIS</span>
                    </div>
                </button>
                <button type="button"
                    class="fileno-tab-btn flex-1 px-5 py-3 text-base font-semibold rounded-md transition-all duration-200 text-gray-700 hover:text-gray-900 whitespace-nowrap"
                    data-tab="sltr" onclick="PhsFileNoModal.switchTab('sltr'); return false;">
                    <div class="flex items-center justify-center space-x-2">
                        <i data-lucide="file-text" class="w-5 h-5 text-indigo-600"></i>
                        <span>SLTR</span>
                    </div>
                </button>
                <button type="button"
                    class="fileno-tab-btn flex-1 px-5 py-3 text-base font-semibold rounded-md transition-all duration-200 text-gray-700 hover:text-gray-900 whitespace-nowrap"
                    data-tab="old_mls" onclick="PhsFileNoModal.switchTab('old_mls'); return false;">
                    <div class="flex items-center justify-center space-x-2">
                        <i data-lucide="archive" class="w-5 h-5 text-yellow-600"></i>
                        <span>Old MLS (KN)</span>
                    </div>
                </button>
                <button type="button"
                    class="fileno-tab-btn flex-1 px-5 py-3 text-base font-semibold rounded-md transition-all duration-200 text-gray-700 hover:text-gray-900 whitespace-nowrap"
                    data-tab="sit" onclick="PhsFileNoModal.switchTab('sit'); return false;">
                    <div class="flex items-center justify-center space-x-2">
                        <i data-lucide="file-digit" class="w-5 h-5 text-pink-600"></i>
                        <span>SIT</span>
                    </div>
                </button>
                <button type="button"
                    class="fileno-tab-btn flex-1 px-5 py-3 text-base font-semibold rounded-md transition-all duration-200 text-gray-700 hover:text-gray-900 whitespace-nowrap"
                    data-tab="dciv" onclick="PhsFileNoModal.switchTab('dciv'); return false;">
                    <div class="flex items-center justify-center space-x-2">
                        <i data-lucide="folder-open" class="w-5 h-5 text-teal-600"></i>
                        <span>DCIV</span>
                    </div>
                </button>
                <button type="button"
                    class="fileno-tab-btn flex-1 px-5 py-3 text-base font-semibold rounded-md transition-all duration-200 text-gray-700 hover:text-gray-900 whitespace-nowrap"
                    data-tab="gkn" onclick="PhsFileNoModal.switchTab('gkn'); return false;">
                    <div class="flex items-center justify-center space-x-2">
                        <i data-lucide="map-pinned" class="w-5 h-5 text-orange-600"></i>
                        <span>GKN</span>
                    </div>
                </button>
            </div>

            <!-- Tab Content Container -->
            <div class="tab-content-container">

                <!-- MLS Tab Content -->
                <div class="fileno-tab-content bg-white rounded p-4 border border-gray-200" data-tab="mls"
                    style="display: block;">

                    <!-- Input Method Toggle -->
                    <div class="flex p-1 bg-gray-100 rounded-lg mb-4" id="modal-mls-method-toggle">
                        <label class="flex-1 flex items-center justify-center cursor-pointer group">
                            <input type="radio" name="mls-input-method" value="smart" class="sr-only peer" checked>
                            <span class="flex items-center space-x-2 px-4 py-2 rounded-md font-medium text-sm transition-all duration-200 
                                             text-gray-600 peer-checked:bg-blue-500 peer-checked:text-white peer-checked:shadow-sm
                                             hover:text-gray-800 peer-checked:hover:bg-blue-600">
                                <i data-lucide="sparkles" class="w-4 h-4"></i>
                                <span>Smart Selector</span>
                            </span>
                        </label>
                        <label class="flex-1 flex items-center justify-center cursor-pointer group">
                            <input type="radio" name="mls-input-method" value="manual" class="sr-only peer">
                            <span class="flex items-center space-x-2 px-4 py-2 rounded-md font-medium text-sm transition-all duration-200 
                                             text-gray-600 peer-checked:bg-blue-500 peer-checked:text-white peer-checked:shadow-sm
                                             hover:text-gray-800 peer-checked:hover:bg-blue-600">
                                <i data-lucide="pencil" class="w-4 h-4"></i>
                                <span>Manual Entry</span>
                            </span>
                        </label>
                    </div>

                    <!-- No File Number Toggle -->
                    @if(str_contains(request()->url(), 'propertycard'))
                        <div class="mb-4 p-3 bg-red-50 border border-red-100 rounded-lg">
                            <label class="inline-flex items-center text-sm font-medium text-red-800 cursor-pointer">
                                <input type="checkbox" id="modal-no-file-number-toggle"
                                    class="form-checkbox h-4 w-4 text-red-600 rounded border-gray-300 focus:ring-red-500">
                                <span class="ml-2">This record has no official File Number</span>
                            </label>
                            <p class="text-[10px] text-red-600 mt-1 ml-6 italic">Checking this will generate a temporary ID
                                for tracking until an official number is assigned.</p>
                        </div>
                    @endif

                    <!-- Smart Selector Section -->
                    <div class="mls-input-section" data-method="smart">
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Search Existing MLS
                                Files</label>
                            <select id="mls-smart-selector"
                                class="w-full p-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                <option value="">Select from recent files or search for more...</option>
                            </select>
                            <div id="mls-loading" class="hidden mt-2">
                                <div class="flex items-center p-2 bg-blue-50 rounded text-sm">
                                    <div
                                        class="animate-spin rounded-full h-4 w-4 border-2 border-blue-600 border-t-transparent mr-2">
                                    </div>
                                    <span class="text-blue-700">Loading MLS file numbers...</span>
                                </div>
                            </div>
                            <div class="text-xs text-gray-500 mt-1">
                                💡 <strong>Tip:</strong> Recent files are pre-loaded. Start typing to search for more
                                options.
                            </div>
                        </div>
                    </div>

                    <!-- Manual Entry Section -->
                    <div class="mls-input-section hidden" data-method="manual">
                        <div class="mb-3 space-y-4">
                            <!-- File Type -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">File Type</label>
                                <select id="mls-file-type"
                                    class="w-full p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500">
                                    <option value="regular">Regular</option>
                                    <option value="temporary">Temporary</option>
                                    <option value="extension">Extension</option>
                                    <option value="miscellaneous">Miscellaneous</option>
                                    <option value="klaes_temp_fileno">KLAES Temp FileNo</option>
                                </select>
                            </div>

                            <!-- Regular/Temporary Fields -->
                            <div class="mls-type-fields mt-2" data-type="regular temporary">
                                <div class="grid grid-cols-3 gap-2">
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 mb-1">Prefix</label>
                                        <select id="mls-prefix"
                                            class="w-full p-2 text-sm border border-gray-300 rounded">
                                            <option value="">Select prefix</option>
                                            <optgroup label="Standard">
                                                <option value="RES">RES - Residential</option>
                                                <option value="COM">COM - Commercial</option>
                                                <option value="IND">IND - Industrial</option>
                                                <option value="AG">AG - Agricultural</option>
                                            </optgroup>
                                            <optgroup label="Conversion">
                                                <option value="CON-RES">CON-RES - Con. Residential</option>
                                                <option value="CON-COM">CON-COM - Con. Commercial</option>
                                                <option value="CON-IND">CON-IND - Con. Industrial</option>
                                                <option value="CON-AG">CON-AG - Con. Agricultural</option>
                                            </optgroup>
                                            <optgroup label="RC Options">
                                                <option value="RES-RC">RES-RC</option>
                                                <option value="COM-RC">COM-RC</option>
                                                <option value="AG-RC">AG-RC</option>
                                                <option value="IND-RC">IND-RC</option>
                                                <option value="CON-RES-RC">CON-RES-RC</option>
                                                <option value="CON-COM-RC">CON-COM-RC</option>
                                                <option value="CON-AG-RC">CON-AG-RC</option>
                                                <option value="CON-IND-RC">CON-IND-RC</option>
                                            </optgroup>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 mb-1">Year</label>
                                        <select id="mls-year"
                                            class="w-full p-2 text-sm border border-gray-300 rounded year-select"
                                            data-placeholder="Select year">
                                            <option value="">Select year</option>
                                            @for($year = date('Y'); $year >= 1981; $year--)
                                                <option value="{{ $year }}" {{ ($year == date('Y')) ? 'selected' : '' }}>
                                                    {{ $year }}
                                                </option>
                                            @endfor
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 mb-1">Serial</label>
                                        <input type="text" id="mls-serial"
                                            oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                                            class="w-full p-2 text-sm border border-gray-300 rounded" placeholder="10">
                                    </div>
                                </div>
                            </div>

                            <!-- Extension Fields -->
                            <div class="mls-type-fields hidden mt-2" data-type="extension">
                                <div class="grid grid-cols-3 gap-2">
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 mb-1">Prefix</label>
                                        <select id="mls-extension-prefix"
                                            class="w-full p-2 text-sm border border-gray-300 rounded">
                                            <option value="">Select prefix</option>
                                            <optgroup label="Standard">
                                                <option value="RES">RES - Residential</option>
                                                <option value="COM">COM - Commercial</option>
                                                <option value="IND">IND - Industrial</option>
                                                <option value="AG">AG - Agricultural</option>
                                            </optgroup>
                                            <optgroup label="Conversion">
                                                <option value="CON-RES">CON-RES - Con. Residential</option>
                                                <option value="CON-COM">CON-COM - Con. Commercial</option>
                                                <option value="CON-IND">CON-IND - Con. Industrial</option>
                                                <option value="CON-AG">CON-AG - Con. Agricultural</option>
                                            </optgroup>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 mb-1">Year</label>
                                        <select id="mls-extension-year"
                                            class="w-full p-2 text-sm border border-gray-300 rounded year-select"
                                            data-placeholder="Select year">
                                            <option value="">Select year</option>
                                            @for($year = date('Y'); $year >= 1981; $year--)
                                                <option value="{{ $year }}" {{ ($year == date('Y')) ? 'selected' : '' }}>
                                                    {{ $year }}
                                                </option>
                                            @endfor
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 mb-1">Serial</label>
                                        <input type="text" id="mls-extension-serial"
                                            oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                                            class="w-full p-2 text-sm border border-gray-300 rounded" placeholder="10">
                                    </div>
                                </div>
                            </div>

                            <!-- Miscellaneous Fields -->
                            <div class="mls-type-fields hidden mt-2" data-type="miscellaneous">
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 mb-1">Middle
                                            Prefix</label>
                                        <input type="text" id="mls-middle-prefix"
                                            class="w-full p-2 border border-gray-300 rounded" placeholder="KN"
                                            value="KN">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 mb-1">Serial</label>
                                        <input type="text" id="mls-misc-serial"
                                            oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                                            class="w-full p-2 border border-gray-300 rounded" placeholder="001">
                                    </div>
                                </div>
                            </div>

                            <!-- KLAES Temp FileNo Fields -->
                            <div class="mls-type-fields hidden mt-2" data-type="klaes_temp_fileno">
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 mb-1">Prefix</label>
                                        <input type="text" id="mls-klaes-temp-prefix"
                                            class="w-full p-2 border border-gray-300 rounded bg-gray-50" value="TEMP" readonly>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 mb-1">Serial</label>
                                        <input type="text" id="mls-klaes-temp-serial"
                                            oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                                            class="w-full p-2 border border-gray-300 rounded" placeholder="35123">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- MLS Preview Section -->
                    <div class="bg-gray-50 border border-gray-200 rounded p-3 mt-4">
                        <div class="flex items-center justify-between">
                            <div class="flex-1">
                                <label class="block text-sm font-medium text-gray-800 mb-2">Preview</label>
                                <div id="mls-preview"
                                    class="text-lg font-mono font-bold text-blue-900 bg-white border border-blue-200 px-3 py-2 rounded h-[48px] flex items-center">
                                    <span class="text-gray-400 font-normal">-</span>
                                </div>
                            </div>

                            <div class="ml-3">
                                <button type="button" id="mls-copy-btn"
                                    class="px-3 py-2 bg-blue-500 text-white rounded hover:bg-blue-600 disabled:opacity-50 disabled:cursor-not-allowed"
                                    disabled>
                                    <div class="flex items-center space-x-1">
                                        <i data-lucide="copy" class="w-4 h-4"></i>
                                        <span class="text-sm">Copy</span>
                                    </div>
                                </button>
                            </div>
                        </div>
                        <!-- MLS FILE DETAILS UI -->
                        <div id="mls-details-container"></div>
                    </div>

                    <!-- Temporary File Number UI -->
                    <div id="modal-temp-wrapper"
                        class="mt-4 rounded-lg border border-blue-200 bg-blue-50 p-4 space-y-2 hidden">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-bold text-blue-900 uppercase tracking-wider">Temporary File
                                    Number</p>
                                <p class="text-[10px] text-blue-700">Generated for legacy cross-referencing only.</p>
                            </div>
                            <button type="button" id="modal-refresh-temp"
                                class="inline-flex items-center px-2 py-1 text-[10px] font-bold text-blue-700 bg-white border border-blue-300 rounded hover:bg-blue-100 transition-colors shadow-sm">
                                <i data-lucide="refresh-cw" class="w-3 h-3 mr-1"></i>
                                <span>Regenerate</span>
                            </button>
                        </div>
                        <div class="flex items-baseline gap-2">
                            <span id="modal-temp-value"
                                class="text-lg font-mono font-bold text-blue-900">Pending...</span>
                            <span id="modal-temp-status"
                                class="text-[10px] text-blue-600 hidden animate-pulse">Generating...</span>
                        </div>
                    </div>

                </div>

                <!-- KANGIS Tab Content -->
                <div class="fileno-tab-content hidden bg-white rounded p-4 border border-gray-200" data-tab="kangis">
                    <div class="bg-green-600 text-white p-3 rounded mb-3">
                        <div class="flex items-center">
                            <i data-lucide="map" class="w-4 h-4 mr-2"></i>
                            <h4 class="font-medium text-sm">KANGIS (Legacy)</h4>
                        </div>
                    </div>

                    <!-- Input Method Selection -->
                    <div class="flex p-1 bg-gray-100 rounded-lg mb-4">
                        <label class="flex-1 flex items-center justify-center cursor-pointer group">
                            <input type="radio" name="kangis-input-method" value="smart" class="sr-only peer" checked>
                            <span class="flex items-center space-x-2 px-4 py-2 rounded-md font-medium text-sm transition-all duration-200 
                                             text-gray-600 peer-checked:bg-green-500 peer-checked:text-white peer-checked:shadow-sm
                                             hover:text-gray-800 peer-checked:hover:bg-green-600">
                                <i data-lucide="sparkles" class="w-4 h-4"></i>
                                <span>Smart Selector</span>
                            </span>
                        </label>
                        <label class="flex-1 flex items-center justify-center cursor-pointer group">
                            <input type="radio" name="kangis-input-method" value="manual" class="sr-only peer">
                            <span class="flex items-center space-x-2 px-4 py-2 rounded-md font-medium text-sm transition-all duration-200 
                                             text-gray-600 peer-checked:bg-green-500 peer-checked:text-white peer-checked:shadow-sm
                                             hover:text-gray-800 peer-checked:hover:bg-green-600">
                                <i data-lucide="pencil" class="w-4 h-4"></i>
                                <span>Manual Entry</span>
                            </span>
                        </label>
                    </div>

                    <!-- Smart Selector Section -->
                    <div class="kangis-input-section" data-method="smart">
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Search Existing KANGIS
                                Files</label>
                            <select id="kangis-smart-selector"
                                class="w-full p-2 border border-gray-300 rounded focus:ring-2 focus:ring-green-500">
                                <option value="">Select from recent files or search for more...</option>
                            </select>
                        </div>
                    </div>

                    <!-- Manual Entry Section -->
                    <div class="kangis-input-section hidden" data-method="manual">
                        <div class="grid grid-cols-3 gap-3 mb-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">File Type</label>
                                <select id="kangis-file-type"
                                    class="w-full p-2 border border-gray-300 rounded focus:ring-2 focus:ring-green-500">
                                    <option value="regular">Regular</option>
                                    <option value="temporary">Temporary File (T)</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">File Prefix</label>
                                <select id="kangis-prefix"
                                    class="w-full p-2 border border-gray-300 rounded focus:ring-2 focus:ring-green-500">
                                    <option value="">Select Prefix</option>
                                    <option value="KNML">KNML</option>
                                    <option value="MLKN">MLKN</option>
                                    <option value="KNGP">KNGP</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Serial Number</label>
                                <input type="text" id="kangis-number"
                                    oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                                    class="w-full p-2 border border-gray-300 rounded focus:ring-2 focus:ring-green-500"
                                    placeholder="e.g., 10 or 2500">
                            </div>
                        </div>
                    </div>

                    <!-- Preview -->
                    <div class="bg-gray-50 border rounded p-3">
                        <div class="flex items-center justify-between">
                            <div class="flex-1">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Preview</label>
                                <div id="kangis-preview"
                                    class="text-lg font-mono font-bold text-green-900 bg-white border px-3 py-2 rounded h-[48px] flex items-center">
                                    <span class="text-gray-400 font-normal">-</span>
                                </div>
                            </div>
                            <div class="ml-3">
                                <button type="button" id="kangis-copy-btn"
                                    class="px-3 py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300 disabled:opacity-50"
                                    disabled>
                                    <i data-lucide="copy" class="w-4 h-4 mr-1"></i>
                                    Copy
                                </button>
                            </div>
                        </div>
                        <!-- KANGIS FILE DETAILS UI -->
                        <div id="kangis-details-container" class="mt-3"></div>
                    </div>
                </div>

                <!-- New KANGIS Tab Content -->
                <div class="fileno-tab-content hidden bg-white rounded p-4 border border-gray-200" data-tab="newkangis">
                    <div class="bg-purple-600 text-white p-3 rounded mb-3">
                        <div class="flex items-center">
                            <i data-lucide="map-pin" class="w-4 h-4 mr-2"></i>
                            <h4 class="font-medium text-sm">New KANGIS Format</h4>
                        </div>
                    </div>

                    <!-- Input Method Selection -->
                    <div class="flex p-1 bg-gray-100 rounded-lg mb-4">
                        <label class="flex-1 flex items-center justify-center cursor-pointer group">
                            <input type="radio" name="newkangis-input-method" value="smart" class="sr-only peer" checked>
                            <span class="flex items-center space-x-2 px-4 py-2 rounded-md font-medium text-sm transition-all duration-200 
                                             text-gray-600 peer-checked:bg-purple-500 peer-checked:text-white peer-checked:shadow-sm
                                             hover:text-gray-800 peer-checked:hover:bg-purple-600">
                                <i data-lucide="sparkles" class="w-4 h-4"></i>
                                <span>Smart Selector</span>
                            </span>
                        </label>
                        <label class="flex-1 flex items-center justify-center cursor-pointer group">
                            <input type="radio" name="newkangis-input-method" value="manual" class="sr-only peer">
                            <span class="flex items-center space-x-2 px-4 py-2 rounded-md font-medium text-sm transition-all duration-200 
                                             text-gray-600 peer-checked:bg-purple-500 peer-checked:text-white peer-checked:shadow-sm
                                             hover:text-gray-800 peer-checked:hover:bg-purple-600">
                                <i data-lucide="pencil" class="w-4 h-4"></i>
                                <span>Manual Entry</span>
                            </span>
                        </label>
                    </div>

                    <!-- Smart Selector Section -->
                    <div class="newkangis-input-section" data-method="smart">
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Search Existing New KANGIS Files</label>
                            <select id="newkangis-smart-selector"
                                class="w-full p-2 border border-gray-300 rounded focus:ring-2 focus:ring-purple-500">
                                <option value="">Select from recent files or search for more...</option>
                            </select>
                        </div>
                    </div>

                    <!-- Manual Entry Section -->
                    <div class="newkangis-input-section hidden" data-method="manual">
                        <div class="grid grid-cols-2 gap-3 mb-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">File Prefix</label>
                                <input type="text" id="newkangis-prefix"
                                    class="w-full p-2 border border-gray-300 rounded bg-gray-100 text-gray-700"
                                    value="KN" readonly>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Serial Number</label>
                                <input type="text" id="newkangis-number"
                                    oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                                    class="w-full p-2 border border-gray-300 rounded focus:ring-2 focus:ring-purple-500"
                                    placeholder="e.g., 1586">
                            </div>
                        </div>
                    </div>

                    <!-- Preview -->
                    <div class="bg-gray-50 border rounded p-3">
                        <div class="flex items-center justify-between">
                            <div class="flex-1">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Preview</label>
                                <div id="newkangis-preview"
                                    class="text-lg font-mono font-bold text-purple-900 bg-white border px-3 py-2 rounded h-[48px] flex items-center">
                                    <span class="text-gray-400 font-normal">-</span>
                                </div>
                            </div>
                            <div class="ml-3">
                                <button type="button" id="newkangis-copy-btn"
                                    class="px-3 py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300 disabled:opacity-50"
                                    disabled>
                                    <i data-lucide="copy" class="w-4 h-4 mr-1"></i>
                                    Copy
                                </button>
                            </div>
                        </div>
                        <!-- NEW KANGIS FILE DETAILS UI -->
                        <div id="newkangis-details-container" class="mt-3"></div>
                    </div>
                </div>

                <!-- SLTR Tab Content -->
                <div class="fileno-tab-content hidden bg-white rounded p-4 border border-gray-200" data-tab="sltr">
                    <div class="bg-indigo-600 text-white p-3 rounded mb-4">
                        <div class="flex items-center">
                            <i data-lucide="file-text" class="w-4 h-4 mr-2"></i>
                            <h4 class="font-medium text-sm">SLTR</h4>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Serial</label>
                        <input type="text" id="mls-sltr-serial"
                            oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                            class="w-full p-2 border border-gray-300 rounded focus:ring-2 focus:ring-indigo-500" placeholder="001">
                    </div>
                    <div class="bg-gray-50 border rounded p-3">
                        <div class="flex items-center justify-between">
                            <div class="flex-1">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Preview</label>
                                <div id="sltr-preview" class="text-lg font-mono font-bold text-indigo-900 bg-white border px-3 py-2 rounded h-[48px] flex items-center">
                                    <span class="text-gray-400 font-normal">-</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Old MLS (KN) Tab Content -->
                <div class="fileno-tab-content hidden bg-white rounded p-4 border border-gray-200" data-tab="old_mls">
                    <div class="bg-yellow-600 text-white p-3 rounded mb-4">
                        <div class="flex items-center">
                            <i data-lucide="archive" class="w-4 h-4 mr-2"></i>
                            <h4 class="font-medium text-sm">Old MLS (KN)</h4>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Serial</label>
                        <input type="text" id="mls-old-serial"
                            oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                            class="w-full p-2 border border-gray-300 rounded focus:ring-2 focus:ring-yellow-500" placeholder="12345">
                    </div>
                    <div class="bg-gray-50 border rounded p-3">
                        <div class="flex items-center justify-between">
                            <div class="flex-1">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Preview</label>
                                <div id="old_mls-preview" class="text-lg font-mono font-bold text-yellow-900 bg-white border px-3 py-2 rounded h-[48px] flex items-center">
                                    <span class="text-gray-400 font-normal">-</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SIT Tab Content -->
                <div class="fileno-tab-content hidden bg-white rounded p-4 border border-gray-200" data-tab="sit">
                    <div class="bg-pink-600 text-white p-3 rounded mb-4">
                        <div class="flex items-center">
                            <i data-lucide="file-digit" class="w-4 h-4 mr-2"></i>
                            <h4 class="font-medium text-sm">SIT</h4>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3 mb-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Year</label>
                            <select id="mls-sit-year" class="w-full p-2 border border-gray-300 rounded year-select focus:ring-2 focus:ring-pink-500" data-placeholder="Select year">
                                <option value="">Select year</option>
                                @for($year = date('Y'); $year >= 1981; $year--)
                                    <option value="{{$year}}" {{($year == date('Y')) ? 'selected' : ''}}>
                                        {{$year}}
                                    </option>
                                @endfor
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Serial</label>
                            <input type="text" id="mls-sit-serial"
                                oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                                class="w-full p-2 border border-gray-300 rounded focus:ring-2 focus:ring-pink-500" placeholder="001">
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="inline-flex items-center text-sm font-medium text-gray-700 cursor-pointer">
                            <input type="checkbox" id="mls-sit-temporary" class="form-checkbox h-4 w-4 text-orange-500 rounded border-gray-300 focus:ring-orange-400">
                            <span class="ml-2">Temporary <span class="text-gray-400">(T)</span></span>
                        </label>
                    </div>
                    <div class="bg-gray-50 border rounded p-3">
                        <div class="flex items-center justify-between">
                            <div class="flex-1">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Preview</label>
                                <div id="sit-preview" class="text-lg font-mono font-bold text-pink-900 bg-white border px-3 py-2 rounded h-[48px] flex items-center">
                                    <span class="text-gray-400 font-normal">-</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- DCIv Tab Content -->
                <div class="fileno-tab-content hidden bg-white rounded p-4 border border-gray-200" data-tab="dciv">
                    <div class="bg-teal-600 text-white p-3 rounded mb-4">
                        <div class="flex items-center">
                            <i data-lucide="folder-open" class="w-4 h-4 mr-2"></i>
                            <h4 class="font-medium text-sm">DCIV</h4>
                        </div>
                    </div>
                    <div class="grid grid-cols-3 gap-3 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Prefix</label>
                            <select id="mls-dciv-lpcc-prefix" class="w-full p-2 border border-gray-300 rounded focus:ring-2 focus:ring-teal-500">
                                <option value="LPCC">LPCC</option>
                                <option value="DCIV">DCIV</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Year</label>
                            <select id="mls-dciv-lpcc-year" class="w-full p-2 border border-gray-300 rounded year-select focus:ring-2 focus:ring-teal-500" data-placeholder="Select year">
                                <option value="">Select year</option>
                                @for($year = date('Y'); $year >= 1981; $year--)
                                    <option value="{{$year}}" {{($year == date('Y')) ? 'selected' : ''}}>
                                        {{$year}}
                                    </option>
                                @endfor
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Serial</label>
                            <input type="text" id="mls-dciv-lpcc-serial"
                                oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                                class="w-full p-2 border border-gray-300 rounded focus:ring-2 focus:ring-teal-500" placeholder="10">
                        </div>
                    </div>
                    <div class="bg-gray-50 border rounded p-3">
                        <div class="flex items-center justify-between">
                            <div class="flex-1">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Preview</label>
                                <div id="dciv-preview" class="text-lg font-mono font-bold text-teal-900 bg-white border px-3 py-2 rounded h-[48px] flex items-center">
                                    <span class="text-gray-400 font-normal">-</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- GKN Tab Content -->
                <div class="fileno-tab-content hidden bg-white rounded p-4 border border-gray-200" data-tab="gkn">
                    <div class="bg-orange-600 text-white p-3 rounded mb-4">
                        <div class="flex items-center">
                            <i data-lucide="map-pinned" class="w-4 h-4 mr-2"></i>
                            <h4 class="font-medium text-sm">GKN (Survey Registry)</h4>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3 mb-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Prefix</label>
                            <select id="mls-gkn-prefix" class="w-full p-2 border border-gray-300 rounded focus:ring-2 focus:ring-orange-500">
                                <option value="GKN" selected>GKN</option>
                                <option value="MISC">MISC KN</option>
                                <option value="LPKN">LPKN</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Sequential Number</label>
                            <input type="text" id="mls-gkn-serial"
                                oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                                class="w-full p-2 border border-gray-300 rounded focus:ring-2 focus:ring-orange-500" placeholder="1">
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="inline-flex items-center text-sm font-medium text-gray-700 cursor-pointer">
                            <input type="checkbox" id="mls-gkn-temporary" class="form-checkbox h-4 w-4 text-orange-500 rounded border-gray-300 focus:ring-orange-400">
                            <span class="ml-2">Temporary <span class="text-gray-400">(T)</span></span>
                        </label>
                    </div>
                    <div class="bg-gray-50 border rounded p-3">
                        <div class="flex items-center justify-between">
                            <div class="flex-1">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Preview</label>
                                <div id="gkn-preview" class="text-lg font-mono font-bold text-orange-900 bg-white border px-3 py-2 rounded h-[48px] flex items-center">
                                    <span class="text-gray-400 font-normal">-</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div> <!-- End Tab Content Container -->

        </div> <!-- End Modal Body -->

        <!-- Modal Footer -->
        <div class="flex justify-between items-center px-6 py-4 border-t border-gray-200 bg-gray-50 shrink-0">
            <div class="flex items-center space-x-3">
                <span id="validation-message" class="text-sm"></span>
            </div>

            <div class="flex space-x-3">
                <button type="button" class="px-4 py-2 border border-gray-300 text-gray-700 rounded hover:bg-gray-100"
                    onclick="PhsFileNoModal.close()">
                    Cancel
                </button>
                <button type="button" id="apply-fileno-btn"
                    class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600 disabled:opacity-50 disabled:cursor-not-allowed"
                    disabled onclick="PhsFileNoModal.apply()">
                    Apply File Number
                </button>
            </div>
        </div>

    </div> <!-- End White Box Wrapper -->
</div> <!-- End Global Overlay -->

@once
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <style>
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        #phs-fileno-modal {
            z-index: 2000000 !important;
        }

        /*
         * The file-number selector is intentionally a light-themed modal. When the
         * portal is in dark mode the global `dark:text-gray-100` body colour is
         * inherited by Select2's generated markup (and native option lists),
         * leaving faint light-grey text on the white dropdown. Force readable
         * colours for every control inside the modal + its Select2 dropdown.
         */
        #phs-fileno-modal select,
        #phs-fileno-modal option,
        #phs-fileno-modal optgroup {
            color: #1f2937;          /* gray-800 */
            background-color: #ffffff;
        }
        /* Select2 dropdowns are appended inside #phs-fileno-modal (dropdownParent),
           so these stay scoped to this modal and don't affect Select2 elsewhere. */
        #phs-fileno-modal .select2-dropdown,
        #phs-fileno-modal .select2-results__option {
            background-color: #ffffff;
            color: #1f2937;          /* gray-800 */
        }
        #phs-fileno-modal .select2-results__option[aria-disabled="true"] {
            color: #9ca3af;          /* gray-400 — muted, e.g. "Searching…" */
        }
        #phs-fileno-modal .select2-results__option--highlighted[aria-selected],
        #phs-fileno-modal .select2-results__option--highlighted {
            background-color: #2563eb !important;  /* blue-600 */
            color: #ffffff !important;
        }
        #phs-fileno-modal .select2-results__option[aria-selected="true"] {
            background-color: #eff6ff;  /* blue-50 */
            color: #1e3a8a;             /* blue-900 */
        }
        #phs-fileno-modal .select2-container--default .select2-selection--single,
        #phs-fileno-modal .select2-container--default .select2-selection--multiple {
            background-color: #ffffff;
            border-color: #d1d5db;      /* gray-300 */
        }
        #phs-fileno-modal .select2-container--default .select2-selection__rendered {
            color: #1f2937 !important;
        }
        #phs-fileno-modal .select2-container--default .select2-selection__placeholder {
            color: #6b7280;             /* gray-500 */
        }
        #phs-fileno-modal .select2-search--dropdown .select2-search__field {
            background-color: #ffffff;
            color: #1f2937;
            border-color: #d1d5db;
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function () {
            // Initialize Select2 on year dropdowns
            $('.year-select').select2({
                dropdownParent: $('#phs-fileno-modal'),
                placeholder: 'Select year',
                allowClear: true,
                width: '100%',
                minimumResultsForSearch: 5
            });

            // Auto-fix leading zeros in serial number fields
            function removeLeadingZeros(value) {
                return value.replace(/^0+/, '') || '0';
            }

            const serialInputs = [
                '#mls-serial',
                '#mls-extension-serial',
                '#mls-misc-serial',
                '#mls-sit-serial',
                '#mls-sltr-serial',
                '#mls-old-serial',
                '#mls-dciv-lpcc-serial',
                '#mls-gkn-serial',
                '#kangis-number',
                '#newkangis-number'
            ];

            serialInputs.forEach(function (selector) {
                $(document).on('input blur', selector, function () {
                    let value = $(this).val().trim();
                    if (value && /^\d+$/.test(value)) {
                        let cleanValue = removeLeadingZeros(value);
                        if (cleanValue !== value) {
                            $(this).val(cleanValue);
                        }
                    }
                });
            });
        });
    </script>
@endonce
