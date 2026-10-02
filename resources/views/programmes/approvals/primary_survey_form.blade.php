@extends('layouts.app')

@section('page-title')
    {{ $PageTitle ?? __('Complete Survey Details (Primary)') }}
@endsection

@section('content')
    <div class="flex-1 overflow-auto">
        @include($headerPartial ?? 'admin.header')

        <div class="p-6">
            <div class="max-w-4xl mx-auto">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">{{ $PageTitle }}</h1>
                        <p class="text-sm text-gray-600 mt-1">{{ $PageDescription }}</p>
                    </div>
                    <a href="{{ $returnUrl }}" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <i data-lucide="arrow-left" class="w-4 h-4 mr-2"></i>
                        Back to Planning Recommendation
                    </a>
                </div>

                <div class="bg-white border border-gray-200 rounded-lg shadow-sm mb-6">
                    <div class="border-b border-gray-200 px-6 py-4">
                        <h2 class="text-lg font-semibold text-gray-900">Application Summary</h2>
                    </div>
                    <div class="px-6 py-4 grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                        <div>
                            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">File Number</p>
                            <p class="mt-1 text-base text-gray-900 font-semibold">{{ $application->fileno ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Owner</p>
                            <p class="mt-1 text-base text-gray-900 font-semibold">{{ $ownerName }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Land Use</p>
                            <p class="mt-1 text-base text-gray-700">{{ $application->land_use ? strtoupper($application->land_use) : 'N/A' }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Applicant Type</p>
                            <p class="mt-1 text-base text-gray-700">{{ $application->applicant_type ? ucwords(str_replace(['_', '-'], ' ', $application->applicant_type)) : 'N/A' }}</p>
                        </div>
                        @php
                            $statusValue = $application->planning_recommendation_status ?? 'Pending';
                            $statusKey = strtolower($statusValue);
                            $statusClasses = match ($statusKey) {
                                'approved' => 'bg-green-100 text-green-800 border-green-200',
                                'declined' => 'bg-red-100 text-red-700 border-red-200',
                                default => 'bg-yellow-100 text-yellow-800 border-yellow-200',
                            };
                        @endphp
                        <div>
                            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Planning Recommendation</p>
                            <span class="inline-flex items-center px-2.5 py-0.5 mt-1 rounded-full text-xs font-semibold border {{ $statusClasses }}">
                                {{ $statusValue }}
                            </span>
                        </div>
                        <div>
                            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Existing Scheme Plan No</p>
                            <p class="mt-1 text-base text-gray-700">{{ $application->scheme_no ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Date Captured</p>
                            <p class="mt-1 text-base text-gray-700">
                                @if(!empty($application->created_at))
                                    {{ \Carbon\Carbon::parse($application->created_at)->format('d M Y, h:i A') }}
                                @else
                                    —
                                @endif
                            </p>
                        </div>
                    </div>
                </div>

                <div class="bg-white border border-gray-200 rounded-lg shadow-sm">
                    <div class="border-b border-gray-200 px-6 py-4">
                        <h2 class="text-lg font-semibold text-gray-900">Survey References</h2>
                        <p class="text-sm text-gray-500 mt-1">Provide the survey identifiers associated with this application. All fields are required.</p>
                    </div>

                    <form id="primarySurveyForm"
                          data-survey-form
                          data-scheme-required="true"
                          data-return-url="{{ $returnUrl }}"
                          action="{{ route('sectionaltitling.saveApplicationData') }}"
                          method="POST"
                          class="px-6 py-6 space-y-6">
                        @csrf
                        <input type="hidden" name="redirect_to" value="{{ $returnUrl }}">
                        <input type="hidden" name="application_id" value="{{ $application->id }}">

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="relative">
                                <label for="lkn_number" class="block text-sm font-medium text-gray-700">LPKN Number <span class="text-red-600">*</span></label>
                                <div class="flex mt-1">
                                    <input type="text"
                                           id="lkn_number"
                                           name="lkn_number"
                                           value="{{ old('lkn_number', $formData['lkn_number'] ?? 'Peace of Land') }}"
                                           required
                                           class="block w-full rounded-l-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <button type="button"
                                            onclick="document.getElementById('lkn_number').value = ''"
                                            class="inline-flex items-center px-3 py-2 bg-red-600 text-white text-xs font-medium rounded-r-md border border-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                        Reset
                                    </button>
                                </div>
                            </div>
                            <div class="relative">
                                <label for="tp_plan_number" class="block text-sm font-medium text-gray-700">TP Plan Number <span class="text-red-600">*</span></label>
                                <div class="flex mt-1">
                                    <input type="text"
                                           id="tp_plan_number"
                                           name="tp_plan_number"
                                           value="{{ old('tp_plan_number', $formData['tp_plan_number'] ?? 'Peace of Land') }}"
                                           required
                                           class="block w-full rounded-l-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <button type="button"
                                            onclick="document.getElementById('tp_plan_number').value = ''"
                                            class="inline-flex items-center px-3 py-2 bg-red-600 text-white text-xs font-medium rounded-r-md border border-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                        Reset
                                    </button>
                                </div>
                            </div>
                            <div>
                                <label for="approved_plan_number" class="block text-sm font-medium text-gray-700">Approved Plan Number <span class="text-red-600">*</span></label>
                                <input type="text"
                                       id="approved_plan_number"
                                       name="approved_plan_number"
                                       value="{{ old('approved_plan_number', $formData['approved_plan_number']) }}"
                                       required
                                       class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label for="scheme_number" class="block text-sm font-medium text-gray-700">Scheme Plan No <span class="text-red-600">*</span></label>
                                <input type="text"
                                       id="scheme_number"
                                       name="scheme_number"
                                       data-scheme-field
                                       value="{{ old('scheme_number', $formData['scheme_plan_number']) }}"
                                       required
                                       class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                        </div>

                        <div class="flex items-center justify-end gap-3">
                            <a href="{{ $returnUrl }}" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                Cancel
                            </a>
                            <button type="submit" data-submit-btn class="inline-flex items-center px-5 py-2 bg-blue-600 text-white text-sm font-semibold rounded-md shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                <i data-lucide="save" class="w-4 h-4 mr-2"></i>
                                Save Survey Details
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        @include($footerPartial ?? 'admin.footer')
    </div>
@endsection

@section('scripts')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.querySelector('[data-survey-form]');
            if (!form) {
                return;
            }

            const submitBtn = form.querySelector('[data-submit-btn]');
            const schemeField = form.querySelector('[data-scheme-field]');
            const schemeRequired = form.dataset.schemeRequired === 'true';

            form.addEventListener('submit', async (event) => {
                event.preventDefault();

                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.classList.add('opacity-70', 'cursor-not-allowed');
                }
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: 'Saving survey details...',
                        allowOutsideClick: false,
                        showConfirmButton: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                }

                try {
                    const formData = new FormData(form);

                    if (!schemeRequired && schemeField && !schemeField.value.trim()) {
                        formData.delete(schemeField.name);
                    }

                    const response = await fetch(form.action, {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });

                    let payload = null;
                    try {
                        payload = await response.json();
                    } catch (error) {
                        // Ignore JSON parse errors here; handled below
                    }

                    if (typeof Swal !== 'undefined') {
                        Swal.close();
                    }

                    if (response.ok && payload && payload.success) {
                        const message = payload.message || 'Survey details saved successfully.';
                        const returnUrl = form.dataset.returnUrl;

                        if (typeof Swal !== 'undefined') {
                            await Swal.fire({
                                icon: 'success',
                                title: 'Saved!',
                                text: message,
                                timer: 1500,
                                showConfirmButton: false
                            });
                        } else {
                            alert(message);
                        }

                        if (returnUrl) {
                            window.location.href = returnUrl;
                        }
                    } else {
                        let errorMessage = payload?.message || payload?.error || 'Unable to save survey details.';
                        if (payload?.errors) {
                            const firstError = Object.values(payload.errors)[0];
                            if (Array.isArray(firstError) && firstError.length > 0) {
                                errorMessage = firstError[0];
                            } else if (typeof firstError === 'string') {
                                errorMessage = firstError;
                            }
                        }

                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                icon: 'error',
                                title: 'Unable to save survey details',
                                text: errorMessage
                            });
                        } else {
                            alert(errorMessage);
                        }
                    }
                } catch (error) {
                    if (typeof Swal !== 'undefined') {
                        Swal.close();
                        Swal.fire({
                            icon: 'error',
                            title: 'Unexpected error',
                            text: error.message || 'An unexpected error occurred while saving survey details.'
                        });
                    } else {
                        alert(error.message || 'An unexpected error occurred while saving survey details.');
                    }
                } finally {
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.classList.remove('opacity-70', 'cursor-not-allowed');
                    }
                }
            });

            @if(session('swal_success') && session('success'))
                Swal.fire({
                    icon: 'success',
                    title: 'Success',
                    text: {!! json_encode(session('success')) !!},
                    timer: 2000,
                    showConfirmButton: false
                });
            @endif

            @if(session('swal_error') && session('error'))
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: {!! json_encode(session('error')) !!}
                });
            @endif
        });
    </script>
@endsection
