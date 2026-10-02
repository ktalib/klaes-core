{{-- Decline Reason Modal --}}
<div id="declineReasonModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 items-center justify-center z-50 hidden" style="display: none;">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-4xl max-h-[90vh] overflow-y-auto">
        <div class="p-4 border-b flex justify-between items-center bg-red-50">
            <h3 class="text-lg font-medium text-red-800">Specify Decline Reasons</h3>
            <button id="closeDeclineModal" class="text-gray-500 hover:text-gray-700">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>
        
        <div class="p-6 space-y-6">
            <div class="text-sm text-gray-600 mb-4 bg-yellow-50 p-4 rounded-md border border-yellow-200">
                <p class="font-medium text-yellow-800">Instructions:</p>
                <p>Please select applicable reasons for declining this application and provide specific details for each selected reason.</p>
            </div>
            
             <!-- 1. Accessibility Category - Simplified -->
            <div class="border rounded-md p-4 bg-gray-50 shadow-sm">
                <div class="flex items-start mb-3">
                    <input type="checkbox" id="accessibilityCheck" class="mt-1 decline-reason-check h-4 w-4" onclick="toggleDetails(this, 'accessibilityDetails')">
                    <div class="ml-3">
                        <label for="accessibilityCheck" class="font-medium text-gray-800 text-base">1. Accessibility Issues</label>
                        <p class="text-sm text-gray-600">The property/site must have adequate accessibility to ensure ease of movement and compliance with urban planning standards.</p>
                    </div>
                </div>
                
                <div class="ml-8 mt-3 decline-reason-details bg-white p-4 rounded-md border" id="accessibilityDetails" style="display: none;">
                    <div class="mb-4">
                        <label for="accessibilitySpecificDetails" class="block text-sm font-medium text-gray-700 mb-1">Specific details about accessibility issues:</label>
                        <textarea id="accessibilitySpecificDetails" rows="3" placeholder="E.g., The property lacks direct access to an approved road network..." class="w-full p-2 border border-gray-300 rounded-md text-sm"></textarea>
                    </div>
                    
                    <div>
                        <label for="accessibilityObstructions" class="block text-sm font-medium text-gray-700 mb-1">Obstructions or barriers to access (if any):</label>
                        <textarea id="accessibilityObstructions" rows="2" placeholder="Describe any physical barriers or obstructions..." class="w-full p-2 border border-gray-300 rounded-md text-sm"></textarea>
                    </div>
                </div>
            </div>
            
            <!-- 2. Land Use Conformity Category - Simplified -->
            <div class="border rounded-md p-4 bg-gray-50 shadow-sm">
                <div class="flex items-start mb-3">
                    <input type="checkbox" id="conformityCheck" class="mt-1 decline-reason-check h-4 w-4" onclick="toggleDetails(this, 'conformityDetails')">
                    <div class="ml-3">
                        <label for="conformityCheck" class="font-medium text-gray-800 text-base">2. Land Use Conformity Issues</label>
                        <p class="text-sm text-gray-600">The property/site must conform to the existing land use designation of the area as per the Kano State Physical Development Plan.</p>
                    </div>
                </div>
                
                <div class="ml-8 mt-3 decline-reason-details bg-white p-4 rounded-md border" id="conformityDetails" style="display: none;">
                    <div class="mb-4">
                        <label for="landUseDetails" class="block text-sm font-medium text-gray-700 mb-1">Specific details about non-conformity:</label>
                        <textarea id="landUseDetails" rows="3" placeholder="E.g., The proposed use of the property conflicts with the designated residential zoning of the area..." class="w-full p-2 border border-gray-300 rounded-md text-sm"></textarea>
                    </div>
                    
                    <div>
                        <label for="landUseDeviations" class="block text-sm font-medium text-gray-700 mb-1">Deviations from the approved land use plan:</label>
                        <textarea id="landUseDeviations" rows="2" placeholder="Describe any specific deviations from zoning or land use plans..." class="w-full p-2 border border-gray-300 rounded-md text-sm"></textarea>
                    </div>
                </div>
            </div>
            
            <!-- 3. Utility Lines Category - Simplified -->
            <div class="border rounded-md p-4 bg-gray-50 shadow-sm">
                <div class="flex items-start mb-3">
                    <input type="checkbox" id="utilityCheck" class="mt-1 decline-reason-check h-4 w-4" onclick="toggleDetails(this, 'utilityDetails')">
                    <div class="ml-3">
                        <label for="utilityCheck" class="font-medium text-gray-800 text-base">3. Utility Line Interference</label>
                        <p class="text-sm text-gray-600">The property/site must not transverse or interfere with existing utility lines (e.g., electricity, water, sewage).</p>
                    </div>
                </div>
                
                <div class="ml-8 mt-3 decline-reason-details bg-white p-4 rounded-md border" id="utilityDetails" style="display: none;">
                    <div class="mb-4">
                        <label for="utilityIssueDetails" class="block text-sm font-medium text-gray-700 mb-1">Specific details about utility line issues:</label>
                        <textarea id="utilityIssueDetails" rows="3" placeholder="E.g., The property boundary overlaps with an existing high-voltage power line corridor..." class="w-full p-2 border border-gray-300 rounded-md text-sm"></textarea>
                    </div>
                    
                    <div>
                        <label for="utilityTypeDetails" class="block text-sm font-medium text-gray-700 mb-1">Type of utility line affected and implications:</label>
                        <textarea id="utilityTypeDetails" rows="2" placeholder="Specify the utility type (electricity, water, sewage) and safety/access implications..." class="w-full p-2 border border-gray-300 rounded-md text-sm"></textarea>
                    </div>
                </div>
            </div>
            
            <!-- 4. Road Reservation Category - Simplified -->
            <div class="border rounded-md p-4 bg-gray-50 shadow-sm">
                <div class="flex items-start mb-3">
                    <input type="checkbox" id="roadReservationCheck" class="mt-1 decline-reason-check h-4 w-4" onclick="toggleDetails(this, 'roadReservationDetails')">
                    <div class="ml-3">
                        <label for="roadReservationCheck" class="font-medium text-gray-800 text-base">4. Road Reservation Issues</label>
                        <p class="text-sm text-gray-600">The property/site must have an adequate access road or comply with minimum road reservation standards as stipulated in KAMMA guidelines.</p>
                    </div>
                </div>
                
                <div class="ml-8 mt-3 decline-reason-details bg-white p-4 rounded-md border" id="roadReservationDetails" style="display: none;">
                    <div class="mb-4">
                        <label for="roadReservationIssues" class="block text-sm font-medium text-gray-700 mb-1">Specific details about road/reservation issues:</label>
                        <textarea id="roadReservationIssues" rows="3" placeholder="E.g., The property lacks a defined access road, and the surrounding road network is below the required width..." class="w-full p-2 border border-gray-300 rounded-md text-sm"></textarea>
                    </div>
                    
                    <div>
                        <label for="roadMeasurements" class="block text-sm font-medium text-gray-700 mb-1">Measurements or observations related to deficiencies:</label>
                        <textarea id="roadMeasurements" rows="2" placeholder="Provide relevant measurements (required vs. actual) and observations..." class="w-full p-2 border border-gray-300 rounded-md text-sm"></textarea>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="p-4 border-t flex justify-end bg-gray-50">
            <button type="button" id="cancelDeclineReasons" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 mr-2" onclick="toggleModal(false)">
                Cancel
            </button>
            <button type="button" id="saveDeclineReasons" class="px-4 py-2 text-sm font-medium text-white bg-red-600 border border-transparent rounded-md shadow-sm hover:bg-red-700">
                Save Reasons
            </button>
            <button type="button" id="saveAndViewDeclineReasons" class="ml-2 px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md shadow-sm hover:bg-blue-700">
                Save & View Memo
            </button>
        </div>
    </div>
</div>