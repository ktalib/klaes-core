{{-- Planning Recommendation Approval Tab --}}
@php
    $approvalTabActive = ($activeTab ?? null) === 'initial';
@endphp
<div id="initial-tab" class="tab-content {{ $approvalTabActive ? 'active' : '' }}">
    <div class="bg-white border border-gray-200 rounded-lg shadow-sm">
        <div class="p-4 border-b">
            <h3 class="text-sm font-medium">Planning Recommendation Approval</h3>
        </div>
    <form id="planningRecommendationForm" method="POST" action="{{ route('planning-recommendation.update') }}">
            @csrf
            <input type="hidden" name="application_id" value="{{ $application->id }}">
            <div class="p-4 space-y-4">
                <input type="hidden" id="application_id" value="{{ $application->id }}">
                <input type="hidden" name="fileno" value="{{ $application->fileno }}">
                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-2">
                        <label class="text-xs font-medium block">
                            Decision
                        </label>
                        <div class="flex items-center space-x-4">

                            <label class="inline-flex items-center">
                                <input 
                                    id="planning-decision-approve"
                                    type="radio" 
                                    name="status" 
                                    value="approve"
                                    class="form-radio"
                                    onchange="toggleObservationsAndReasonContainers(this)"
                                    @if(strtolower($application->planning_recommendation_status ?? '') === 'approve' || strtolower($application->planning_recommendation_status ?? '') === 'approved') checked @endif
                                    @if(strtolower($application->planning_recommendation_status ?? '') === 'approve' || strtolower($application->planning_recommendation_status ?? '') === 'approved') disabled @endif
                                >
                                <span class="ml-2 text-sm @if(strtolower($application->planning_recommendation_status ?? '') === 'approve' || strtolower($application->planning_recommendation_status ?? '') === 'approved') text-gray-400 @endif">Approve</span>
                            </label>
                            <label class="inline-flex items-center">
                                <input 
                                    type="radio" 
                                    name="status" 
                                    value="decline"
                                    class="form-radio"
                                    onchange="toggleObservationsAndReasonContainers(this)"
                                    @if(strtolower($application->planning_recommendation_status ?? '') === 'decline' || strtolower($application->planning_recommendation_status ?? '') === 'declined') checked @endif
                                    @if(strtolower($application->planning_recommendation_status ?? '') === 'approve' || strtolower($application->planning_recommendation_status ?? '') === 'approved') disabled @endif
                                >
                                <span class="ml-2 text-sm @if(strtolower($application->planning_recommendation_status ?? '') === 'approve' || strtolower($application->planning_recommendation_status ?? '') === 'approved') text-gray-400 @endif">Decline</span>
                            </label>

                            <script>
                                function toggleObservationsAndReasonContainers(radio) {
                                    const reasonContainer = document.getElementById('reasonContainer');
                                    const observationsContainer = document.getElementById('observationsContainer');
                                    
                                    // Only show reason container when declining
                                    reasonContainer.style.display = (radio.value === 'decline') ? 'block' : 'none';
                                    
                                    // Only show observations container when approving
                                    if (observationsContainer) {
                                        observationsContainer.style.display = (radio.value === 'approve') ? 'block' : 'none';
                                    }
                                }
                            </script>
                        </div>
                    </div>
                    <div class="space-y-2">
                        <label for="approval-date" class="text-xs font-medium block">
                            Approval/Decline Date
                        </label>
                        <div class="flex items-center space-x-2">
                            <input id="approval-date" type="datetime-local" name="approval_date"
                                value="{{ old('approval_date') ?? now()->format('Y-m-d\TH:i') }}"
                                class="w-full p-2 border border-gray-300 rounded-md text-sm"
                                max="{{ now()->format('Y-m-d\TH:i') }}"
                            >
                            <button type="button" onclick="document.getElementById('approval-date').value = '{{ now()->format('Y-m-d\TH:i') }}';"
                                class="px-2 py-1 text-xs bg-gray-200 rounded hover:bg-gray-300">
                                Use Current Date/Time
                            </button>
                        </div>
                        <span class="text-xs text-gray-500">You cannot select a future date.</span>
                    </div>
                </div>

                <!-- Additional Observations Section -->
                <div id="observationsContainer" class="grid grid-cols-1 gap-4" style="display: none;">
                    <div class="space-y-2">
                        <label for="additionalObservations" class="text-xs font-medium block">
                            Additional Observations (If applicable)
                        </label>
                        <div class="border border-gray-300 rounded-md p-2">
                            <form id="saveObservationsForm" method="POST" action="{{ route('pr_memos.save-observations') }}">
                                @csrf
                                <input type="hidden" name="application_id" value="{{ $application->id }}">
                                <textarea id="additionalObservations" name="additional_observations" rows="4" 
                                    class="w-full p-2 border-none focus:outline-none focus:ring-0"
                                    placeholder="Enter any additional observations or special considerations here...">{{ $additionalObservations ?? '' }}</textarea>
                                <div class="flex justify-end mt-2">
                                    <button type="submit" id="saveObservations" 
                                        class="px-3 py-1 bg-green-600 text-white text-sm rounded hover:bg-green-700">
                                        Save Observations
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div id="reasonContainer" class="space-y-2" style="display: none;">
                    <label for="comments" class="text-xs font-medium block">
                        Reason <span class="text-red-500">*</span>
                    </label>
                    <button type="button" id="openDeclineReasonModal" 
                        class="w-full p-2 border border-gray-300 rounded-md text-sm bg-white text-left text-gray-500 hover:bg-gray-50">
                        Click to specify decline reasons...
                    </button>
                    <input type="hidden" id="comments" name="comments">
                    <p class="text-xs text-red-500 mt-1">Please provide detailed reasons for declining this application</p>
                </div>

                <hr class="my-4">

                <div class="flex justify-between items-center">
                    <div class="flex gap-2">
                        <button type="button" onclick="window.history.back()"
                            class="flex items-center px-3 py-1 text-xs border border-gray-300 rounded-md bg-white hover:bg-gray-50">
                            <i data-lucide="undo-2" class="w-3.5 h-3.5 mr-1.5"></i>
                            Back
                        </button>
                        @php
                            $planningStatus = strtolower($application->planning_recommendation_status ?? '');
                            $isPlanningApproved = in_array($planningStatus, ['approve', 'approved'], true);
                            $submitButtonBaseClasses = 'flex items-center px-3 py-1 text-xs rounded-md transition-colors duration-150';
                            $submitButtonVariantClasses = $isPlanningApproved
                                ? 'bg-gray-400 text-gray-200 cursor-not-allowed'
                                : 'bg-green-700 text-white hover:bg-green-800';
                        @endphp
                        <button id="planningRecommendationSubmitBtn" type="submit"
                            class="{{ $submitButtonBaseClasses }} {{ $submitButtonVariantClasses }}"
                            @if(strtolower($application->planning_recommendation_status ?? '') === 'approve' || strtolower($application->planning_recommendation_status ?? '') === 'approved')
                                disabled
                            @endif
                        >
                            <i data-lucide="send-horizontal" class="w-3.5 h-3.5 mr-1.5"></i>
                            Submit
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>