<!-- Step 8: Application Summary -->
<div id="step-content-8" class="step-content hidden">
    <div class="bg-white border border-gray-200 rounded-lg">
        <div class="p-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold flex items-center gap-2">
                <i data-lucide="file-check" class="h-5 w-5"></i>
                APPLICATION SUMMARY
            </h3>
            <p class="text-sm text-gray-600 mt-1">Please review your application details before submission</p>
        </div>
        <div class="p-4 space-y-6">
            
            <!-- Application Information -->
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <h4 class="font-semibold text-blue-900 mb-3 flex items-center gap-2">
                    <i data-lucide="info" class="h-4 w-4"></i>
                    Application Information
                </h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                    <div>
                        <span class="font-medium text-gray-700">Application Date:</span>
                        <span id="summary-application-date" class="ml-2 text-gray-900">-</span>
                    </div>
                    <div>
                        <span class="font-medium text-gray-700">File Number:</span>
                        <span id="summary-file-number" class="ml-2 text-blue-900 font-mono">-</span>
                    </div>
                    <div>
                        <span class="font-medium text-gray-700">Application Type:</span>
                        <span id="summary-application-type" class="ml-2 text-gray-900">-</span>
                    </div>
                    <div>
                        <span class="font-medium text-gray-700">Reason:</span>
                        <span id="summary-application-reason" class="ml-2 text-gray-900">-</span>
                    </div>
                </div>
            </div>

            <!-- Applicant Details -->
            <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                <h4 class="font-semibold text-green-900 mb-3 flex items-center gap-2">
                    <i data-lucide="user" class="h-4 w-4"></i>
                    Applicant Details
                </h4>
                <div id="summary-applicant-details" class="space-y-3">
                    <!-- Content will be populated by JavaScript -->
                </div>
            </div>

            <!-- Contact Information -->
            <div class="bg-purple-50 border border-purple-200 rounded-lg p-4">
                <h4 class="font-semibold text-purple-900 mb-3 flex items-center gap-2">
                    <i data-lucide="phone" class="h-4 w-4"></i>
                    Contact Information
                </h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                    <div>
                        <span class="font-medium text-gray-700">Phone Number:</span>
                        <span id="summary-phone" class="ml-2 text-gray-900">-</span>
                    </div>
                    <div>
                        <span class="font-medium text-gray-700">Email Address:</span>
                        <span id="summary-email" class="ml-2 text-gray-900">-</span>
                    </div>
                    <div class="md:col-span-2">
                        <span class="font-medium text-gray-700">Address:</span>
                        <span id="summary-address" class="ml-2 text-gray-900">-</span>
                    </div>
                </div>
            </div>

            <!-- Title Holder Information -->
            <div class="bg-orange-50 border border-orange-200 rounded-lg p-4">
                <h4 class="font-semibold text-orange-900 mb-3 flex items-center gap-2">
                    <i data-lucide="award" class="h-4 w-4"></i>
                    Title Holder Information
                </h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                    <div>
                        <span class="font-medium text-gray-700">Title Holder:</span>
                        <span id="summary-title-holder" class="ml-2 text-gray-900">-</span>
                    </div>
                    <div>
                        <span class="font-medium text-gray-700">C-of-O Number:</span>
                        <span id="summary-cofo-number" class="ml-2 text-gray-900 font-mono">-</span>
                    </div>
                    <div>
                        <span class="font-medium text-gray-700">Original Owner:</span>
                        <span id="summary-original-owner" class="ml-2 text-gray-900">-</span>
                    </div>
                    <div>
                        <span class="font-medium text-gray-700">Instrument Type:</span>
                        <span id="summary-instrument-type" class="ml-2 text-gray-900">-</span>
                    </div>
                </div>
            </div>

            <!-- Plot Details -->
            <div class="bg-teal-50 border border-teal-200 rounded-lg p-4">
                <h4 class="font-semibold text-teal-900 mb-3 flex items-center gap-2">
                    <i data-lucide="map-pin" class="h-4 w-4"></i>
                    Plot Details
                </h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                    <div>
                        <span class="font-medium text-gray-700">Plot Number:</span>
                        <span id="summary-plot-number" class="ml-2 text-gray-900">-</span>
                    </div>
                    <div>
                        <span class="font-medium text-gray-700">Plot Size:</span>
                        <span id="summary-plot-size" class="ml-2 text-gray-900">-</span>
                    </div>
                    <div>
                        <span class="font-medium text-gray-700">Layout/District:</span>
                        <span id="summary-layout-district" class="ml-2 text-gray-900">-</span>
                    </div>
                    <div>
                        <span class="font-medium text-gray-700">LGA:</span>
                        <span id="summary-lga" class="ml-2 text-gray-900">-</span>
                    </div>
                    <div>
                        <span class="font-medium text-gray-700">Current Land Use:</span>
                        <span id="summary-land-use" class="ml-2 text-gray-900">-</span>
                    </div>
                    <div>
                        <span class="font-medium text-gray-700">Plot Status:</span>
                        <span id="summary-plot-status" class="ml-2 text-gray-900">-</span>
                    </div>
                </div>
            </div>

            <!-- Payment Information -->
            <div class="bg-indigo-50 border border-indigo-200 rounded-lg p-4">
                <h4 class="font-semibold text-indigo-900 mb-3 flex items-center gap-2">
                    <i data-lucide="credit-card" class="h-4 w-4"></i>
                    Payment Information
                </h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                    <div>
                        <span class="font-medium text-gray-700">Payment Method:</span>
                        <span id="summary-payment-method" class="ml-2 text-gray-900">-</span>
                    </div>
                    <div>
                        <span class="font-medium text-gray-700">Payment Amount:</span>
                        <span id="summary-payment-amount" class="ml-2 text-gray-900">-</span>
                    </div>
                    <div>
                        <span class="font-medium text-gray-700">Receipt/Teller No:</span>
                        <span id="summary-receipt-no" class="ml-2 text-gray-900">-</span>
                    </div>
                    <div>
                        <span class="font-medium text-gray-700">Bank Name:</span>
                        <span id="summary-bank-name" class="ml-2 text-gray-900">-</span>
                    </div>
                </div>
            </div>

            <!-- Supporting Documents -->
            <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                <h4 class="font-semibold text-gray-900 mb-3 flex items-center gap-2">
                    <i data-lucide="paperclip" class="h-4 w-4"></i>
                    Supporting Documents
                </h4>
                <div id="summary-documents" class="space-y-2 text-sm">
                    <!-- Content will be populated by JavaScript -->
                </div>
            </div>

            <!-- Uploaded Files -->
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                <h4 class="font-semibold text-yellow-900 mb-3 flex items-center gap-2">
                    <i data-lucide="upload" class="h-4 w-4"></i>
                    Uploaded Files
                </h4>
                <div id="summary-uploaded-files" class="space-y-2 text-sm">
                    <!-- Content will be populated by JavaScript -->
                </div>
            </div>

            <!-- Declaration Confirmation -->
            <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                <h4 class="font-semibold text-red-900 mb-3 flex items-center gap-2">
                    <i data-lucide="shield-check" class="h-4 w-4"></i>
                    Declaration & Confirmation
                </h4>
                <div class="space-y-2 text-sm">
                    <div class="flex items-center gap-2">
                        <i data-lucide="check-circle" class="h-4 w-4 text-green-600"></i>
                        <span class="text-gray-900">I agree with the terms and conditions of service</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <i data-lucide="check-circle" class="h-4 w-4 text-green-600"></i>
                        <span class="text-gray-900">I confirm that all information provided is accurate and complete</span>
                    </div>
                </div>
            </div>

            <!-- Edit Actions -->
            <div class="bg-white border-2 border-dashed border-gray-300 rounded-lg p-4">
                <h4 class="font-semibold text-gray-900 mb-3 flex items-center gap-2">
                    <i data-lucide="edit" class="h-4 w-4"></i>
                    Need to Make Changes?
                </h4>
                <p class="text-sm text-gray-600 mb-3">
                    If you need to modify any information, you can go back to the relevant step using the buttons below:
                </p>
                <div class="flex flex-wrap gap-2">
                    <button type="button" onclick="goToStep(1)" class="inline-flex items-center justify-center rounded-md font-medium text-xs px-3 py-1.5 transition-all cursor-pointer bg-blue-100 text-blue-700 hover:bg-blue-200 gap-1">
                        <i data-lucide="user" class="h-3 w-3"></i>
                        Personal Details
                    </button>
                    <button type="button" onclick="goToStep(2)" class="inline-flex items-center justify-center rounded-md font-medium text-xs px-3 py-1.5 transition-all cursor-pointer bg-purple-100 text-purple-700 hover:bg-purple-200 gap-1">
                        <i data-lucide="phone" class="h-3 w-3"></i>
                        Contact Details
                    </button>
                    <button type="button" onclick="goToStep(3)" class="inline-flex items-center justify-center rounded-md font-medium text-xs px-3 py-1.5 transition-all cursor-pointer bg-orange-100 text-orange-700 hover:bg-orange-200 gap-1">
                        <i data-lucide="award" class="h-3 w-3"></i>
                        Title Holder
                    </button>
                    <button type="button" onclick="goToStep(4)" class="inline-flex items-center justify-center rounded-md font-medium text-xs px-3 py-1.5 transition-all cursor-pointer bg-red-100 text-red-700 hover:bg-red-200 gap-1">
                        <i data-lucide="shield" class="h-3 w-3"></i>
                        Mortgage & Encumbrance
                    </button>
                    <button type="button" onclick="goToStep(5)" class="inline-flex items-center justify-center rounded-md font-medium text-xs px-3 py-1.5 transition-all cursor-pointer bg-teal-100 text-teal-700 hover:bg-teal-200 gap-1">
                        <i data-lucide="map-pin" class="h-3 w-3"></i>
                        Plot Details
                    </button>
                    <button type="button" onclick="goToStep(6)" class="inline-flex items-center justify-center rounded-md font-medium text-xs px-3 py-1.5 transition-all cursor-pointer bg-indigo-100 text-indigo-700 hover:bg-indigo-200 gap-1">
                        <i data-lucide="credit-card" class="h-3 w-3"></i>
                        Payment & Terms
                    </button>
                </div>
            </div>

            <!-- Final Submission Notice -->
            <div class="bg-gradient-to-r from-green-50 to-blue-50 border-2 border-green-200 rounded-lg p-6 text-center">
                <div class="flex justify-center mb-3">
                    <i data-lucide="send" class="h-8 w-8 text-green-600"></i>
                </div>
                <h4 class="font-bold text-lg text-gray-900 mb-2">Ready to Submit?</h4>
                <p class="text-sm text-gray-700 mb-4">
                    Once you submit this application, you will receive a confirmation email and your application will be processed by our team.
                </p>
                <div class="bg-white rounded-lg p-3 border border-gray-200">
                    <p class="text-xs text-gray-600">
                        <strong>Processing Time:</strong> Applications are typically processed within 5-10 business days. 
                        You will be notified via email and SMS about the status of your application.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>