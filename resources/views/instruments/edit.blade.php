@extends('layouts.app')

@section('page-title')
    {{ __('Edit Instrument') }}
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
                        <h1 class="text-2xl font-bold text-gray-800">Edit Instrument</h1>
                        <p class="text-gray-500 mt-1">Update registration details for {{ $record->instrument_type }}
                            ({{ $record->mlsFNo ?: $record->kangisFileNo ?: $record->NewKANGISFileno ?: $record->temp_fileno }})
                        </p>
                    </div>
                    <a href="{{ route('instruments.index') }}"
                        class="flex items-center gap-2 px-4 py-2 bg-white border border-gray-200 rounded-lg text-sm font-medium text-gray-600 hover:bg-gray-50 transition-all">
                        <i data-lucide="arrow-left" class="w-4 h-4"></i>
                        Back to List
                    </a>
                </div>

                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-8 text-center">
                    <div class="max-w-md mx-auto">
                        <div
                            class="w-16 h-16 bg-blue-50 text-blue-600 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i data-lucide="edit-3" class="w-8 h-8"></i>
                        </div>
                        <h2 class="text-xl font-bold text-gray-800 mb-2">Editing Record</h2>
                        <p class="text-gray-500 mb-6">You are editing the instrument record with ID: {{ $record->id }}. The
                            edit form will open automatically.</p>

                        <button type="button" onclick="openEditDialog()"
                            class="px-6 py-3 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 transition-all shadow-md inline-flex items-center gap-2">
                            <i data-lucide="external-link" class="w-5 h-5"></i>
                            Open Edit Form
                        </button>
                    </div>
                </div>

                <!-- Instrument Registration Form Dialog (Hidden by default, opened via JS) -->
                @php $isEdit = true; @endphp
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
                <template id="template-deed-of-mortgage-sidebar">
                    @include('instruments.partials.types.sidebars.tripartite-mortgage')
                </template>
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
    <!-- Paints the Deeds workflow strip beside the file number. -->
    <script src="{{ asset('js/deeds-pipeline.js') }}?v={{ @filemtime(public_path('js/deeds-pipeline.js')) }}"></script>

    <script>
        // Pass the record data to JS
        window.editRecordData = @json($record);

        function openEditDialog() {
            const instrumentType = "{{ $record->instrument_type }}";
            // Trigger the registration dialog with the existing instrument type
            if (typeof openRegistrationDialog === 'function') {
                // Determine the key for the instrument type map
                // The map keys are lower-kebab-case (e.g., 'deed-of-assignment')
                // The DB value might correspond to the name or ID. 
                // We'll try to match it or pass it directly if it matches the ID format.
                // Assuming $record->instrument_type matches the 'name' or 'id' in JS map.
                // It's safer to let openRegistrationDialog handle the loop if passed a name, 
                // OR we pass the specific ID if we know it.
                // For now, passing the exact DB value. openRegistrationDialog iterates to find a match.

                openRegistrationDialog(instrumentType);

                // Customize labels/title for edit mode
                const titleEl = document.getElementById('dialog-title');
                const subtitleEl = document.getElementById('dialog-subtitle');
                const submitBtn = document.getElementById('submit-btn');

                if (titleEl) titleEl.innerText = 'Edit Instrument';
                if (subtitleEl) subtitleEl.innerText = 'Update details for ' + instrumentType;
                if (submitBtn) {
                    submitBtn.innerHTML = '<i data-lucide="save" class="h-4 w-4"></i> Update Instrument';
                    // Ensure form action is update
                    const form = document.getElementById('registration-form');
                    // The action URL is set in populateForm or we can set it here if needed
                }

                // Populate the form with data
                if (typeof populateForm === 'function' && window.editRecordData) {
                    // Slight delay to ensure DOM update from openRegistrationDialog (if it does async template injection)
                    setTimeout(() => {
                        populateForm(window.editRecordData);
                    }, 100);
                }

                // Re-initialize icons
                if (typeof lucide !== 'undefined') lucide.createIcons();
            }
        }

        document.addEventListener('DOMContentLoaded', function () {
            // Auto-open after a small delay to ensure JS is ready
            setTimeout(openEditDialog, 500);

            // Override cancel button behavior to go back to list
            const cancelBtn = document.getElementById('cancel-btn');
            if (cancelBtn) {
                cancelBtn.replaceWith(cancelBtn.cloneNode(true)); // Remove old listeners
                document.getElementById('cancel-btn').addEventListener('click', function () {
                    window.location.href = "{{ route('instruments.index') }}";
                });
            }
            // Also handle the "X" close button
            const closeBtn = document.getElementById('dialog-close-btn');
            if (closeBtn) {
                closeBtn.addEventListener('click', function () {
                    window.location.href = "{{ route('instruments.index') }}";
                });
            }
        });
    </script>
@endsection