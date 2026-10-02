@extends('layouts.app')

@section('page-title')
    {{ $PageTitle ?? __('Complete Survey Details (Unit)') }}
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
                    <a href="{{ $returnUrl }}" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                        <i data-lucide="arrow-left" class="w-4 h-4 mr-2"></i>
                        Back to Planning Recommendation
                    </a>
                </div>

                <div class="bg-white border border-gray-200 rounded-lg shadow-sm mb-6">
                    <div class="border-b border-gray-200 px-6 py-4">
                        <h2 class="text-lg font-semibold text-gray-900">Unit Summary</h2>
                    </div>
                    <div class="px-6 py-4 grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                        <div>
                            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Unit File Number</p>
                            <p class="mt-1 text-base text-gray-900 font-semibold">{{ $unitApplication->fileno ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">NP File Number</p>
                            <p class="mt-1 text-base text-gray-700">{{ $unitApplication->np_fileno ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Unit Type</p>
                            <p class="mt-1 text-base text-gray-700">{{ $unitApplication->unit_type ? strtoupper($unitApplication->unit_type) : 'N/A' }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Owner</p>
                            <p class="mt-1 text-base text-gray-900 font-semibold">{{ $ownerName }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Primary File Number</p>
                            <p class="mt-1 text-base text-gray-700">{{ $primaryApplication->fileno ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Primary Owner</p>
                            <p class="mt-1 text-base text-gray-700">{{ $primaryOwnerName ?? '—' }}</p>
                        </div>
                        @php
                            $statusValue = $unitApplication->planning_recommendation_status ?? 'Pending';
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
                            <p class="mt-1 text-base text-gray-700">{{ $unitApplication->scheme_no ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Date Captured</p>
                            <p class="mt-1 text-base text-gray-700">
                                @if(!empty($unitApplication->created_at))
                                    {{ \Carbon\Carbon::parse($unitApplication->created_at)->format('d M Y, h:i A') }}
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
                        <p class="text-sm text-gray-500 mt-1">Provide the survey identifiers associated with this unit application.</p>
                        @if(!$schemeRequired)
                            <div class="mt-2 inline-flex items-center rounded-md bg-blue-50 px-3 py-1 text-xs font-medium text-blue-700 border border-blue-200">
                                <i data-lucide="info" class="w-3 h-3 mr-1"></i>
                                Scheme Plan No is optional for SUA unit types.
                            </div>
                        @endif
                    </div>

                    <form id="unitSurveyForm"
                          data-survey-form
                          data-scheme-required="{{ $schemeRequired ? 'true' : 'false' }}"
                          data-return-url="{{ $returnUrl }}"
                          action="{{ route('programmes.planning.unit.save') }}"
                          method="POST"
                          class="px-6 py-6 space-y-6">
                        @csrf
                        <input type="hidden" name="redirect_to" value="{{ $returnUrl }}">
                        <input type="hidden" name="sub_application_id" value="{{ $unitApplication->id }}">
                        <input type="hidden" name="primary_id" value="{{ $primaryApplication->id ?? '' }}">

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="unit_lkn_number" class="block text-sm font-medium text-gray-700">LPKN Number <span class="text-red-600">*</span></label>
                                <div class="flex gap-2">
                                    <input type="text"
                                           id="unit_lkn_number"
                                           name="lkn_number"
                                           value="{{ old('lkn_number', $formData['lkn_number'] ?? 'Peace of Land') }}"
                                           required
                                           class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-green-500 focus:outline-none focus:ring-2 focus:ring-green-500">
                                    <button type="button" 
                                            onclick="document.getElementById('unit_lkn_number').value = 'Peace of Land'"
                                            class="mt-1 px-3 py-2 bg-red-600 text-white text-sm font-medium rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                        Reset
                                    </button>
                                </div>
                            </div>
                            <div>
                                <label for="unit_tp_plan_number" class="block text-sm font-medium text-gray-700">TP Plan Number <span class="text-red-600">*</span></label>
                                <div class="flex gap-2">
                                    <input type="text"
                                           id="unit_tp_plan_number"
                                           name="tp_plan_number"
                                           value="{{ old('tp_plan_number', $formData['tp_plan_number'] ?? 'Peace of Land') }}"
                                           required
                                           class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-green-500 focus:outline-none focus:ring-2 focus:ring-green-500">
                                    <button type="button" 
                                            onclick="document.getElementById('unit_tp_plan_number').value = 'Peace of Land'"
                                            class="mt-1 px-3 py-2 bg-red-600 text-white text-sm font-medium rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                        Reset
                                    </button>
                                </div>
                            </div>
                            <div>
                                <label for="unit_approved_plan_number" class="block text-sm font-medium text-gray-700">Approved Plan Number <span class="text-red-600">*</span></label>
                                <input type="text"
                                       id="unit_approved_plan_number"
                                       name="approved_plan_number"
                                       value="{{ old('approved_plan_number', $formData['approved_plan_number']) }}"
                                       required
                                       class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-green-500 focus:outline-none focus:ring-2 focus:ring-green-500">
                            </div>
                            <div>
                                <label for="unit_scheme_plan_number" class="block text-sm font-medium text-gray-700">
                                    Scheme Plan No
                                    @if($schemeRequired)
                                        <span class="text-red-600">*</span>
                                    @else
                                        <span class="text-gray-400 text-xs font-normal">(Optional)</span>
                                    @endif
                                </label>
                                <input type="text"
                                       id="unit_scheme_plan_number"
                                       name="scheme_plan_number"
                                       data-scheme-field
                                       value="{{ old('scheme_plan_number', $formData['scheme_plan_number']) }}"
                                       @if($schemeRequired) required @endif
                                       class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-green-500 focus:outline-none focus:ring-2 focus:ring-green-500">
                            </div>
                        </div>

                        <div class="flex items-center justify-end gap-3">
                            <a href="{{ $returnUrl }}" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                Cancel
                            </a>
                            <button type="submit" data-submit-btn class="inline-flex items-center px-5 py-2 bg-green-600 text-white text-sm font-semibold rounded-md shadow-sm hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
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
