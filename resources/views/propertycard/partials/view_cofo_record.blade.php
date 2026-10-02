<div id="cofo-view-dialog" class="dialog-overlay hidden">
    <div class="dialog-content property-form-content max-w-3xl">
        <div class="flex justify-between items-start mb-4">
            <div>
                <h2 class="text-xl font-bold text-green-800">Certificate of Occupancy Details</h2>
                <p id="cofo-view-subtitle" class="text-sm text-gray-500">Loading record...</p>
            </div>
            <button id="close-cofo-view" class="text-gray-500 hover:text-gray-700" type="button" aria-label="Close CofO view">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>

        <div id="cofo-view-body" class="space-y-6 max-h-[75vh] overflow-y-auto">
            <div class="bg-green-50 border border-green-100 p-4 rounded-lg">
                <h3 class="text-lg font-semibold text-green-900 mb-2">Record Summary</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <p class="text-sm text-gray-500">CofO Type</p>
                        <p id="cofo-view-type" class="font-medium text-green-800">Loading...</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Primary File Number</p>
                        <p id="cofo-view-file-number" class="font-medium">Loading...</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">MLPP File Number</p>
                        <p id="cofo-view-mls" class="font-medium">Loading...</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">KANGIS File Number</p>
                        <p id="cofo-view-kangis" class="font-medium">Loading...</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">New KANGIS File Number</p>
                        <p id="cofo-view-new-kangis" class="font-medium">Loading...</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Date Captured</p>
                        <p id="cofo-view-created-at" class="font-medium">Loading...</p>
                    </div>
                </div>
            </div>

            <div class="bg-white border p-4 rounded-lg">
                <h3 class="text-lg font-semibold mb-3">Property Details</h3>
                <div class="space-y-4">
                    <div>
                        <p class="text-sm text-gray-500">Description</p>
                        <p id="cofo-view-description" class="mt-1">Loading...</p>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm text-gray-500">LGA / City</p>
                            <p id="cofo-view-lga" class="font-medium">Loading...</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Plot Number</p>
                            <p id="cofo-view-plot-no" class="font-medium">Loading...</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Layout</p>
                            <p id="cofo-view-layout" class="font-medium">Loading...</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Location</p>
                            <p id="cofo-view-location" class="font-medium">Loading...</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Land Use</p>
                            <p id="cofo-view-land-use" class="font-medium">Loading...</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white border p-4 rounded-lg">
                <h3 class="text-lg font-semibold mb-3">Transaction Details</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <p class="text-sm text-gray-500">Transaction Type</p>
                        <p id="cofo-view-transaction-type" class="font-medium">Loading...</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Transaction Date</p>
                        <p id="cofo-view-transaction-date" class="font-medium">Loading...</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Transaction Time</p>
                        <p id="cofo-view-transaction-time" class="font-medium">Loading...</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Registration Number</p>
                        <p id="cofo-view-reg-no" class="font-medium">Loading...</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Instrument Type</p>
                        <p id="cofo-view-instrument-type" class="font-medium">Loading...</p>
                    </div>
                </div>
            </div>

            <div id="cofo-view-parties" class="bg-white border p-4 rounded-lg hidden">
                <h3 class="text-lg font-semibold mb-3">Parties</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <p id="cofo-view-party-from-label" class="text-sm text-gray-500">From</p>
                        <p id="cofo-view-party-from" class="font-medium">Not specified</p>
                    </div>
                    <div>
                        <p id="cofo-view-party-to-label" class="text-sm text-gray-500">To</p>
                        <p id="cofo-view-party-to" class="font-medium">Not specified</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex justify-end space-x-3 pt-4 border-t mt-4">
            <button id="cofo-view-close" class="btn btn-primary" type="button">Close</button>
        </div>
    </div>
</div>
