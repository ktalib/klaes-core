<!-- File Summary Header -->
<div id="file-summary-container" class="hidden mb-6 overflow-hidden rounded-lg border border-green-300 bg-gradient-to-r from-green-50 to-teal-50 shadow-md">
    <div class="px-6 py-4 bg-green-100 border-b border-green-300">
        <h3 class="text-lg font-semibold text-green-800 flex items-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <span id="summary-title" class="truncate">File Summary</span>
        </h3>
    </div>
    
    <div class="p-6">
        <!-- Search GIS Primary File Number and Mother Application Summary -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6 text-sm">
            <div id="search-primary-file-summary" class="p-4 bg-white rounded-lg shadow-sm border border-gray-200">
                <h4 class="text-md font-medium text-gray-700 mb-3">Primary GIS File Number</h4>
                <div class="flex flex-col">
                    <span class="text-gray-500">File Number</span>
                    <span id="summary-primaryFileNo" class="font-medium text-gray-900">-</span>
                </div>
            </div>

            <div id="mother-app-summary" class="hidden p-4 bg-white rounded-lg shadow-sm border border-gray-200">
                <h4 class="text-md font-medium text-gray-700 mb-3">Mother File Number</h4>
                <div class="flex flex-col">
                    <span class="text-gray-500">File Number</span>
                    <span id="summary-motherAppFileNo" class="font-medium text-gray-900">-</span>
                </div>
            </div>
        </div>

        <!-- Unit File Information -->
        <div id="unit-file-summary" class="hidden">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 text-sm">
                <div class="p-4 bg-white rounded-lg shadow-sm border border-gray-200">
                    <span class="text-gray-500">Unit File Number</span>
                    <span id="summary-unitFileNo" class="font-medium text-gray-900">-</span>
                </div>
                <div class="p-4 bg-white rounded-lg shadow-sm border border-gray-200">
                    <span class="text-gray-500">Scheme Number</span>
                    <span id="summary-schemeNo" class="font-medium text-gray-900">-</span>
                </div>
                <div class="p-4 bg-white rounded-lg shadow-sm border border-gray-200">
                    <span class="text-gray-500">Section Number</span>
                    <span id="summary-sectionNo" class="font-medium text-gray-900">-</span>
                </div>
                <div class="p-4 bg-white rounded-lg shadow-sm border border-gray-200">
                    <span class="text-gray-500">Block Number</span>
                    <span id="summary-blockNo" class="font-medium text-gray-900">-</span>
                </div>
                <div class="p-4 bg-white rounded-lg shadow-sm border border-gray-200">
                    <span class="text-gray-500">Unit Number</span>
                    <span id="summary-unitNo" class="font-medium text-gray-900">-</span>
                </div>
                <div class="p-4 bg-white rounded-lg shadow-sm border border-gray-200">
                    <span class="text-gray-500">Land Use</span>
                    <span id="summary-unitLandUse" class="font-medium text-gray-900">-</span>
                </div>
            </div>
        </div>

        <!-- Info text at bottom -->
        <div class="mt-4 text-xs text-gray-500 italic">
            <p id="summary-note">File details will be used to populate the form below.</p>
        </div>
    </div>
</div>
