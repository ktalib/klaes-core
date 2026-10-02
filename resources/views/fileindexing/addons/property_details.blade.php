<div class="form-section">
    <div class="flex items-center gap-2 mb-4">
        <i data-lucide="map-pin" class="h-5 w-5 text-emerald-600"></i>
        <h3 class="form-section-title" style="margin-bottom: 0;">Property Details</h3>
    </div>

    <hr class="mb-6 border-t border-gray-200">

    <div class="grid grid-cols-2 gap-4">
        <div class="form-group">
            <label for="land-use-type" class="block text-sm font-medium text-gray-700 mb-2">Land Use Type <span class="text-red-500">*</span></label>
            <select class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="land-use-type" name="land_use_type" required aria-required="true">
                <option value="">Select Land Use Type</option>
                <option value="AGRICULTURAL">AGRICULTURAL</option>
                <option value="COMMERCIAL">COMMERCIAL</option>
                <option value="COMMERCIAL ( WARE HOUSE)">COMMERCIAL ( WARE HOUSE)</option>
                <option value="COMMERCIAL (OFFICES)">COMMERCIAL (OFFICES)</option>
                <option value="COMMERCIAL (PETROL FILLING STATION)">COMMERCIAL (PETROL FILLING STATION)</option>
                <option value="COMMERCIAL (RICE PROCESSING)">COMMERCIAL (RICE PROCESSING)</option>
                <option value="COMMERCIAL (SCHOOL)">COMMERCIAL (SCHOOL)</option>
                <option value="COMMERCIAL (SHOPS &amp; PUBLIC CONVINIENCE)">COMMERCIAL (SHOPS &amp; PUBLIC CONVINIENCE)</option>
                <option value="COMMERCIAL (SHOPS AND OFFICES)">COMMERCIAL (SHOPS AND OFFICES)</option>
                <option value="COMMERCIAL (SHOPS)">COMMERCIAL (SHOPS)</option>
                <option value="COMMERCIAL (WAREHOUSE)">COMMERCIAL (WAREHOUSE)</option>
                <option value="COMMERCIAL (WORKSHOP AND OFFICES)">COMMERCIAL (WORKSHOP AND OFFICES)</option>
                <option value="COMMERCIAL AND RESIDENTIAL">COMMERCIAL AND RESIDENTIAL</option>
                <option value="INDUSTRIAL">INDUSTRIAL</option>
                <option value="INDUSTRIAL (SMALL SCALE)">INDUSTRIAL (SMALL SCALE)</option>
                <option value="RESIDENTIAL">RESIDENTIAL</option>
                <option value="RESIDENTIAL AND COMMERCIAL">RESIDENTIAL AND COMMERCIAL</option>
                <option value="RESIDENTIAL/COMMERCIAL">RESIDENTIAL/COMMERCIAL</option>
                <option value="RESIDENTIAL/COMMERCIAL LAYOUT">RESIDENTIAL/COMMERCIAL LAYOUT</option>
            </select>
        </div>
        <div class="form-group">
            <label class="block text-sm font-medium text-gray-700 mb-2">Plot Number</label>
            <input type="text" id="plot-number" class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
        </div>
    </div>

    <div class="grid grid-cols-2 gap-4">
        <div class="form-group">
            <label class="block text-sm font-medium text-gray-700 mb-2">TP Number</label>
            <input type="text" id="tp-number" class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
        </div>
        <div class="form-group">
            <label class="block text-sm font-medium text-gray-700 mb-2">LPKN Number</label>
            <input type="text" id="lpkn-no" class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
        </div>
    </div>

    <div class="grid grid-cols-2 gap-4">
        <div class="form-group">
            <label class="block text-sm font-medium text-gray-700 mb-2">District</label>
            <select class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="district-select" style="text-transform: uppercase;">
                <option value="">Loading districts...</option>
            </select>
            <div id="custom-district-container" class="hidden" style="margin-top: 0.5rem;">
                <input type="text" id="custom-district-input" class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" placeholder="Enter district name" style="text-transform: uppercase;">
            </div>
        </div>
        <div class="form-group">
            <label class="block text-sm font-medium text-gray-700 mb-2">LGA</label>
            <select id="lga-city" class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" style="text-transform: uppercase;">
                <option value="">Loading LGAs...</option>
            </select>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-4">
        <div class="form-group">
            <label class="block text-sm font-medium text-gray-700 mb-2">Location</label>
            <input type="text" id="location" class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm bg-gray-50" readonly placeholder="Auto-generated from Plot Number, District, LGA" style="cursor: default; text-transform: uppercase;">
        </div>
        <div class="form-group">
            <label class="block text-sm font-medium text-gray-700 mb-2">Plot Size</label>
            <input type="text" id="plot-size" name="plot_size" class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" style="text-transform: uppercase;">
        </div>
    </div>
</div>

