<!-- Manual File Number Modal -->
<div id="manual-file-number-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 hidden z-50">
    <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full max-h-[90vh] overflow-y-auto">
        <!-- Modal Header -->
        <div class="p-6 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <i class="fa-solid fa-file-lines text-blue-600 text-xl mr-3"></i>
                    <div>
                        <h2 class="text-xl font-semibold text-gray-900">Manual File Number Entry</h2>
                        <p class="text-sm text-gray-600 mt-1">Enter file number details manually using the appropriate format</p>
                    </div>
                </div>
                <button type="button" id="close-manual-modal" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <i class="fa-solid fa-times text-xl"></i>
                </button>
            </div>
        </div>

        <!-- Modal Content -->
        <div class="p-6" x-data="{ 
            tab: 'mls',
            mlsPrefix: '', 
            mlsYear: (new Date().getFullYear()).toString(), 
            mlsSerial: '', 
            mlsType: 'regular',
            mlsMiddlePrefix: 'KN', 
            mlsMiscSerial: '', 
            mlsSpecialSerial: '', 
            mlsOldSerial: '',
            mlsExistingOptions: [], 
            mlsExistingSelected: '',
            kangisPrefix: '', 
            kangisNumber: '',
            newkangisPrefix: 'KN', 
            newkangisNumber: '',
            
            loadExistingMls() {
                if (this.mlsExistingOptions.length) return;
                fetch('/api/get-existing-mls-files', { 
                    headers: { 
                        'Content-Type': 'application/json', 
                        'X-CSRF-TOKEN': (document.querySelector('meta[name=csrf-token]')?.content || '') 
                    } 
                })
                .then(r => r.json())
                .then(d => {
                    if (d?.success && Array.isArray(d.files)) {
                        this.mlsExistingOptions = d.files.map(f => f.mlsFNo || f.file_number).filter(Boolean);
                    }
                })
                .catch(() => {});
            },
            
            mlsPreview() { 
                // Regular / Temporary
                    if (this.mlsType === 'regular' || this.mlsType === 'temporary') {
                    const parts = [];
                    if (this.mlsPrefix) parts.push(this.mlsPrefix);
                    if (this.mlsYear) parts.push(this.mlsYear);
                    // Do not auto-pad manual MLS serials — use exactly what user typed
                    if (this.mlsSerial) parts.push(this.mlsSerial.toString());
                    let baseFileNo = parts.length === 3 ? parts.join('-') : '';
                    if (baseFileNo && this.mlsType === 'temporary') return baseFileNo + '(T)';
                    return baseFileNo;
                }
                // Extension -> use existing file number
                if (this.mlsType === 'extension') {
                    if (this.mlsExistingSelected) return this.mlsExistingSelected.trim().replace(/-$/, '') + ' AND EXTENSION';
                    return '';
                }
                // Miscellaneous
                if (this.mlsType === 'miscellaneous') {
                    if (this.mlsMiddlePrefix && this.mlsMiscSerial) return `MISC-${this.mlsMiddlePrefix}-${this.mlsMiscSerial}`;
                    return '';
                }
                // SIT -> requires Year + Serial
                if (this.mlsType === 'sit') {
                    if (this.mlsYear && this.mlsSpecialSerial) return `SIT-${this.mlsYear}-${this.mlsSpecialSerial}`;
                    return '';
                }
                // SLTR -> Serial only
                if (this.mlsType === 'sltr') {
                    if (this.mlsSpecialSerial) return `SLTR-${this.mlsSpecialSerial}`;
                    return '';
                }
                // Old MLS
                if (this.mlsType === 'old_mls') {
                    if (this.mlsOldSerial) return `KN ${this.mlsOldSerial}`;
                    return '';
                }
                return '';
            },
            
            kangisPreview() {
                const prefix = (this.kangisPrefix || '').toString().trim();
                const num = (this.kangisNumber || '').toString().trim();
                if (prefix && num) {
                    // Do not auto-pad manual KANGIS numbers — use exactly what user typed
                    const n = num.toString();
                    return `${prefix} ${n}`;
                }
                return prefix || num || '';
            },
            
            newkangisPreview() { 
                const p = (this.newkangisPrefix || '').toString().trim();
                const n = (this.newkangisNumber || '').toString().trim();
                return p && n ? `${p}${n}` : (p || n || ''); 
            },
            
            getCurrentFileNumber() {
                if (this.tab === 'mls') return this.mlsPreview();
                if (this.tab === 'kangis') return this.kangisPreview();
                if (this.tab === 'newkangis') return this.newkangisPreview();
                return '';
            },
            
            getFileNumberType() {
                if (this.tab === 'mls') return 'MLSF File';
                if (this.tab === 'kangis') return 'KANGIS File';
                if (this.tab === 'newkangis') return 'New KANGIS File';
                return 'Property File';
            }
        }" x-cloak>
            
            <!-- File Number Type Information -->
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                <div class="flex items-center mb-2">
                    <i class="fa-solid fa-info-circle text-blue-600 mr-2"></i>
                    <span class="font-medium text-blue-800">File Number Formats</span>
                </div>
                <!-- <div class="text-sm text-blue-700 space-y-1">
                    <div><strong>MLSF:</strong> RES-2024-0001, COM-2024-0572, CON-COM-2019-296, MISC-KN-001, etc.</div>
                    <div><strong>KANGIS:</strong> KNML 00001, MNKL 02500, MLKN 01586, etc.</div>
                    <div><strong>New KANGIS:</strong> KN1586, KN2500, etc.</div>
                </div> -->
            </div>

            <!-- Tab Navigation -->
            <div class="flex space-x-1 mb-6 bg-gray-100 p-1 rounded-lg">
                <button type="button"
                        @click="tab = 'mls'"
                        :class="tab === 'mls' ? 'flex-1 px-4 py-2 text-sm font-medium rounded-md bg-white text-blue-600 shadow-sm transition-all' : 'flex-1 px-4 py-2 text-sm font-medium rounded-md text-gray-500 hover:text-gray-700 transition-all'">
                    <i class="fa-solid fa-building mr-2"></i>
                    MLSF
                </button>
                <button type="button"
                        @click="tab = 'kangis'"
                        :class="tab === 'kangis' ? 'flex-1 px-4 py-2 text-sm font-medium rounded-md bg-white text-blue-600 shadow-sm transition-all' : 'flex-1 px-4 py-2 text-sm font-medium rounded-md text-gray-500 hover:text-gray-700 transition-all'">
                    <i class="fa-solid fa-map-location-dot mr-2"></i>
                    KANGIS
                </button>
                <button type="button"
                        @click="tab = 'newkangis'"
                        :class="tab === 'newkangis' ? 'flex-1 px-4 py-2 text-sm font-medium rounded-md bg-white text-blue-600 shadow-sm transition-all' : 'flex-1 px-4 py-2 text-sm font-medium rounded-md text-gray-500 hover:text-gray-700 transition-all'">
                    <i class="fa-solid fa-map-pin mr-2"></i>
                    New KANGIS
                </button>
            </div>

            <!-- MLSF Tab Content -->
            <div x-show="tab === 'mls'" class="space-y-6">
                <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                    <h3 class="text-lg font-semibold text-gray-900 mb-3">
                        <i class="fa-solid fa-building text-blue-600 mr-2"></i>
                        MLSF File Number
                    </h3>
                    
                    <!-- File type selection dropdown -->
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">File Type</label>
                        <div class="relative">
                            <select x-model="mlsType" @change="if(mlsType==='extension') loadExistingMls()" class="w-full p-3 text-sm border border-gray-300 rounded-lg appearance-none pr-10 bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="regular">Regular</option>
                                <option value="temporary">Temporary</option>
                                <option value="extension">Extension</option>
                                <option value="miscellaneous">Miscellaneous</option>
                                <option value="old_mls">Old MLPP</option>
                                <option value="sltr">SLTR</option>
                                <option value="sit">SIT</option>
                            </select>
                            <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                                <i class="fa-solid fa-chevron-down text-gray-400"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Regular/Temporary -->
                    <div x-show="mlsType === 'regular' || mlsType === 'temporary'" class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">File Prefix</label>
                            <div class="relative">
                                <select x-model="mlsPrefix" class="w-full p-3 border border-gray-300 rounded-lg appearance-none pr-10 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    <option value="">Select prefix</option>
                                    <optgroup label="Standard">
                                        <option value="RES">RES - Residential</option>
                                        <option value="COM">COM - Commercial</option>
                                        <option value="IND">IND - Industrial</option>
                                        <option value="AGR">AG - Agricultural</option>
                                        
                                    </optgroup>
                                    <optgroup label="Conversion">
                                        <option value="CON-RES">CON-RES - Conversion to Residential</option>
                                        <option value="CON-COM">CON-COM - Conversion to Commercial</option>
                                        <option value="CON-IND">CON-IND - Conversion to Industrial</option>
                                        <option value="CON-AGR">CON-AG - Conversion to Agricultural</option>
 
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
                                <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                                    <i class="fa-solid fa-chevron-down text-gray-400"></i>
                                </div>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Year</label>
                            <input type="text" x-model="mlsYear" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="e.g. 2025" maxlength="4">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Serial Number</label>
                            <input type="text" x-model="mlsSerial" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="e.g. 572">
                        </div>
                    </div>

                    <!-- Extension -->
                    <div x-show="mlsType === 'extension'" class="mb-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Year</label>
                                <input type="text" x-model="mlsYear" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="e.g. 2025" maxlength="4">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Select Existing MLPP File Number</label>
                                <select x-model="mlsExistingSelected" @focus="loadExistingMls()" @click="loadExistingMls()" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    <option value="">Select existing file number...</option>
                                    <template x-for="opt in mlsExistingOptions" :key="opt">
                                        <option :value="opt" x-text="opt"></option>
                                    </template>
                                </select>
                            </div>
                        </div>
                        <p class="text-xs text-gray-500 mt-2">Serial not required for extensions.</p>
                    </div>

                    <!-- Miscellaneous -->
                    <div x-show="mlsType === 'miscellaneous'" class="mb-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Middle Prefix</label>
                                <input type="text" x-model="mlsMiddlePrefix" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="e.g. KN">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Serial Number</label>
                                <input type="text" x-model="mlsMiscSerial" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="Enter custom serial (e.g., 001, ABC123)">
                            </div>
                        </div>
                    </div>

                    <!-- SLTR / SIT -->
                    <div x-show="mlsType === 'sltr' || mlsType === 'sit'" class="mb-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div x-show="mlsType === 'sit'">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Year</label>
                                <input type="text" x-model="mlsYear" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="e.g. 2025" maxlength="4">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Serial Number</label>
                                <input type="text" x-model="mlsSpecialSerial" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" :placeholder="mlsType === 'sltr' ? 'Enter SLTR serial (e.g., 001)' : 'Enter SIT serial (e.g., 001)'">
                            </div>
                        </div>
                    </div>

                    <!-- Old MLS -->
                    <div x-show="mlsType === 'old_mls'" class="mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Old MLPP Number</label>
                            <input type="text" x-model="mlsOldSerial" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="e.g. 5467">
                        </div>
                    </div>
                </div>
            </div>

            <!-- KANGIS Tab Content -->
            <div x-show="tab === 'kangis'" class="space-y-6">
                <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                    <h3 class="text-lg font-semibold text-gray-900 mb-3">
                        <i class="fa-solid fa-map-location-dot text-blue-600 mr-2"></i>
                        KANGIS File Number
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">File Prefix</label>
                            <select x-model="kangisPrefix" class="w-full p-3 border border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Select Prefix</option>
                                <option value="KNML">KNML</option>
                                <option value="MNKL">MNKL</option>
                                <option value="MLKN">MLKN</option>
                                <option value="KNGP">KNGP</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Serial Number</label>
                            <input type="text" x-model="kangisNumber" class="w-full p-3 border border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="e.g. 1 or 2500">
                        </div>
                    </div>
                    <div class="mt-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Generated File Number</label>
                        <div class="bg-gradient-to-r from-green-50 to-emerald-50 border border-green-200 rounded-lg p-3">
                            <div class="text-lg font-bold text-green-900 tracking-wide" x-text="kangisPreview() || 'Enter prefix and number to see preview'"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- New KANGIS Tab Content -->
            <div x-show="tab === 'newkangis'" class="space-y-6">
                <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                    <h3 class="text-lg font-semibold text-gray-900 mb-3">
                        <i class="fa-solid fa-map-pin text-blue-600 mr-2"></i>
                        New KANGIS File Number
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">File Prefix</label>
                            <select x-model="newkangisPrefix" class="w-full p-3 border border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Select Prefix</option>
                                <option value="KN">KN</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Serial Number</label>
                            <input type="text" x-model="newkangisNumber" class="w-full p-3 border border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="e.g. 1586">
                        </div>
                    </div>
                    <div class="mt-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Generated File Number</label>
                        <div class="bg-gradient-to-r from-purple-50 to-violet-50 border border-purple-200 rounded-lg p-3">
                            <div class="text-lg font-bold text-purple-900 tracking-wide" x-text="newkangisPreview() || 'Enter prefix and number to see preview'"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Current Selection Preview -->
            <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-lg p-4 mt-6">
                <h4 class="text-lg font-semibold text-blue-900 mb-2">
                    <i class="fa-solid fa-eye mr-2"></i>
                    Current Selection Preview
                </h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <span class="text-sm text-blue-700">File Number:</span>
                        <div class="text-xl font-bold text-blue-900" x-text="getCurrentFileNumber() || 'No file number generated'"></div>
                    </div>
                    <div>
                        <span class="text-sm text-blue-700">Type:</span>
                        <div class="text-lg font-medium text-blue-800" x-text="getFileNumberType()"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Footer -->
        <div class="p-6 border-t border-gray-200 flex justify-end space-x-3">
            <button type="button" id="cancel-manual-modal" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 transition-colors">
                <i class="fa-solid fa-times mr-2"></i>
                Cancel
            </button>
            <button type="button" id="apply-manual-file-number" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">
                <i class="fa-solid fa-check mr-2"></i>
                Apply File Number
            </button>
        </div>
    </div>
</div>
