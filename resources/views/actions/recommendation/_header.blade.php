{{-- Page Header with Status --}}
<div class="modal-content8 p-6">
    <div class="flex justify-between items-center mb-4">
        <h2 class="text-lg font-medium">
            Planning Recommendation 
            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $statusClass }}">
                <i data-lucide="{{ $statusIcon }}" class="w-3 h-3 mr-1"></i>
                {{ $application->planning_recommendation_status }}
            </span>
        </h2>
        <button onclick="window.history.back()" class="text-gray-500 hover:text-gray-700">
            <i data-lucide="x" class="w-5 h-5"></i>
        </button>
    </div>
    <div class="text-sm mb-4">
        Approval Date:
        <span class="font-medium">
            {{ $application->planning_approval_date }}
        </span>
    </div>

    <div class="py-2">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="text-sm font-medium">{{ $application->land_use }} Property</h3>
                <p class="text-xs text-gray-500">
                    Application ID: {{ $application->applicationID }} | File No: {{ $application->fileno }}
                </p>

                <span
                    class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $statusClass }}">
                    <i data-lucide="{{ $statusIcon }}" class="w-3 h-3 mr-1"></i>
                    {{ $application->planning_recommendation_status }}
                </span>
            </div>
            <div class="text-right">
                <h3 class="text-sm font-medium">
                    @if ($application->applicant_type == 'individual')
                        {{ $application->applicant_title }} {{ $application->first_name }}
                        {{ $application->surname }}
                    @elseif($application->applicant_type == 'corporate')
                        {{ $application->rc_number }} {{ $application->corporate_name }}
                    @elseif($application->applicant_type == 'multiple')
                        @php
                            $names = @json_decode($application->multiple_owners_names, true);
                            if (is_array($names) && count($names) > 0) {
                                echo implode(', ', $names);
                            } else {
                                echo $application->multiple_owners_names;
                            }
                        @endphp
                    @endif
                </h3>
                <p class="text-xs text-gray-500">
                    <span
                        class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">
                        {{ $application->land_use }}
                    </span>
                </p>
            </div>
        </div>
    </div>
</div>