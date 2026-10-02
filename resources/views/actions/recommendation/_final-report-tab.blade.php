{{-- Final Planning Recommendation Report Tab --}}
@php
    $finalTabActive = ($activeTab ?? null) === 'final';
@endphp
<div id="final-tab" class="tab-content {{ $finalTabActive ? 'active' : '' }}">
    <div class="bg-white border border-gray-200 rounded-lg shadow-sm">
        <div class="p-4 border-b">
            <h3 class="text-sm font-medium">Planning Recommendation Report</h3>
            <p class="text-xs text-gray-500"></p>
            @php
                $showPrintButton = request()->query('url') === 'recommendation';
            @endphp
            @if($showPrintButton)
                @php
                    $isSubApplication = isset($application->main_application_id) && !empty($application->main_application_id);
                    $printRouteName = $isSubApplication ? 'sub_pr_memos.print' : 'planning-recommendation.print';
                @endphp
                <div class="mt-3">
                    <a href="{{ route($printRouteName, $application->id) }}"
                        target="_blank"
                        rel="noopener"
                        class="inline-flex items-center px-3 py-1 text-xs bg-blue-600 text-white rounded-md hover:bg-blue-700">
                        <i data-lucide="printer" class="w-3.5 h-3.5 mr-1.5"></i>
                        Print Document
                    </a>
                </div>
            @endif
        </div>
        <input type="hidden" id="application_id" value="{{ $application->id }}">
        <input type="hidden" name="fileno" value="{{ $application->fileno }}">
        <div class="p-4 space-y-4">

            @include('actions.planning_recomm')
            <hr class="my-4">

            <div class="flex justify-between items-center">
                <div class="flex gap-2">
                    <button onclick="window.history.back()"
                        class="flex items-center px-3 py-1 text-xs border border-gray-300 rounded-md bg-white hover:bg-gray-50">
                        <i data-lucide="undo-2" class="w-3.5 h-3.5 mr-1.5"></i>
                        Back
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>