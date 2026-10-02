<div x-data="{ tab: 'mls',
                      mlsPrefix: '', mlsYear: (new Date().getFullYear()).toString(), mlsSerial: '', mlsType: 'regular',
                      mlsMiddlePrefix: 'KN', mlsMiscSerial: '', mlsSpecialSerial: '', mlsOldSerial: '',
                      mlsExistingOptions: [], mlsExistingSelected: '',
                      // Added initial state for KANGIS and New KANGIS
                      kangisPrefix: '', kangisNumber: '',
                      newkangisPrefix: '', newkangisNumber: '',
                      loadExistingMls() {
                        if (this.mlsExistingOptions.length) return;
                        fetch('/api/get-existing-mls-files', { headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': (document.querySelector('meta[name=csrf-token]')?.content || '') } })
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
                          if (this.mlsSerial) parts.push(this.mlsSerial.toString().padStart(4, '0'));
                          let baseFileNo = parts.length === 3 ? parts.join('-') : '';
                          if (baseFileNo && this.mlsType === 'temporary') return baseFileNo + '(T)';
                          return baseFileNo;
                        }
                        // Extension -> use existing file number
                        if (this.mlsType === 'extension') {
                          if (this.mlsExistingSelected) return this.mlsExistingSelected + ' AND EXTENSION';
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
                        // Do not mutate state during render; just compute preview safely
                        const prefix = (this.kangisPrefix || '').toString().trim();
                        const num = (this.kangisNumber || '').toString().trim();
                        if (prefix && num) {
                          const n = num.padStart(5, '0');
                          return `${prefix} ${n}`;
                        }
                        return prefix || num || '';
                      },
                      newkangisPreview() { 
                        const p = (this.newkangisPrefix || '').toString().trim();
                        const n = (this.newkangisNumber || '').toString().trim();
                        return p && n ? `${p}${n}` : (p || n || ''); 
                      }
                    }"
     class="bg-green-50 border border-green-100 rounded-md p-4 mb-6" x-cloak>
  <div class="flex items-center mb-2">
    <i data-lucide="file" class="w-5 h-5 mr-2 text-green-600"></i>
    <span class="font-medium">File Number Information</span>
  </div>
  <p class="text-sm text-gray-600 mb-4">Select file number type and enter the details</p>

  <!-- Hidden inputs for form submission -->
  <input type="hidden" name="activeFileTab" :value="tab">
  <input type="hidden" name="mlsFNo" :value="mlsPreview()">
  <input type="hidden" name="kangisFileNo" :value="kangisPreview()">
  <input type="hidden" name="NewKANGISFileno" :value="newkangisPreview()">

  <!-- Tab Navigation -->
  <div class="flex space-x-1 mb-4 bg-gray-100 p-1 rounded-lg">
    <button type="button"
            @click="tab = 'mls'"
            :class="tab === 'mls' ? 'flex-1 px-3 py-2 text-sm font-medium rounded-md bg-white text-blue-600 shadow-sm' : 'flex-1 px-3 py-2 text-sm font-medium rounded-md text-gray-500 hover:text-gray-700'">
      MLS
    </button>
    <button type="button"
            @click="tab = 'kangis'"
            :class="tab === 'kangis' ? 'flex-1 px-3 py-2 text-sm font-medium rounded-md bg-white text-blue-600 shadow-sm' : 'flex-1 px-3 py-2 text-sm font-medium rounded-md text-gray-500 hover:text-gray-700'">
      KANGIS
    </button>
    <button type="button"
            @click="tab = 'newkangis'"
            :class="tab === 'newkangis' ? 'flex-1 px-3 py-2 text-sm font-medium rounded-md bg-white text-blue-600 shadow-sm' : 'flex-1 px-3 py-2 text-sm font-medium rounded-md text-gray-500 hover:text-gray-700'">
      New KANGIS
    </button>
  </div>

  <!-- MLS Tab Content -->
  <div x-show="tab === 'mls'" class="tab-content-panel">
    <p class="text-sm text-gray-600 mb-2">MLS File Number</p>
    
    <!-- File type selection dropdown -->
    <div class="mb-2">
      <label class="block text-sm mb-1 text-gray-700">Type</label>
      <div class="relative">
        <select x-model="mlsType" @change="if(mlsType==='extension') loadExistingMls()" class="w-full p-2 text-sm border border-gray-300 rounded appearance-none pr-8 bg-white focus:ring-1 focus:ring-blue-400 focus:border-blue-400">
          <option value="regular">Regular</option>
          <option value="temporary">Temporary</option>
          <option value="extension">Extension</option>
          <option value="miscellaneous">Miscellaneous</option>
          <option value="old_mls">Old MLS</option>
          <option value="sltr">SLTR</option>
          <option value="sit">SIT</option>
        </select>
        <div class="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none">
          <i data-lucide="chevron-down" class="w-4 h-4 text-gray-400"></i>
        </div>
      </div>
    </div>

    <!-- Regular/Temporary -->
    <div x-show="mlsType === 'regular' || mlsType === 'temporary'" class="grid grid-cols-3 gap-4 mb-4">
      <div>
        <label class="block text-sm mb-1">File Prefix</label>
        <div class="relative">
          <select x-model="mlsPrefix" class="w-full p-2 border border-gray-300 rounded-md appearance-none pr-8">
            <option value="">Select prefix</option>
            <optgroup label="Standard">
              <option value="RES">RES - Residential</option>
              <option value="COM">COM - Commercial</option>
              <option value="IND">IND - Industrial</option>
              <option value="AGR">AGR - Agricultural</option>
              <option value="INS">INS - Institutional</option>
            </optgroup>
            <optgroup label="Conversion">
              <option value="CON-RES">CON-RES - Conversion to Residential</option>
              <option value="CON-COM">CON-COM - Conversion to Commercial</option>
              <option value="CON-IND">CON-IND - Conversion to Industrial</option>
              <option value="CON-AGR">CON-AGR - Conversion to Agricultural</option>
              <option value="CON-INS">CON-INS - Conversion to Institutional</option>
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
          <div class="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none">
            <i data-lucide="chevron-down" class="w-4 h-4 text-gray-400"></i>
          </div>
        </div>
      </div>
      <div>
        <label class="block text-sm mb-1">Year</label>
        <input type="text" x-model="mlsYear" class="w-full p-2 border border-gray-300 rounded-md" placeholder="e.g. 2025" maxlength="4">
      </div>
      <div>
        <label class="block text-sm mb-1">Serial No</label>
        <input type="text" x-model="mlsSerial" class="w-full p-2 border border-gray-300 rounded-md" placeholder="e.g. 0572">
      </div>
    </div>

    <!-- Extension -->
    <div x-show="mlsType === 'extension'" class="mb-4">
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm mb-1">Year</label>
          <input type="text" x-model="mlsYear" class="w-full p-2 border border-gray-300 rounded-md" placeholder="e.g. 2025" maxlength="4">
        </div>
        <div>
          <label class="block text-sm mb-1">Select Existing MLS File Number</label>
          <select x-model="mlsExistingSelected" @focus="loadExistingMls()" @click="loadExistingMls()" class="w-full p-2 border border-gray-300 rounded-md">
            <option value="">Select existing file number...</option>
            <template x-for="opt in mlsExistingOptions" :key="opt">
              <option :value="opt" x-text="opt"></option>
            </template>
          </select>
        </div>
      </div>
      <p class="text-xs text-gray-500 mt-1">Serial not required for extensions.</p>
    </div>

    <!-- Miscellaneous -->
    <div x-show="mlsType === 'miscellaneous'" class="mb-4">
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm mb-1">Middle Prefix</label>
          <input type="text" x-model="mlsMiddlePrefix" class="w-full p-2 border border-gray-300 rounded-md" placeholder="e.g. KN">
        </div>
        <div>
          <label class="block text-sm mb-1">Serial No</label>
          <input type="text" x-model="mlsMiscSerial" class="w-full p-2 border border-gray-300 rounded-md" placeholder="Enter custom serial (e.g., 001, ABC123)">
        </div>
      </div>
    </div>

    <!-- SLTR / SIT -->
    <div x-show="mlsType === 'sltr' || mlsType === 'sit'" class="mb-4">
      <div class="grid grid-cols-2 gap-4">
        <div x-show="mlsType === 'sit'">
          <label class="block text-sm mb-1">Year</label>
          <input type="text" x-model="mlsYear" class="w-full p-2 border border-gray-300 rounded-md" placeholder="e.g. 2025" maxlength="4">
        </div>
        <div>
          <label class="block text-sm mb-1">Serial No</label>
          <input type="text" x-model="mlsSpecialSerial" class="w-full p-2 border border-gray-300 rounded-md" :placeholder="mlsType === 'sltr' ? 'Enter SLTR serial (e.g., 001)' : 'Enter SIT serial (e.g., 001)'">
        </div>
      </div>
    </div>

    <!-- Old MLS -->
    <div x-show="mlsType === 'old_mls'" class="mb-4">
      <div>
        <label class="block text-sm mb-1">Old MLS Number</label>
        <input type="text" x-model="mlsOldSerial" class="w-full p-2 border border-gray-300 rounded-md" placeholder="e.g. 5467">
      </div>
    </div>

    <!-- Enhanced Full File Number Display -->
    <div class="mb-3">
      <label class="block text-xs mb-1 text-gray-700 font-medium">Generated File Number</label>
      <div class="relative w-1/2">
        <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-lg p-2 flex items-center justify-between">
          <div class="text-sm font-bold text-blue-900 tracking-wide" x-text="mlsPreview() || 'Enter details above to see preview'"></div>
          <div class="flex items-center space-x-1">
            <i data-lucide="file-text" class="w-4 h-4 text-blue-600"></i>
            <span class="text-xs text-blue-600 font-medium">MLS</span>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- KANGIS Tab Content -->
  <div x-show="tab === 'kangis'" class="tab-content-panel">
    <p class="text-sm text-gray-600 mb-3">KANGIS File Number</p>
    <div class="grid grid-cols-3 gap-4">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">File Prefix</label>
        <select x-model="kangisPrefix" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
          <option value="">Select Prefix</option>
          <option>KNML</option>
          <option>MNKL</option>
          <option>MLKN</option>
          <option>KNGP</option>
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Serial Number</label>
        <input type="text" x-model="kangisNumber" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" placeholder="e.g. 0001 or 2500">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Full FileNo</label>
        <input type="text" :value="kangisPreview()" readonly class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm bg-gray-50">
      </div>
    </div>
  </div>

  <!-- New KANGIS Tab Content -->
  <div x-show="tab === 'newkangis'" class="tab-content-panel">
    <p class="text-sm text-gray-600 mb-3">New KANGIS File Number</p>
    <div class="grid grid-cols-3 gap-4">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">File Prefix</label>
        <select x-model="newkangisPrefix" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
          <option value="">Select Prefix</option>
          <option>KN</option>
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Serial Number</label>
        <input type="text" x-model="newkangisNumber" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" placeholder="e.g. 1586">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Full FileNo</label>
        <input type="text" :value="newkangisPreview()" readonly class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm bg-gray-50">
      </div>
    </div>
  </div>
</div>