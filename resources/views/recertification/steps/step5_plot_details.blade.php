<!-- Step 5: Plot Details -->
<div id="step-content-5" class="step-content hidden">
    <div class="bg-white border border-gray-200 rounded-lg">
        <div class="p-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold flex items-center gap-2">
                <i data-lucide="building" class="h-5 w-5"></i>
                SECTION C: PLOT DETAILS
            </h3>
        </div>
        <div class="p-4 space-y-4">
            <div class="grid grid-cols-2 gap-4">
            <div class="form-field">
                <label for="plotNumber" class="block text-sm font-medium text-gray-700 mb-1">
                    Plot Number or Piece of Land <span class="text-red-500">*</span>
                </label>
                <input
                    type="text"
                    id="plotNumber"
                    name="plotNumber"
                    required
                    class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm transition-all focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10 uppercase"
                    placeholder="PLOT NUMBER"
                />
                <div class="error-message">Plot number is required</div>
            </div>

            <div class="form-field hidden">
                <label for="fileNumber" class="block text-sm font-medium text-gray-700 mb-1">
                    File Number <span class="text-red-500">*</span>
                </label>
                <input
                    type="text"
                    id="fileNumber"
                    name="fileNumber"
                    readonly
                    class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm transition-all bg-gray-100 text-gray-600 cursor-not-allowed uppercase"
                    placeholder="Generating..."
                    value=""
                />
                <div class="text-xs text-gray-500 mt-1">Auto-generated unique file number</div>
                <div class="error-message">File number is required</div>
            </div>
            </div>

            <div class="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-lg p-4 border border-blue-200">
                <h4 class="text-sm font-semibold text-blue-900 mb-3 flex items-center gap-2">
                    <i data-lucide="file-text" class="h-4 w-4"></i>
                    File Numbers
                </h4>
                
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                    <div class="form-field hidden">
                        <label for="newKangisFileNo" class="block text-sm font-medium text-gray-700 mb-1">
                            New KANGIS File No <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="text"
                            id="newKangisFileNo"
                            name="newKangisFileNo"
                            required
                            readonly
                            class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm transition-all bg-blue-50 text-blue-900 cursor-not-allowed uppercase font-mono font-bold"
                            placeholder="Generating..."
                            value=""
                        />
                        <div class="text-xs text-blue-600 mt-1 flex items-center gap-1">
                            <i data-lucide="check-circle" class="h-3 w-3"></i>
                            Auto-generated new KANGIS file number
                        </div>
                        <div class="error-message">New KANGIS file number is required</div>
                    </div>

                    @php
                    $fileNumbers = DB::connection('sqlsrv')->table('fileNumber')
                        ->select('kangisFileNo', 'mlsfNo')
                        ->where(function($query) {
                            $query->whereNull('is_deleted')
                                  ->orWhere('is_deleted', 0);
                        })
                        ->where('is_decommissioned', 0)
                        ->get();
                    
                    $mlsFileNumbers = $fileNumbers->whereNotNull('mlsfNo')->pluck('mlsfNo')->unique()->sort()->values();
                    $kangisFileNumbers = $fileNumbers->whereNotNull('kangisFileNo')->pluck('kangisFileNo')->unique()->sort()->values();
                    @endphp

                    <div class="form-field">
                        <label for="mlsFileNo" class="block text-sm font-medium text-gray-700 mb-1">
                            MLPP File No <span class="text-red-500">*</span>
                        </label>
                        <div class="space-y-2">
                            <button
                                type="button"
                                onclick="toggleManualInput('mlsFileNo')"
                                class="text-xs bg-green-500 hover:bg-green-500 px-2 py-1 rounded border text-gray-700 transition-colors"
                            >
                                Enter Manually
                            </button>
                            <div id="mlsFileNoSelect">
                                <select
                                    id="mlsFileNo"
                                    name="mlsFileNo"
                                    required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm transition-all focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10 uppercase file-number-select"
                                >
                                    <option value="">Search and select MLPP File Number</option>
                                    @foreach($mlsFileNumbers as $fileNo)
                                        <option value="{{ $fileNo }}">{{ $fileNo }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div id="mlsFileNoManual" class="hidden">
                                <input
                                    type="text"
                                    name="mlsFileNoManual"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm transition-all focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10 uppercase"
                                    placeholder="ENTER MLPP FILE NUMBER MANUALLY"
                                />
                                <button
                                    type="button"
                                    onclick="toggleManualInput('mlsFileNo')"
                                    class="text-xs text-blue-600 hover:text-blue-800 mt-1"
                                >
                                    ← Back to search
                                </button>
                            </div>
                        </div>
                        <div class="text-xs text-gray-500 mt-1 flex items-center gap-1">
                            <i data-lucide="info" class="h-3 w-3"></i>
                            Search from list or enter manually
                        </div>
                        <div class="error-message">MLPP file number is required</div>
                    </div>

                    <div class="form-field">
                        <label for="kangisFileNo" class="block text-sm font-medium text-gray-700 mb-1">
                            KANGIS File No <span class="text-gray-400"></span>
                        </label>
                        <div class="space-y-2">
                            <button
                                type="button"
                                onclick="toggleManualInput('kangisFileNo')"
                                class="text-xs bg-green-500 hover:bg-gray-200 px-2 py-1 rounded border text-gray-700 transition-colors"
                            >
                                Enter Manually
                            </button>
                            <div id="kangisFileNoSelect">
                                <select
                                    id="kangisFileNo"
                                    name="kangisFileNo"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm transition-all focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10 uppercase file-number-select"
                                >
                                    <option value="">Search and select KANGIS File Number</option>
                                    @foreach($kangisFileNumbers as $fileNo)
                                        <option value="{{ $fileNo }}">{{ $fileNo }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div id="kangisFileNoManual" class="hidden">
                                <input
                                    type="text"
                                    name="kangisFileNoManual"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm transition-all focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10 uppercase"
                                    placeholder="ENTER KANGIS FILE NUMBER MANUALLY"
                                />
                                <button
                                    type="button"
                                    onclick="toggleManualInput('kangisFileNo')"
                                    class="text-xs text-blue-600 hover:text-blue-800 mt-1"
                                >
                                    ← Back to search
                                </button>
                            </div>
                        </div>
                        <div class="text-xs text-gray-500 mt-1 flex items-center gap-1">
                            <i data-lucide="file" class="h-3 w-3"></i>
                            Only if available
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="form-field">
                    <label for="plotSize" class="block text-sm font-medium text-gray-700 mb-1">
                        Plot Size (Ha) <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                        <input
                            type="number"
                            id="plotSize"
                            name="plotSize"
                            required
                            step="0.01"
                            min="0"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm transition-all focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10"
                            placeholder="0.00"
                        />
                        <span class="absolute right-3 top-2.5 text-xs text-gray-500">Hectares</span>
                    </div>
                    <div class="text-xs text-gray-500 mt-1">Enter plot size in hectares</div>
                    <div class="error-message">Plot size is required</div>
                </div>
                
                <div class="form-field" >
                    <label for="coordinates" class="block text-sm font-medium text-gray-700 mb-1">
                        GPS Coordinates <span class="text-gray-400">(Optional)</span>
                    </label>
                    <input
                        type="text"
                        id="coordinates"
                        name="coordinates"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm transition-all focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10"
                        placeholder="e.g., 12.0000, 8.5000"
                    />
                    <div class="text-xs text-gray-500 mt-1">Latitude, Longitude format</div>
                </div>
            </div>

            <script>
            // Initialize Select2 when document is ready
            $(document).ready(function() {
                // Initialize Select2 for file number selects
                $('.file-number-select').select2({
                    placeholder: function() {
                        return $(this).attr('placeholder') || 'Search and select...';
                    },
                    allowClear: true,
                    width: '100%',
                    templateResult: function(option) {
                        if (!option.id) return option.text;
                        return $('<span style="text-transform: uppercase;">' + option.text + '</span>');
                    },
                    templateSelection: function(option) {
                        return $('<span style="text-transform: uppercase;">' + option.text + '</span>');
                    }
                });
            });

            function toggleManualInput(fieldName) {
                const selectDiv = document.getElementById(fieldName + 'Select');
                const manualDiv = document.getElementById(fieldName + 'Manual');
                const selectElement = document.getElementById(fieldName);
                const manualInput = manualDiv.querySelector('input');
                
                if (selectDiv.classList.contains('hidden')) {
                    // Show select, hide manual
                    selectDiv.classList.remove('hidden');
                    manualDiv.classList.add('hidden');
                    selectElement.setAttribute('name', fieldName);
                    manualInput.removeAttribute('name');
                    manualInput.value = '';
                    // Reinitialize Select2
                    $('#' + fieldName).select2({
                        placeholder: 'Search and select...',
                        allowClear: true,
                        width: '100%'
                    });
                } else {
                    // Hide select, show manual
                    selectDiv.classList.add('hidden');
                    manualDiv.classList.remove('hidden');
                    selectElement.removeAttribute('name');
                    manualInput.setAttribute('name', fieldName);
                    // Destroy Select2 instance
                    $('#' + fieldName).select2('destroy');
                    manualInput.focus();
                }
            }
            </script>
         
            
          
            
            <div class="grid grid-cols-2 gap-4">
                <div class="form-field">
                    <label for="layoutDistrict" class="block text-sm font-medium text-gray-700 mb-1">
                        Layout/District <span class="text-red-500">*</span>
                    </label>
                    @include('components.District3')
                    <div class="error-message">Layout/District is required</div>
                </div>
                
                <div class="form-field">
                    <label for="lga" class="block text-sm font-medium text-gray-700 mb-1">
                        LGA <span class="text-red-500">*</span>
                    </label>
                    <select
                        id="lga"
                        name="lga"
                        required
                        class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm transition-all focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10"
                    >
                        <option value="">Select LGA</option>
                        <option value="Kano Municipal">Kano Municipal</option>
                        <option value="Fagge">Fagge</option>
                        <option value="Gwale">Gwale</option>
                        <option value="Dala">Dala</option>
                        <option value="Tarauni">Tarauni</option>
                        <option value="Nassarawa">Nassarawa</option>
                        <option value="Kumbotso">Kumbotso</option>
                        <option value="Ungogo">Ungogo</option>
                        <option value="Warawa">Warawa</option>
                        <option value="Dawakin Kudu">Dawakin Kudu</option>
                        <option value="Dawakin Tofa">Dawakin Tofa</option>
                        <option value="Rimin Gado">Rimin Gado</option>
                        <option value="Tofa">Tofa</option>
                        <option value="Tsanyawa">Tsanyawa</option>
                        <option value="Kunchi">Kunchi</option>
                        <option value="Kibiya">Kibiya</option>
                        <option value="Rano">Rano</option>
                        <option value="Bunkure">Bunkure</option>
                        <option value="Karaye">Karaye</option>
                        <option value="Rogo">Rogo</option>
                        <option value="Gwarzo">Gwarzo</option>
                        <option value="Kabo">Kabo</option>
                        <option value="Madobi">Madobi</option>
                        <option value="Wudil">Wudil</option>
                        <option value="Garko">Garko</option>
                        <option value="Gabasawa">Gabasawa</option>
                        <option value="Gezawa">Gezawa</option>
                        <option value="Garun Mallam">Garun Mallam</option>
                        <option value="Bagwai">Bagwai</option>
                        <option value="Shanono">Shanono</option>
                        <option value="Gaya">Gaya</option>
                        <option value="Ajingi">Ajingi</option>
                        <option value="Albasu">Albasu</option>
                        <option value="Gaya">Gaya</option>
                        <option value="Kura">Kura</option>
                        <option value="Minjibir">Minjibir</option>
                        <option value="Dambatta">Dambatta</option>
                        <option value="Makoda">Makoda</option>
                        <option value="Tudun Wada">Tudun Wada</option>
                        <option value="Doguwa">Doguwa</option>
                        <option value="Takai">Takai</option>
                        <option value="Sumaila">Sumaila</option>
                        <option value="Kiru">Kiru</option>
                        <option value="Bebeji">Bebeji</option>
                        <option value="Bichi">Bichi</option>
                    </select>
                    <div class="error-message">LGA is required</div>
                </div>
            </div>
            
            <div class="form-field">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Current Land Use <span class="text-red-500">*</span>
                </label>
                <div class="grid grid-cols-4 gap-2">
                    <label class="radio-item">
                        <input type="radio" name="currentLandUse" value="residential" required />
                        <div class="radio-circle"></div>
                        <span class="text-xs">Residential</span>
                    </label>
                    <label class="radio-item">
                        <input type="radio" name="currentLandUse" value="commercial" />
                        <div class="radio-circle"></div>
                        <span class="text-xs">Commercial</span>
                    </label>
                    <label class="radio-item">
                        <input type="radio" name="currentLandUse" value="industrial" />
                        <div class="radio-circle"></div>
                        <span class="text-xs">Industrial</span>
                    </label>
                    <label class="radio-item">
                        <input type="radio" name="currentLandUse" value="agricultural" />
                        <div class="radio-circle"></div>
                        <span class="text-xs">Agricultural</span>
                    </label>
                    <label class="radio-item">
                        <input type="radio" name="currentLandUse" value="educational" />
                        <div class="radio-circle"></div>
                        <span class="text-xs">Educational</span>
                    </label>
                    <label class="radio-item">
                        <input type="radio" name="currentLandUse" value="religious" />
                        <div class="radio-circle"></div>
                        <span class="text-xs">Religious</span>
                    </label>
                    <label class="radio-item">
                        <input type="radio" name="currentLandUse" value="public" />
                        <div class="radio-circle"></div>
                        <span class="text-xs">Public</span>
                    </label>
                    <label class="radio-item">
                        <input type="radio" name="currentLandUse" value="ngo" />
                        <div class="radio-circle"></div>
                        <span class="text-xs">NGO</span>
                    </label>
                    <label class="radio-item">
                        <input type="radio" name="currentLandUse" value="social" />
                        <div class="radio-circle"></div>
                        <span class="text-xs">Social (Hospital, etc)</span>
                    </label>
                    <label class="radio-item">
                        <input type="radio" name="currentLandUse" value="petrol-station" />
                        <div class="radio-circle"></div>
                        <span class="text-xs">Petrol Filling Station</span>
                    </label>
                    <label class="radio-item">
                        <input type="radio" name="currentLandUse" value="gkn" />
                        <div class="radio-circle"></div>
                        <span class="text-xs">GKN</span>
                    </label>
                    <label class="radio-item">
                        <input type="radio" name="currentLandUse" value="mixed-use" />
                        <div class="radio-circle"></div>
                        <span class="text-xs">Mixed Use</span>
                    </label>
                </div>
                <div class="error-message">Current land use is required</div>
            </div>
            
            <div class="grid grid-cols-2 gap-4">
                <div class="form-field">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Plot Status <span class="text-red-500">*</span>
                    </label>
                    <div class="flex gap-4">
                        <label class="radio-item">
                            <input type="radio" name="plotStatus" value="developed" required />
                            <div class="radio-circle"></div>
                            <span class="text-sm">Developed</span>
                        </label>
                        <label class="radio-item">
                            <input type="radio" name="plotStatus" value="undeveloped" />
                            <div class="radio-circle"></div>
                            <span class="text-sm">Undeveloped</span>
                        </label>
                        <label class="radio-item">
                            <input type="radio" name="plotStatus" value="partially-developed" />
                            <div class="radio-circle"></div>
                            <span class="text-sm">Partially Developed</span>
                        </label>
                    </div>
                    <div class="error-message">Plot status is required</div>
                </div>
                
                <div class="form-field">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Mode of Allocation <span class="text-red-500">*</span>
                    </label>
                    <div class="flex gap-4">
                        <label class="radio-item">
                            <input type="radio" name="modeOfAllocation" value="direct-allocation" required />
                            <div class="radio-circle"></div>
                            <span class="text-sm">Direct Allocation</span>
                        </label>
                        <label class="radio-item">
                            <input type="radio" name="modeOfAllocation" value="resettlement" />
                            <div class="radio-circle"></div>
                            <span class="text-sm">Resettlement</span>
                        </label>
                        <label class="radio-item">
                            <input type="radio" name="modeOfAllocation" value="regularization" />
                            <div class="radio-circle"></div>
                            <span class="text-sm">Regularization</span>
                        </label>
                    </div>
                    <div class="error-message">Mode of allocation is required</div>
                </div>
            </div>
            
            <div class="grid grid-cols-2 gap-4">
                <div class="form-field">
                    <label for="startDate" class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                    <input
                        type="date"
                        id="startDate"
                        name="startDate"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm transition-all focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10"
                    />
                </div>
                
                <div class="form-field">
                    <label for="expiryDate" class="block text-sm font-medium text-gray-700 mb-1">Expiry Date</label>
                    <input
                        type="date"
                        id="expiryDate"
                        name="expiryDate"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm transition-all focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10"
                    />
                </div>
            </div>
            
            <div class="form-field">
                <label for="plotDescription" class="block text-sm font-medium text-gray-700 mb-1">Plot Description (Optional)</label>
                <textarea
                    id="plotDescription"
                    name="plotDescription"
                    rows="3"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm transition-all focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10"
                    placeholder="Brief description of the plot location, boundaries, or any special features..."
                ></textarea>
            </div>
        </div>
    </div>
</div>