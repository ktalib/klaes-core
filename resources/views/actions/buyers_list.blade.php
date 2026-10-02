@extends('layouts.app')

@section('page-title')
    {{ __('SECTIONAL TITLING MODULE') }}
@endsection

@section('content')
@include('sectionaltitling.partials.assets.css')

@php
    $applicantDisplayName = '';
    switch ($application->applicant_type) {
        case 'individual':
            $applicantDisplayName = trim(($application->applicant_title ?? '') . ' ' . ($application->first_name ?? '') . ' ' . ($application->surname ?? ''));
            break;
        case 'corporate':
            $applicantDisplayName = trim(($application->rc_number ?? '') . ' ' . ($application->corporate_name ?? ''));
            break;
        case 'multiple':
            $applicantDisplayName = $application->multiple_owners_names ?? '';
            break;
        default:
            $applicantDisplayName = $application->applicant_name ?? '';
    }

    $applicantDisplayName = trim(preg_replace('/\s+/', ' ', $applicantDisplayName));
    $buyersReadOnly = $isPlanningRecommendationApproved ?? false;
@endphp

<div class="flex-1 overflow-auto">
    @include('admin.header')

    <div class="p-6 space-y-6">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold text-gray-800">Buyers List</h1>
                <p class="text-sm text-gray-500">Add or review the buyers captured for this sectional titling application.</p>
            </div>
            <div class="flex items-center gap-3">
                @if($buyersReadOnly)
                    <span class="inline-flex items-center rounded-md bg-amber-100 px-3 py-1 text-xs font-medium text-amber-700">
                        <i data-lucide="lock" class="w-3.5 h-3.5 mr-1.5"></i>
                        Read-only — planning recommendation approved
                    </span>
                @endif
                <button type="button" onclick="window.history.back()" class="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-600 bg-white border border-gray-300 rounded-md hover:text-gray-800 hover:border-gray-400 transition">
                    <i data-lucide="arrow-left" class="w-4 h-4 mr-2"></i>
                    Back
                </button>
            </div>
        </div>

        @if($buyersReadOnly)
            <div class="flex items-start gap-3 rounded-md border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                <i data-lucide="info" class="w-5 h-5 mt-0.5"></i>
                <div>
                    <p class="font-semibold">Planning Recommendation has been approved.</p>
                    <p class="text-xs mt-1">The buyers list is locked for editing. You can still review the existing records below.</p>
                </div>
            </div>
        @endif

        <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-6">
            <div class="grid gap-6 sm:grid-cols-2 xl:grid-cols-4">
                <div class="flex items-center gap-3">
                    <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-blue-50 text-blue-600">
                        <i data-lucide="hash" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">File Number</p>
                        <p class="text-sm font-semibold text-gray-800 mt-1">{{ $application->fileno ?? 'N/A' }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-indigo-50 text-indigo-600">
                        <i data-lucide="clipboard-list" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Applicant</p>
                        <p class="text-sm font-semibold text-gray-800 mt-1">{{ $applicantDisplayName ?: 'N/A' }}</p>
                        <p class="mt-1 text-xs text-gray-500">Applicant Type: {{ ucfirst($application->applicant_type ?? 'Unknown') }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-green-50 text-green-600">
                        <i data-lucide="map-pin" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Land Use</p>
                        @php
                            $landUseClass = match(strtolower($application->land_use ?? '')) {
                                'commercial' => 'badge-commercial',
                                'industrial' => 'badge-industrial',
                                'mixed' => 'badge-new',
                                default => 'badge-residential'
                            };
                        @endphp
                        <p class="mt-1">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border {{ $landUseClass }}">
                                {{ $application->land_use ?? 'N/A' }}
                            </span>
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-amber-50 text-amber-600">
                        <i data-lucide="check-circle" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Planning Recommendation</p>
                        @php
                            $planningStatus = $application->planning_recommendation_status ?? 'Pending';
                            $statusClass = match($planningStatus) {
                                'Approved' => 'badge-approved',
                                'Declined' => 'badge-declined',
                                default => 'badge-pending'
                            };
                        @endphp
                        <p class="mt-1">
                            <span class="badge {{ $statusClass }}">{{ $planningStatus }}</span>
                        </p>
                    </div>
                </div>
        
            </div>
        </div>

        <div id="buyers-registry-panel" class="bg-white border border-gray-200 rounded-lg shadow-sm">
            @include('sectionaltitling.partials.buyers-list-tab', [
                'application' => $application,
                'titles' => $titles,
                'isReadOnly' => $buyersReadOnly
            ])
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="{{ asset('js/buyer-list-management.js') }}"></script>
<script>
    window.isApplicationApproved = {{ $buyersReadOnly ? 'true' : 'false' }};
    const titles = @json($titles);

    document.addEventListener('DOMContentLoaded', function () {
        const buyersTab = document.getElementById('buyers-tab');
        if (buyersTab) {
            buyersTab.classList.add('active');
            buyersTab.style.display = 'block';
        }

        if (typeof loadBuyersList === 'function') {
            loadBuyersList();
        }
    });
    
</script>
@endsection
