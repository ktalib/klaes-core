@extends('layouts.app')

@section('page-title')
{{ __('Edit Inspection Details') }}
@endsection

@section('content')


<div class="flex-1 overflow-auto">
    <!-- Header -->
    @include($headerPartial ?? 'admin.header')

    <div class="max-w-5xl mx-auto space-y-6">
        <div class="bg-white border border-gray-200 rounded-xl shadow-sm">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 px-6 py-5 border-b border-gray-100">
                <div>
                    <h1 class="text-2xl font-semibold text-gray-800 hidden">{{ __('Joint Site Inspection') }}</h1>
                    <p class="text-sm text-gray-600 mt-1 hidden">
                        @if($context === 'unit')
                            {{ __('Editing inspection details for Unit Application #:id', ['id' => $subApplication->id]) }}
                        @else
                            {{ __('Editing inspection details for Primary Application #:id', ['id' => $application->id]) }}
                        @endif
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    @if($returnUrl)
                        <a href="{{ $returnUrl }}" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">
                            <i data-lucide="arrow-left" class="w-4 h-4"></i>
                            <span>{{ __('Back to list') }}</span>
                        </a>
                    @else
                        <a href="{{ route('programmes.approvals.planning_recomm', ['url' => 'view']) }}" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">
                            <i data-lucide="arrow-left" class="w-4 h-4"></i>
                            <span>{{ __('Back to Planning Recommendation') }}</span>
                        </a>
                    @endif
                </div>
            </div>

            <div class="px-6 py-5 grid grid-cols-1 md:grid-cols-2 gap-5 text-sm text-gray-700">
                <div class="space-y-2">
                    <p class="font-semibold text-gray-900 hidden">{{ __('Applicant') }}</p>
                    <p class="text-gray-600 hidden">
                        @if($context === 'unit')
                            {{ trim(($subApplication->first_name ?? '') . ' ' . ($subApplication->surname ?? '')) ?: __('Not captured') }}
                        @else
                            {{ $application->owner_name ?? __('Not captured') }}
                        @endif
                    </p>
                    @if($context === 'unit' && $application)
                        <p class="text-xs text-gray-500">{{ __('Primary Application #:id', ['id' => $application->id]) }}</p>
                    @endif
                </div>
                <div class="space-y-2">
                    <p class="font-semibold text-gray-900 hidden">{{ __('Latest Inspection Status') }}</p>
                    @if($report)
                        <div class="flex items-center gap-2 hidden">
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold rounded-full
                                {{ $report->is_submitted ? 'bg-green-100 text-green-700' : ($report->is_generated ? 'bg-blue-100 text-blue-700' : 'bg-amber-100 text-amber-700') }}">
                                <i data-lucide="file-text" class="w-3.5 h-3.5"></i>
                                <span>
                                    @if($report->is_submitted)
                                        {{ __('Submitted') }}
                                    @elseif($report->is_generated)
                                        {{ __('Generated') }}
                                    @else
                                        {{ __('Draft') }}
                                    @endif
                                </span>
                            </span>
                            @if($report->inspection_date)
                                <span class="text-xs text-gray-500">
                                    {{ __('Inspection date: :date', ['date' => optional($report->inspection_date)->format('d M Y')]) }}
                                </span>
                            @endif
                        </div>
                    @else
                        <p class="text-gray-500">{{ __('No inspection report has been captured yet.') }}</p>
                    @endif
                </div>
            </div>
        </div>

        <div class="bg-white border border-gray-200 rounded-xl shadow-sm">
            <div class="px-6 py-5 border-b border-gray-100">
                <h2 class="text-lg font-semibold text-gray-800">{{ __('Inspection Report Details') }}</h2>
                <p class="text-sm text-gray-600 mt-1">{{ __('Update the inspection report directly on this page and use the Save button to store your changes.') }}</p>
            </div>
            <div class="px-6 py-6">
                @include('programmes.partials.joint_site_inspection_form', [
                    'editorMode' => 'page',
                    'formClasses' => 'space-y-6',
                    'cancelUrl' => $returnUrl ?? route('programmes.approvals.planning_recomm', ['url' => 'view'])
                ])
            </div>
        </div>
    </div>
</div>

<script>
    window.jointInspectionEditorMode = 'page';
    window.jointInspectionSavedReport = @json($reportPayload);
    window.jointInspectionDefaults = @json($defaults);
    window.sharedUtilitiesOptions = @json($sharedUtilitiesOptions);
    window.unitDataOptions = @json($unitDataOptions);
    window.jointInspectionReturnUrl = @json($returnUrl);

    const jointInspectionTargetApplicationId = @json($defaults['application_id'] ?? null);
    const jointInspectionTargetSubId = @json($defaults['sub_application_id'] ?? null);

    const initializeJointInspection = () => {
        if (typeof initializeJointInspectionEditor === 'function') {
            initializeJointInspectionEditor(jointInspectionTargetApplicationId, jointInspectionTargetSubId);
        } else if (typeof openJointInspectionModal === 'function') {
            openJointInspectionModal(jointInspectionTargetApplicationId, jointInspectionTargetSubId, { showModal: false });
        } else {
            setTimeout(initializeJointInspection, 150);
        }
    };

    document.addEventListener('DOMContentLoaded', initializeJointInspection);
</script>

<script src="{{ asset('js/global-fileno-modal.js') }}"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (window.GlobalFileNoModal) {
            window.GlobalFileNoModal.init();
        }
    });
</script>

@include('programmes.partials.joint_site_inspection_modal', ['renderJointInspectionModalMarkup' => false])
@include('programmes.partials.joint_site_inspection_js')
@endsection
