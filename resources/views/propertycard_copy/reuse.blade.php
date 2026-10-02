  <div id="transaction-details-dialog" class="dialog-overlay hidden">
                <div class="dialog-content">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-xl font-bold">Transaction Details</h2>
                        <button id="close-transaction-details" class="text-gray-500">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="18" y1="6" x2="6" y2="18"></line>
                                <line x1="6" y1="6" x2="18" y2="18"></line>
                            </svg>
                        </button>
                    </div>
                    <div class="grid grid-cols-2 gap-4 py-4">
                        <div class="space-y-1">
                            <p class="text-sm font-medium text-gray-500">Transaction Type</p>
                            <p id="transaction-type" class="font-medium">Assignment</p>
                        </div>
                        <div class="space-y-1">
                            <p class="text-sm font-medium text-gray-500">Date</p>
                            <p id="transaction-date" class="font-medium">2023-05-15</p>
                        </div>
                        <div class="space-y-1">
                            <p class="text-sm font-medium text-gray-500">File Number</p>
                            <p id="transaction-file-number" class="font-medium">KNML 39762</p>
                        </div>
                        <div class="space-y-1">
                            <p class="text-sm font-medium text-gray-500">Registration Number</p>
                            <p id="transaction-reg-number" class="font-medium">1/1/2</p>
                        </div>
                        <div class="space-y-1">
                            <p class="text-sm font-medium text-gray-500">From Party</p>
                            <p id="transaction-from-party" class="font-medium">John Doe</p>
                        </div>
                        <div class="space-y-1">
                            <p class="text-sm font-medium text-gray-500">To Party</p>
                            <p id="transaction-to-party" class="font-medium">Jane Smith</p>
                        </div>
                        <div class="space-y-1">
                            <p class="text-sm font-medium text-gray-500">Instrument Type</p>
                            <p id="transaction-instrument-type" class="font-medium">Deed of Assignment</p>
                        </div>
                        <div class="space-y-1">
                            <p class="text-sm font-medium text-gray-500">Status</p>
                            <span id="transaction-status" class="badge badge-green">Completed</span>
                        </div>
                        <div class="col-span-2 space-y-1">
                            <p class="text-sm font-medium text-gray-500">Additional Notes</p>
                            <p id="transaction-notes" class="text-sm">No additional notes available for this transaction.</p>
                        </div>
                    </div>
                    <div class="flex justify-end space-x-3 pt-4 border-t">
                        <button id="close-transaction-details-btn" class="btn btn-secondary">Close</button>
                        <button class="btn btn-primary">Edit Transaction</button>
                    </div>
                </div>
            </div>
        
            <!-- Add Transaction Dialog -->
            
        
            <!-- Capture CofO Dialog -->
           