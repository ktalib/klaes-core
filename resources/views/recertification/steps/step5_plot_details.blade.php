<!-- Step 1: Plot Details (the land file is the starting point) -->
<div id="step-content-1" class="step-content">
    <div class="bg-white border border-gray-200 rounded-lg">
        <div class="p-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold flex items-center gap-2">
                <i data-lucide="building" class="h-5 w-5"></i>
                FILE DETAILS
            </h3>
        </div>
        <div class="p-4 space-y-4">
            {{-- Land file: the starting point. A recertification needs an existing land file;
                 its details are backfilled and locked, the clerk confirms the rest. --}}
            <div class="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-lg p-4 border border-blue-200">
                <h4 class="text-sm font-semibold text-blue-900 mb-3 flex items-center gap-2">
                    <i data-lucide="folder-search" class="h-4 w-4"></i>
                    Land File
                </h4>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                    <div class="form-field">
                        <label for="landFileDisplay" class="block text-sm font-medium text-gray-700 mb-1">
                            Land File No (MLPP) <span class="text-red-500">*</span>
                        </label>
                        <div class="flex gap-2">
                            <input
                                type="text"
                                id="landFileDisplay"
                                disabled
                                class="flex-1 px-3 py-2 border border-gray-300 rounded-md text-sm bg-gray-100 text-gray-700 cursor-not-allowed font-mono font-bold"
                                placeholder="Select the land file"
                            />
                            <input type="hidden" id="landFileNo" name="landFileNo" value="" required />
                            <button
                                type="button"
                                id="landFilePickBtn"
                                class="inline-flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium bg-blue-600 text-white hover:bg-blue-700 whitespace-nowrap"
                            >
                                <i data-lucide="search" class="h-4 w-4"></i>
                                <span>Select Land File</span>
                            </button>
                        </div>
                        <div class="text-xs text-gray-500 mt-1 flex items-center gap-1">
                            <i data-lucide="info" class="h-3 w-3"></i>
                            Pick from the global file number selector. Details already on record are filled in and locked.
                        </div>
                        <div class="error-message">Select the land file first</div>
                    </div>

                    <div class="form-field">
                        <label for="kangisFileDisplay" class="block text-sm font-medium text-gray-700 mb-1">
                            KANGIS File No <span class="text-gray-400">(if the land has one)</span>
                        </label>
                        <div class="flex gap-2">
                            <input
                                type="text"
                                id="kangisFileDisplay"
                                disabled
                                class="flex-1 px-3 py-2 border border-gray-300 rounded-md text-sm bg-gray-100 text-gray-700 cursor-not-allowed font-mono font-bold"
                                placeholder="None selected"
                            />
                            <input type="hidden" id="kangisFileNo" name="kangisFileNo" value="" />
                            <button
                                type="button"
                                id="kangisFilePickBtn"
                                disabled
                                class="inline-flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium bg-green-600 text-white hover:bg-green-700 whitespace-nowrap disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                <i data-lucide="search" class="h-4 w-4"></i>
                                <span>Select</span>
                            </button>
                            <button
                                type="button"
                                id="kangisFileClearBtn"
                                class="hidden inline-flex items-center rounded-md px-2 py-2 text-sm border border-gray-300 text-gray-600 hover:bg-gray-50"
                                aria-label="Clear KANGIS File No"
                            >
                                <i data-lucide="x" class="h-4 w-4"></i>
                            </button>
                        </div>
                        <div id="kangisFileHint" class="text-xs text-gray-500 mt-1">
                            Select the land file first. A KANGIS number already on record is filled in and locked.
                        </div>
                    </div>

                    <div class="form-field">
                        <label class="block text-sm font-medium text-gray-700 mb-1">New KANGIS File No</label>
                        <input type="hidden" id="newKangisFileNo" name="newKangisFileNo" value="" />
                        <div class="flex items-center gap-2 px-3 py-2 border border-blue-200 rounded-md bg-white">
                            <i data-lucide="file-text" class="h-4 w-4 text-blue-600"></i>
                            <span id="kangisFileNoDisplay" class="text-sm font-bold text-blue-900 font-mono">-</span>
                            <span id="kangisFileNoBadge" class="hidden ml-auto text-xs px-2 py-0.5 rounded-full"></span>
                        </div>
                        <div id="kangisFileNoHint" class="text-xs text-gray-500 mt-1">
                            The land file's New KANGIS (KN) number is used when it has one; otherwise a new KN number is assigned on submit.
                        </div>
                    </div>
                </div>

                <div id="landFileStatus" class="hidden mt-3 text-sm"></div>

                <div id="landFileCard" class="hidden mt-3 bg-white border border-gray-200 border-l-4 border-l-green-600 rounded-md p-3">
                    <div class="grid grid-cols-1 md:grid-cols-5 gap-3 text-sm">
                        <div><div class="text-xs text-gray-500 uppercase">Holder on record</div><div id="landFileHolder" class="font-semibold text-gray-900">-</div></div>
                        <div><div class="text-xs text-gray-500 uppercase">Plot No.</div><div id="landFilePlot" class="text-gray-900">-</div></div>
                        <div><div class="text-xs text-gray-500 uppercase">District</div><div id="landFileDistrict" class="text-gray-900">-</div></div>
                        <div><div class="text-xs text-gray-500 uppercase">LGA</div><div id="landFileLga" class="text-gray-900">-</div></div>
                        <div><div class="text-xs text-gray-500 uppercase">Location</div><div id="landFileLocation" class="text-gray-900">-</div></div>
                    </div>
                </div>
            </div>

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
                <label for="currentLandUse" class="block text-sm font-medium text-gray-700 mb-1">
                    Current Land Use <span class="text-red-500">*</span>
                </label>
                <select
                    id="currentLandUse"
                    name="currentLandUse"
                    required
                    class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm transition-all focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10"
                >
                    <option value="">Select land use</option>
                    <option value="residential">Residential</option>
                    <option value="commercial">Commercial</option>
                    <option value="industrial">Industrial</option>
                    <option value="agricultural">Agricultural</option>
                </select>
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