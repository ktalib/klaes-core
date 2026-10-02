@extends('layouts.app')

@section('page-title')
    {{ __('View Instrument') }}
@endsection

@section('content')
    @include('instruments.create.css')
    <!-- Main Content -->
    <div class="flex-1 overflow-auto">
        <!-- Header -->
        @include('admin.header')

        <div class="p-6">
            <div class="max-w-7xl mx-auto">
                <div class="flex items-center justify-between mb-8">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-800">View Instrument</h1>
                        <p class="text-gray-500 mt-1">Details for registration {{ $record->instrument_type }}
                            ({{ $record->mlsFNo ?: $record->kangisFileNo ?: $record->NewKANGISFileno ?: $record->temp_fileno }})
                        </p>
                    </div>
                    <div class="flex items-center gap-3">
                        <a href="{{ route('instruments.edit', $record->id) }}"
                            class="flex items-center gap-2 px-4 py-2 bg-blue-50 border border-blue-200 rounded-lg text-sm font-medium text-blue-600 hover:bg-blue-100 transition-all">
                            <i data-lucide="edit-3" class="w-4 h-4"></i>
                            Edit Record
                        </a>
                        <a href="{{ route('instruments.index') }}"
                            class="flex items-center gap-2 px-4 py-2 bg-white border border-gray-200 rounded-lg text-sm font-medium text-gray-600 hover:bg-gray-50 transition-all">
                            <i data-lucide="arrow-left" class="w-4 h-4"></i>
                            Back to List
                        </a>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-8 text-center">
                    <div class="max-w-md mx-auto">
                        <div
                            class="w-16 h-16 bg-blue-50 text-blue-600 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i data-lucide="eye" class="w-8 h-8"></i>
                        </div>
                        <h2 class="text-xl font-bold text-gray-800 mb-2">Viewing Record</h2>
                        <p class="text-gray-500 mb-6">You are viewing the instrument record with ID: {{ $record->id }}. The
                            details will be displayed in the modal below.</p>

                        <button type="button" onclick="openViewDialog()"
                            class="px-6 py-3 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 transition-all shadow-md inline-flex items-center gap-2">
                            <i data-lucide="external-link" class="w-5 h-5"></i>
                            Open Details Form
                        </button>
                    </div>
                </div>

                <!-- Instrument Registration Form Dialog (Hidden by default, opened via JS) -->
                @php $isView = true; @endphp
                @include('instruments.partials.register_modal')

                @include('components.global-fileno-modal')

                <!-- Templates for Instrument Types -->
                <template id="template-power-of-attorney">
                    @include('instruments.partials.types.simple-duration')
                </template>
                <template id="template-irrevocable-power-of-attorney">
                    @include('instruments.partials.types.simple-duration')
                </template>
                <template id="template-occupancy-permit">
                    @include('instruments.partials.types.simple-duration')
                </template>
                <template id="template-deed-of-mortgage">
                    @include('instruments.partials.types.deed-of-mortgage')
                </template>
                <template id="template-tripartite-mortgage">
                    @include('instruments.partials.types.tripartite-mortgage')
                </template>
                <template id="template-deed-of-assignment">
                    @include('instruments.partials.types.deed-of-assignment')
                </template>
                <template id="template-deed-of-lease">
                    @include('instruments.partials.types.deed-of-lease')
                </template>
                <template id="template-deed-of-sub-lease">
                    @include('instruments.partials.types.deed-of-sub-lease')
                </template>
                <template id="template-deed-of-sub-division">
                    @include('instruments.partials.types.deed-of-sub-division')
                </template>
                <template id="template-deed-of-merger">
                    @include('instruments.partials.types.deed-of-merger')
                </template>
                <template id="template-deed-of-surrender-release">
                    @include('instruments.partials.types.deed-of-surrender-release')
                </template>
                <template id="template-devolution-order">
                    @include('instruments.partials.types.devolution-order')
                </template>
                <template id="template-deed-of-gift">
                    @include('instruments.partials.types.deed-of-gift')
                </template>
                <!-- Sidebar Templates -->
                <template id="template-tripartite-mortgage-sidebar">
                    @include('instruments.partials.types.sidebars.tripartite-mortgage')
                </template>
                <template id="template-deed-of-surrender-release-sidebar">
                    @include('instruments.partials.types.sidebars.deed-of-surrender-release')
                </template>
            </div>
        </div>
    </div>

    {{-- Scripts --}}
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="{{ asset('js/global-fileno-modal.js') }}"></script>
    <script src="{{ asset('js/instruments-capture.js') }}?v={{ time() }}"></script>

    <script>
        function openViewDialog() {
            const instrumentType = "{{ $record->instrument_type }}";
            // Trigger the registration dialog with the existing instrument type
            if (typeof openRegistrationDialog === 'function') {
                openRegistrationDialog(instrumentType);

                // Customize labels/title for view mode
                const titleEl = document.getElementById('dialog-title');
                const subtitleEl = document.getElementById('dialog-subtitle');
                const submitBtn = document.getElementById('submit-btn');
                const registrationForm = document.getElementById('registration-form');

                if (titleEl) titleEl.innerText = 'View Instrument Details';
                if (subtitleEl) subtitleEl.innerText = 'Viewing details for ' + instrumentType;

                // Make all inputs read-only
                if (registrationForm) {
                    const inputs = registrationForm.querySelectorAll('input, select, textarea');
                    inputs.forEach(input => {
                        input.disabled = true;
                        input.classList.add('bg-gray-50', 'border-gray-100');
                    });
                }

                // Hide submit button in view mode
                if (submitBtn) submitBtn.style.display = 'none';

                // Re-initialize icons
                if (typeof lucide !== 'undefined') lucide.createIcons();
            }
        }

        document.addEventListener('DOMContentLoaded', function () {
            // Auto-open after a small delay to ensure JS is ready
            setTimeout(openViewDialog, 500);

            // Override cancel button behavior to go back to list
            const cancelBtn = document.getElementById('cancel-btn');
            if (cancelBtn) {
                cancelBtn.addEventListener('click', function () {
                    window.location.href = "{{ route('instruments.index') }}";
                });
            }
        });
    </script>
@endsection