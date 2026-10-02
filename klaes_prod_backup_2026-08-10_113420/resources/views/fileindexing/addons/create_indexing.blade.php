@extends('layouts.app')

@section('page-title')
    {{ isset($record) ? __('Update File Index') : __('Create File Index') }}
@endsection

@section('content')
    <link rel="stylesheet" href="{{ asset('css/fileindexing/create-indexing-standalone.css') }}">
        <link rel="stylesheet" href="{{ asset('css/fileindexing/extra.css') }}">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">

    <div class="flex-1 overflow-auto">
        <!-- Header -->
        @include('admin.header')
        <!-- Dashboard Content -->
        <div class="p-6">

 
            <div class="form-shell">
                <!-- Standalone form content without modal wrapper -->
                <div class="bg-white rounded-lg shadow-lg p-8 max-w-6xl mx-auto">
                    @if(!empty($backButton))
                        <div class="mb-5">
                            <a href="{{ $backButton['route'] }}"
                                class="inline-flex items-center gap-2 rounded-md border border-blue-300 bg-blue-50 px-4 py-2 text-sm font-semibold text-blue-700 transition-colors hover:border-blue-400 hover:bg-blue-100 hover:text-blue-900">
                                <i data-lucide="arrow-left" class="h-4 w-4"></i>
                                <span>{{ $backButton['label'] }}</span>
                            </a>
                        </div>
                    @endif
                    <div class="mb-6">
                        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 mb-2">
                            <div>
                                <div class="flex items-center gap-3 mb-2">
                                    <i data-lucide="{{ isset($record) ? 'edit-3' : 'file-plus' }}"
                                        class="h-5 w-5 text-blue-600"></i>
                                    <h2 class="text-xl font-semibold text-gray-800">
                                        {{ isset($record) ? 'Update File Index' : 'Create New File Index' }}
                                    </h2>
                                </div>
                                <p class="text-gray-600">
                                    {{ isset($record) ? 'Update the file indexing details below' : 'Enter the details for the new file to be indexed' }}
                                </p>
                            </div>
                        </div>
                        @if(isset($record))
                            <div class="mt-2 px-3 py-2 bg-blue-50 border border-blue-200 rounded-md">
                                <p class="text-sm text-blue-800">
                                    <span class="font-semibold">Editing:</span> {{ $record->file_number ?? 'Unknown' }} -
                                    {{ $record->file_title ?? 'No Title' }}
                                </p>
                            </div>
                        @endif
                    </div>

                    <form id="new-file-form" data-default-indexer="{{ Auth::user()->name ?? 'Current User' }}"
                        data-check-indexed-url="{{ route('fileindex.check-indexed') }}"
                        data-indexed-view-url-template="{{ route('fileindexing.show', ['id' => '__ID__']) }}"
                        data-transactions-url-template="{{ route('fileindexing.transactions', ['fileIndexing' => '__ID__']) }}"
                        @if(isset($record)) data-editing-id="{{ $record->id }}"
                        data-action-url="{{ route('fileindexing.update', $record->id) }}" data-method="PUT" @else
                        data-action-url="{{ route('fileindexing.store') }}" data-method="POST" @endif
                        x-data="indexingForm()"
                        @grouping-record-updated.window="onGroupingRecordUpdated($event.detail)">
                        @csrf
                        @if(isset($record))
                            @method('PUT')
                            <input type="hidden" name="record_id" value="{{ $record->id }}">
                        @endif
                        <input type="hidden" value="PRO1.2" name="test_control">
                        <input type="hidden" id="prop-id-field" name="prop_id" value="">

                        @if(isset($record))
                            <!-- Pre-populate form with existing data -->
                            <script>
                                window.editingRecord = @json($record);
                                window.isEditMode = true;
                                window.isNewKnMode = false;
                                window.prefillFileNumber = '';
                                window.returnToUrl = '';
                            </script>
                        @else
                            <script>
                                window.editingRecord = null;
                                window.isEditMode = false;
                                window.isNewKnMode = {{ ($isNewKnMode ?? false) ? 'true' : 'false' }};
                                window.prefillFileNumber = @json($prefillFileNumber ?? '');
                                window.prefillTrackingId = @json($prefillTrackingId ?? '');
                                window.returnToUrl = @json($returnTo ?? '');
                            </script>
                        @endif

                        <!-- Basic Information Cards (2x2 Grid) -->
                        <div class="form-grid-2">
                            <!-- Indexing Type Card -->
                            <div class="rounded-lg border border-gray-200 bg-white p-6">
                                <h4 class="mb-6 flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-gray-700">
                                    <i data-lucide="layers" class="h-4 w-4 text-indigo-600"></i>
                                    Indexing Type
                                </h4>
                                <div class="form-group">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Indexing Type <span class="text-red-500">*</span></label>
                                    <select id="indexing-type" name="indexing_type"
                                        class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                        x-model="indexing_type"
                                        required>
                                        <option value="Regular" {{ (isset($record) && $record->indexing_type == 'Regular') ? 'selected' : '' }}>Regular Indexing</option>
                                        <option value="Block" {{ (isset($record) && $record->indexing_type == 'Block') ? 'selected' : '' }}>Block Indexing</option>
                                    </select>
                                </div>

                                {{-- Title Status (optional) — mirrors the standalone Title Status module.
                                     Hidden entirely for SLTR / KANGIS / DCIV registries (see
                                     handleRegistryFieldToggling in create-indexing-dialog.js). --}}
                                <div class="form-group mt-6" id="ts-title-status-block">

                                    <label class="block text-sm font-medium text-gray-700 mb-2">Title Status <span class="text-red-500">*</span></label>
                                    @php
                                        // Left group: Parcel Update (emerald). Right group: Title Status Update (indigo).
                                        $parcelOptions = [
                                            'Subdivision'       => ['Plot Subdivision', 'split'],
                                            'Merger'            => ['Plot Merger', 'merge'],
                                            'Extension'         => ['Extension', 'scaling'],
                                            'Separation'        => ['Plot Separation', 'scissors'],
                                            'Change of Purpose' => ['Change of Purpose', 'repeat'],
                                            'Change of Name'    => ['Change of Name', 'user-pen'],
                                        ];
                                        $titleOptions = [
                                            'Withdrawal (Application)'                            => ['Withdrawal (Application)', 'undo-2'],
                                            'Cancellation'                                 => ['Cancellation', 'x-circle'],
                                            'Revoke'                                       => ['Revocation', 'ban'],
                                            'Litigation'                                          => ['Litigation', 'gavel'],
                                            'Amendment/Reconsideration (Application/RofO/CofO)'   => ['Amendment / Reconsideration', 'file-pen'],
                                            'Surrender'                                           => ['Surrender', 'flag'],
                                            'Re-grant'                                            => ['Re-grant', 'refresh-cw'],
                                            'Resettlement'                                        => ['Resettlement', 'home'],
                                            'Closed'                                              => ['Closed', 'lock'],
                                        ];
                                    @endphp
                                    <div id="ts-title-type-group">
                                     

                                        {{-- Parcel Update (emerald) --}}
                                        <h5 class="mb-2 flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-emerald-700">
                                            <i data-lucide="land-plot" class="h-3.5 w-3.5"></i>
                                            Parcel Update
                                        </h5>

                                           {{-- Normal --}}
                                        <div class="mb-3">
                                            <label class="block cursor-pointer">
                                                <input type="checkbox" value="Normal"
                                                    class="ts-title-type-cb peer sr-only">
                                                <span class="flex items-center justify-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-600 transition hover:border-gray-400 hover:bg-gray-50 peer-checked:border-gray-700 peer-checked:bg-gray-100 peer-checked:text-gray-800 peer-checked:font-medium peer-focus:ring-2 peer-focus:ring-gray-200">
                                                    <i data-lucide="check-circle" class="h-4 w-4 flex-shrink-0"></i>
                                                    <span class="leading-tight">Normal</span>
                                                </span>
                                            </label>
                                        </div>

                                        {{-- File classification flags (amber) — multi-select. Mutually exclusive
                                             only with "Normal". Their auto comment is appended to the shared
                                             Title Status "Remark / Comment" box below. Persisted to
                                             file_indexings.file_classification (+ _remarks). --}}
                                        @php
                                            $fcSelected = isset($record) ? array_filter(array_map('trim', explode(',', (string) $record->file_classification))) : [];
                                        @endphp
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mb-3">
                                            <label class="block cursor-pointer">
                                                <input type="checkbox" value="Dummy File"
                                                    class="ts-title-type-cb ts-classification-cb peer sr-only"
                                                    {{ in_array('Dummy File', $fcSelected) ? 'checked' : '' }}>
                                                <span class="flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-600 transition hover:border-amber-300 hover:bg-amber-50/40 peer-checked:border-amber-500 peer-checked:bg-amber-50 peer-checked:text-amber-700 peer-checked:font-medium peer-focus:ring-2 peer-focus:ring-amber-200">
                                                    <i data-lucide="file-question" class="h-4 w-4 flex-shrink-0"></i>
                                                    <span class="leading-tight">Dummy File</span>
                                                </span>
                                            </label>
                                            <label class="block cursor-pointer">
                                                <input type="checkbox" value="Temporary"
                                                    class="ts-title-type-cb ts-classification-cb peer sr-only"
                                                    {{ in_array('Temporary', $fcSelected) ? 'checked' : '' }}>
                                                <span class="flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-600 transition hover:border-amber-300 hover:bg-amber-50/40 peer-checked:border-amber-500 peer-checked:bg-amber-50 peer-checked:text-amber-700 peer-checked:font-medium peer-focus:ring-2 peer-focus:ring-amber-200">
                                                    <i data-lucide="clock" class="h-4 w-4 flex-shrink-0"></i>
                                                    <span class="leading-tight">Temporary</span>
                                                </span>
                                            </label>
                                        </div>

                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                            @foreach($parcelOptions as $tsValue => $tsMeta)
                                                <label class="block cursor-pointer">
                                                    <input type="checkbox" value="{{ $tsValue }}"
                                                        class="ts-title-type-cb peer sr-only">
                                                    <span class="flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-600 transition hover:border-emerald-300 hover:bg-emerald-50/40 peer-checked:border-emerald-500 peer-checked:bg-emerald-50 peer-checked:text-emerald-700 peer-checked:font-medium peer-focus:ring-2 peer-focus:ring-emerald-200">
                                                        <i data-lucide="{{ $tsMeta[1] }}" class="h-4 w-4 flex-shrink-0"></i>
                                                        <span class="leading-tight"@if($tsValue === 'Extension') id="ts-extension-label"@endif>{{ $tsMeta[0] }}</span>
                                                    </span>
                                                </label>
                                            @endforeach
                                        </div>
                                        {{-- Holds the chosen extension kind (Plot Extension / File Extension),
                                             selected via popup when the "Extension" option is ticked. --}}
                                        <input type="hidden" id="ts-extension-kind" value="">
                                    </div>
                                    <p class="text-[11px] text-gray-400 mt-1.5">Select one or more title statuses.</p>
                                </div>
                            </div>

                            <!-- General Registry--> 
                            <div class="rounded-lg border border-gray-200 bg-white p-6">
                                <h4 class="mb-6 flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-gray-700">
                                    <i data-lucide="building-2" class="h-4 w-4 text-purple-600"></i>
                                    General Registry
                                </h4>
                                <div class="form-group">
                                    <label for="general-registry" class="block text-sm font-medium text-gray-700 mb-2">General Registry <span class="text-red-500">*</span></label>
                                    <select id="general-registry"
                                        class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                        name="general_registry" required>
                                        <option value="">Select Registry</option>
                                        @if(isset($registries))
                                            @foreach($registries as $registry)
                                                <option value="{{ $registry->name }}" {{ (isset($record) && $record->general_registry == $registry->name) ? 'selected' : '' }}>
                                                    {{ $registry->name }}
                                                </option>
                                            @endforeach
                                        @endif
                                    </select>
                                </div>

                                <!-- Reason for Investigation (DCIV) - Conditional Display -->
                                <div class="form-group mt-6 hidden" id="dciv-reason-container">
                                    <label for="dciv-reason" class="block text-sm font-medium text-gray-700 mb-2">
                                        <i data-lucide="alert-circle" class="inline h-4 w-4 text-red-600"></i>
                                        Reason for Investigation
                                    </label>
                                    <input type="text" id="dciv-reason" name="dciv_reason"
                                        class="block w-full px-3 py-2 border border-red-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-red-500 focus:border-red-500 sm:text-sm bg-red-50"
                                        placeholder="Enter reason for investigation" value="{{ isset($record) ? $record->dciv_reason : '' }}">
                                </div>

                                {{-- Title Status Update (indigo) — radio buttons for single selection --}}
                                <div class="form-group mt-6" id="ts-title-update-group">
                                    <h5 class="mb-2 flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-indigo-700">
                                        <i data-lucide="badge-check" class="h-3.5 w-3.5"></i>
                                        Title Status Update
                                    </h5>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                        @foreach($titleOptions as $tsValue => $tsMeta)
                                            <label class="block cursor-pointer">
                                                <input type="radio" name="ts-title-type" value="{{ $tsValue }}"
                                                    class="ts-title-type-cb peer sr-only">
                                                <span class="flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-600 transition hover:border-indigo-300 hover:bg-indigo-50/40 peer-checked:border-indigo-500 peer-checked:bg-indigo-50 peer-checked:text-indigo-700 peer-checked:font-medium peer-focus:ring-2 peer-focus:ring-indigo-200">
                                                    <i data-lucide="{{ $tsMeta[1] }}" class="h-4 w-4 flex-shrink-0"></i>
                                                    <span class="leading-tight"@if($tsValue === 'Re-grant') id="ts-regrant-label"@elseif($tsValue === 'Closed') id="ts-closed-label"@elseif($tsValue === 'Resettlement') id="ts-resettlement-label"@endif>{{ $tsMeta[0] }}</span>
                                                </span>
                                            </label>
                                        @endforeach
                                    </div>
                                    {{-- Holds the chosen re-grant direction (Re-granted From / Re-granted To),
                                         selected via popup when "Re-grant" is picked. "From" means this file is
                                         the new one and Initial FileNo is the old number; "To" means this file
                                         is the old one and the picked number is its replacement. --}}
                                    <input type="hidden" id="ts-regrant-kind" value="">
                                    {{-- Holds the chosen closure direction (Closed To / Continued From).
                                         "Closed To" means this file is being closed and the picked number
                                         is the file it continues in; "Continued From" means this file is
                                         the continuation and the picked number is the closed file. --}}
                                    <input type="hidden" id="ts-closed-kind" value="">
                                    {{-- Holds the chosen resettlement direction (Resettled From /
                                         Resettled To), same From/To sense as Re-grant. --}}
                                    <input type="hidden" id="ts-resettlement-kind" value="">
                                </div>
                            </div>
                        </div>

                        {{-- Title Status details (shown when a Title Status is selected) --}}
                        <div id="ts-status-fields" class="hidden mt-6 rounded-lg border border-teal-200 bg-teal-50/40 p-6 space-y-5">
                           

                            {{-- See (Additional File No.) — Re-grant only (OPTIONAL) --}}
                            <div id="ts-see-row" class="form-group hidden">
                                <input type="hidden" id="ts-see-fileno">
                                <label class="block text-sm font-medium text-gray-700 mb-2"><span id="ts-see-label">Initial FileNo</span> <span class="text-xs font-normal text-gray-500">(Optional)</span></label>
                                <div class="flex items-stretch gap-2">
                                    <input type="text" id="ts-see-fileno-display" readonly
                                        class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm bg-gray-50 sm:text-sm"
                                        placeholder="Click Select to choose the additional file number...">
                                    <button type="button" id="ts-see-select-btn"
                                        class="px-4 py-2 rounded-md bg-teal-600 text-white text-sm font-semibold hover:bg-teal-700 transition">Select</button>
                                    <button type="button" id="ts-see-clear-btn"
                                        class="px-3 py-2 rounded-md border border-gray-300 text-sm text-gray-600 hover:bg-gray-50 transition">Clear</button>
                                </div>
                                <p class="text-[11px] text-gray-400 mt-1" id="ts-see-hint">The related file number written as "See ..." on the physical file. This field is optional.</p>
                            </div>

                            {{-- Reason. Hidden for the statuses whose remark is a fixed sentence
                                 about the counterpart file (Re-grant / Resettlement / Closed) and
                                 for the classification flags — none of them quote a reason. --}}
                            <div class="form-group" id="ts-reason-row">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Reason</label>
                                <textarea id="ts-reason" rows="2"
                                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-teal-500 focus:border-teal-500 sm:text-sm"
                                    placeholder="Reason for this title status action..."></textarea>
                            </div>

                            {{-- Remark / Comment --}}
                            <div class="form-group">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Remark / Comment</label>
                                <textarea id="ts-remark" rows="3"
                                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-teal-500 focus:border-teal-500 sm:text-sm"></textarea>
                            </div>
                        </div>



                        @include('fileindexing.addons.partials.sections.file_identification')

                        <hr class="my-8 border-t border-gray-200">

                        <!-- Personal & Property Details Cards (2x2 Grid) -->
                        <div class="form-grid-2">
                            @include('fileindexing.addons.partials.sections.contact_information')
                            @include('fileindexing.addons.partials.sections.property_details')
                        </div>

                        <!-- Property Location Map (full-width, dedicated card) -->
                        <div class="mt-6">
                            @include('fileindexing.addons.partials.sections.property_map')
                        </div>

                        <hr class="my-8 border-t border-gray-200">

                        @include('fileindexing.addons.partials.sections.file_flags')

                        <hr class="my-8 border-t border-gray-200">

                        @include('fileindexing.addons.partials.sections.file_archive_details')
                        <hr class="my-8 border-t border-gray-200">

                        @include('fileindexing.addons.partials.sections.entity_customer')

                        <hr class="my-8 border-t border-gray-200">

                        @include('fileindexing.addons.partials.sections.billing_ground_rent')

                        <hr class="my-8 border-t border-gray-200">

                        @include('fileindexing.addons.partials.sections.occupancy_permit')

                        <hr class="my-8 border-t border-gray-200">

                        @include('fileindexing.addons.partials.sections.rofo_details')

                        <hr class="my-8 border-t border-gray-200">

                        @include('fileindexing.addons.partials.sections.cofo_details')

                        <hr class="my-8 border-t border-gray-200">

                        @include('fileindexing.addons.partials.sections.file_history')
                        @include('fileindexing.addons.partials.sections.related_fileno_details')
                        @include('fileindexing.addons.partials.sections.form_actions')
                    </form>
                </div>
            </div>
        </div>
    </div>

    @include('components.global-fileno-modal')
    @include('fileindexing.partial.property_transaction_modal')

    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="{{ asset('js/global-fileno-modal.js') }}"></script>
    <script src="{{ asset('js/fileindexing/create-indexing-dialog.js') }}?v={{ @filemtime(public_path('js/fileindexing/create-indexing-dialog.js')) }}"></script>

    <script>
        // Phone number validation
        (function () {
            const phoneInput = document.getElementById('phone');
            const phoneError = document.getElementById('phone-error');

            if (!phoneInput) return;

            function validateNigerianPhone() {
                const phoneNumber = phoneInput.value.trim();

                if (phoneNumber) {
                    // Nigerian phone numbers should be 11 digits starting with 0
                    if (!/^0\d{10}$/.test(phoneNumber)) {
                        phoneError.textContent = 'Nigerian phone numbers must be 11 digits starting with 0 (e.g., 08012345678)';
                        phoneError.classList.remove('hidden');
                        phoneInput.classList.add('border-red-500');
                        return false;
                    } else {
                        phoneError.classList.add('hidden');
                        phoneInput.classList.remove('border-red-500');
                        return true;
                    }
                } else {
                    phoneError.classList.add('hidden');
                    phoneInput.classList.remove('border-red-500');
                    return true;
                }
            }

            // Only allow numeric input
            phoneInput.addEventListener('input', function (e) {
                this.value = this.value.replace(/[^0-9]/g, '');
                validateNigerianPhone();
            });

            phoneInput.addEventListener('blur', validateNigerianPhone);

            // Validate before form submission
            const form = document.getElementById('new-file-form');
            if (form) {
                form.addEventListener('submit', function (e) {
                    // File Title required validation (covers one or more file_title[] inputs)
                    const titleInputs = form.querySelectorAll('input[name="file_title[]"]');
                    let firstEmptyTitle = null;
                    titleInputs.forEach(input => {
                        if (!firstEmptyTitle && !input.value.trim()) {
                            firstEmptyTitle = input;
                        }
                    });
                    if (firstEmptyTitle) {
                        e.preventDefault();
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                icon: 'warning',
                                title: 'File Title Required',
                                text: 'Please enter a File Title before submitting.',
                                confirmButtonColor: '#f59e0b'
                            }).then(() => {
                                firstEmptyTitle.focus();
                                firstEmptyTitle.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            });
                        } else {
                            firstEmptyTitle.focus();
                            firstEmptyTitle.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        }
                        return;
                    }

                    // NOTE: the "Apply & Pin on Map" coordinate requirement is enforced in
                    // submitFileIndexingForm() (create-indexing-dialog.js), which actually blocks
                    // submission — a preventDefault() here does not stop that handler's fetch.
                    // That check is currently OFF (REQUIRE_LOCATION_PIN = false) while the Google
                    // Cloud billing account is suspended.

                    if (!validateNigerianPhone() && phoneInput.value.trim()) {
                        e.preventDefault();
                        phoneInput.focus();
                        phoneInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                });
            }
        })();

        // File Type and RC NO conditional display
        (function () {
            const fileTypeSelect = document.getElementById('file-type');
            const rcNoGroup = document.getElementById('rc-no-group');
            const rcNoInput = document.getElementById('rc-no');

            if (!fileTypeSelect || !rcNoGroup) return;

            function toggleRcNo() {
                if (fileTypeSelect.value === 'Corporate') {
                    rcNoGroup.style.display = 'block';
                } else {
                    rcNoGroup.style.display = 'none';
                    if (rcNoInput) rcNoInput.value = '';
                }
            }

            fileTypeSelect.addEventListener('change', toggleRcNo);
            toggleRcNo(); // Initial check
        })();

        // NIN validation (13 digits)
        (function () {
            const ninInput = document.getElementById('nin');
            if (!ninInput) return;

            ninInput.addEventListener('input', function (e) {
                this.value = this.value.replace(/[^0-9]/g, '').substring(0, 13);
            });
        })();
    </script>
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('indexingForm', () => ({
                fileParams: [
                    {
                        id: Date.now(),
                        title: '',
                        applicant_name: '',
                        gender: '',
                        plot_number: '',
                        district: '',
                        custom_district: '',
                        lga: '',
                        location: '',
                        latitude: null,
                        longitude: null,
                        land_use_type: '',
                        tp_number: '',
                        lpkn_no: '',
                        plot_size: '',
                        customer_name: '',
                        entity_name: '',
                        street_name: '',
                        custom_street_name: ''
                    }
                ],
                activeTab: 0,
                indexing_type: '{{ isset($record) ? $record->indexing_type : 'Regular' }}',
                applyPropertyToAll: false,
                applyPersonalToAll: false,
                applyEntityToAll: false,

                addFileParam() {
                    this.fileParams.push({
                        id: Date.now() + Math.random(),
                        title: '',
                        applicant_name: '',
                        gender: '',
                        plot_number: '',
                        district: '',
                        custom_district: '',
                        lga: '',
                        location: '',
                        latitude: null,
                        longitude: null,
                        land_use_type: '',
                        tp_number: '',
                        lpkn_no: '',
                        plot_size: '',
                        customer_name: '',
                        entity_name: '',
                        street_name: '',
                        custom_street_name: ''
                    });
                },

                removeFileParam(index) {
                    if (this.fileParams.length > 1) {
                        this.fileParams.splice(index, 1);
                        if (this.activeTab >= this.fileParams.length) {
                            this.activeTab = this.fileParams.length - 1;
                        }
                    }
                },

                buildGeocodeAddress(param) {
                    // Google's geocoder expects a natural address, not the labelled
                    // "STREET: / DISTRICT: / LGA:" string stored in param.location.
                    const street = (param.street_name === 'Other' || param.street_name === 'other')
                        ? param.custom_street_name : param.street_name;
                    const district = (param.district === 'Other' || param.district === 'other')
                        ? param.custom_district : param.district;
                    const parts = [
                        param.plot_number,
                        street,
                        district,
                        param.lga,
                        'KANO',
                        'NIGERIA'
                    ];
                    return parts
                        .map(p => (p || '').toString().trim())
                        .filter(p => p && !/^select /i.test(p))
                        .join(', ');
                },

                // Live map/marker instances keyed by file index, so the marker can be
                // dragged and the map reused across re-pins without re-instantiating.
                _maps: {},
                _markers: {},

                // Round to 7 decimals (~1cm precision) — keeps the grey fields tidy.
                _roundCoord(v) {
                    return Math.round(parseFloat(v) * 1e7) / 1e7;
                },

                // Custom property marker. Edit FILL to recolour, or swap the inner
                // <path> for a different glyph (this one is a house = property).
                getMarkerIcon() {
                    const FILL = '#2563eb';   // blue-600 — change to taste (e.g. '#059669' emerald)
                    const svg = `
                        <svg xmlns="http://www.w3.org/2000/svg" width="42" height="50" viewBox="0 0 42 50">
                            <path d="M21 0C10 0 1 9 1 20c0 14 20 30 20 30s20-16 20-30C41 9 32 0 21 0z"
                                  fill="${FILL}" stroke="#ffffff" stroke-width="2"/>
                            <circle cx="21" cy="20" r="13" fill="#ffffff"/>
                            <path d="M21 12l-8 7h2.4v8.4h11.2V19H29z" fill="${FILL}"/>
                        </svg>`;
                    return {
                        url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(svg.trim()),
                        scaledSize: new google.maps.Size(42, 50),
                        anchor: new google.maps.Point(21, 50),
                        labelOrigin: new google.maps.Point(21, 20),
                    };
                },

                setPin(param, index, lat, lng, recenter = false) {
                    param.latitude  = this._roundCoord(lat);
                    param.longitude = this._roundCoord(lng);

                    const map = this._maps[index];
                    const marker = this._markers[index];
                    if (map && marker) {
                        const pos = { lat: param.latitude, lng: param.longitude };
                        marker.setPosition(pos);
                        if (recenter) map.panTo(pos);
                    }
                },

                renderPropertyMap(param, index) {
                    this.$nextTick(() => {
                        const mapEl = document.getElementById('map-' + index);
                        if (!mapEl) return;

                        const pos = { lat: param.latitude, lng: param.longitude };
                        let map = this._maps[index];

                        if (!map) {
                            map = new google.maps.Map(mapEl, {
                                center: pos,
                                zoom: 17,
                                mapTypeId: 'satellite',
                                streetViewControl: true,
                                fullscreenControl: true,
                            });
                            this._maps[index] = map;

                            // Click anywhere on the map to move the pin there.
                            map.addListener('click', (e) => {
                                this.setPin(param, index, e.latLng.lat(), e.latLng.lng());
                            });
                        } else {
                            map.setCenter(pos);
                            // Container may have been display:none while hidden — nudge a resize.
                            google.maps.event.trigger(map, 'resize');
                        }

                        let marker = this._markers[index];
                        if (!marker) {
                            marker = new google.maps.Marker({
                                position: pos,
                                map,
                                draggable: true,
                                title: 'Drag to adjust the exact property location',
                                animation: google.maps.Animation.DROP,
                                icon: this.getMarkerIcon(),
                            });
                            this._markers[index] = marker;

                            // Update coordinates live while/after dragging.
                            marker.addListener('dragend', (e) => {
                                this.setPin(param, index, e.latLng.lat(), e.latLng.lng());
                            });
                        } else {
                            marker.setPosition(pos);
                            marker.setMap(map);
                        }
                    });
                },

                geocodeLocation(param, index) {
                    const address = this.buildGeocodeAddress(param);
                    if (!address || !param.location) return;

                    if (typeof google === 'undefined' || !google.maps) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Map not ready',
                            text: 'Google Maps is still loading. Please try again in a moment.'
                        });
                        return;
                    }

                    const geocoder = new google.maps.Geocoder();
                    geocoder.geocode({ address: address, region: 'NG' }, (results, status) => {
                        if (status === 'OK' && results[0]) {
                            const pos = results[0].geometry.location;
                            param.latitude  = this._roundCoord(pos.lat());
                            param.longitude = this._roundCoord(pos.lng());
                            this.renderPropertyMap(param, index);
                        } else if (status === 'ZERO_RESULTS') {
                            Swal.fire({
                                icon: 'warning',
                                title: 'Location not found',
                                text: 'Google could not find: ' + address
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Geocoding failed (' + status + ')',
                                text: 'The map service rejected the request. Check that Billing and the Geocoding API are enabled for the API key.'
                            });
                        }
                    });
                },

                applyPropertyDetailsToAllFiles() {
                    if (this.indexing_type !== 'Block' || this.fileParams.length < 2) return;
                    
                    const current = this.fileParams[this.activeTab];
                    const data = {
                        land_use_type: current.land_use_type,
                        plot_number: current.plot_number,
                        tp_number: current.tp_number,
                        lpkn_no: current.lpkn_no,
                        district: current.district,
                        custom_district: current.custom_district,
                        street_name: current.street_name,
                        custom_street_name: current.custom_street_name,
                        lga: current.lga,
                        location: current.location,
                        latitude: current.latitude,
                        longitude: current.longitude,
                        plot_size: current.plot_size
                    };

                    this.fileParams.forEach((param, idx) => {
                        Object.assign(param, data);
                    });

                    Swal.fire({
                        icon: 'success',
                        title: 'Property Details Applied',
                        text: `Property details applied to all ${this.fileParams.length} files`,
                        timer: 1500,
                        showConfirmButton: false
                    });
                },

                applyPersonalDetailsToAllFiles() {
                    if (this.indexing_type !== 'Block' || this.fileParams.length < 2) return;

                    const current = this.fileParams[this.activeTab];
                    const data = {
                        gender: current.gender,
                        dob: current.dob,
                        nin: current.nin,
                        tin: current.tin,
                        rc_no: current.rc_no,
                        phone: current.phone,
                        residence_address: current.residence_address,
                        customer_type: current.customer_type
                    };

                    this.fileParams.forEach((param, idx) => {
                        Object.assign(param, data);
                    });

                    Swal.fire({
                        icon: 'success',
                        title: 'Personal Details Applied',
                        text: `Personal details applied to all ${this.fileParams.length} files`,
                        timer: 1500,
                        showConfirmButton: false
                    });
                },

                applyEntityDetailsToAllFiles() {
                    if (this.indexing_type !== 'Block' || this.fileParams.length < 2) return;

                    const current = this.fileParams[this.activeTab];
                    const data = {
                        entity_type: current.entity_type,
                        entity_name: current.entity_name,
                        entity_physical_address: current.entity_physical_address,
                        customer_type: current.customer_type,
                        customer_name: current.customer_name,
                        customer_account_no: current.customer_account_no,
                        customer_code: current.customer_code,
                        property_address: current.property_address
                    };

                    this.fileParams.forEach((param, idx) => {
                        Object.assign(param, data);
                    });

                    Swal.fire({
                        icon: 'success',
                        title: 'Entity & Customer Details Applied',
                        text: `Entity & Customer details applied to all ${this.fileParams.length} files`,
                        timer: 1500,
                        showConfirmButton: false
                    });
                },

                onGroupingRecordUpdated(record) {
                    if (!record) return;
                    
                    if (this.fileParams.length > 0) {
                        const param = this.fileParams[0];
                        
                        // File Title & Applicant Name
                        param.applicant_name = record.applicant_name || record.file_title || record.title || record.comments || param.applicant_name || '';
                        param.title = param.applicant_name;

                        // Property Details
                        param.plot_number = record.plot_no || record.plot_number || param.plot_number || '';
                        param.tp_number = record.tp_number || param.tp_number || '';
                        param.lpkn_no = record.lpkn_no || param.lpkn_no || '';
                        param.land_use_type = record.land_use || record.land_use_type || record.LandUseType || param.land_use_type || '';
                        param.plot_size = record.plot_size || record.PlotSize || param.plot_size || '';

                        // Handle District & Custom District
                        if (record.district || record.District) {
                            const districtVal = record.district || record.District;
                            const districtSelect = document.getElementById('district-select-0'); 
                            
                            // Note: We might need to access reference data store here, but for now relies on DOM
                            param.district = districtVal;
                        }
                        
                        param.lga = record.lga || record.lgsaOrCity || record.LGA || param.lga || '';

                        if (record.location || record.property_location || record.Location) {
                            param.location = record.location || record.property_location || record.Location;
                        }

                        // Entity & Customer Details - Try multiple aliases
                        param.entity_type = record.entity_type || record.EntityType || param.entity_type || 'Corporate';
                        param.entity_name = record.Grantor || record.first_party || record.entity_name || record.EntityName || param.entity_name || '';
                        param.entity_physical_address = record.entity_physical_address || record.EntityPhysicalAddress || param.entity_physical_address || '';
                        
                        param.customer_type = record.customer_type || record.CustomerType || param.customer_type || 'Individual';
                        param.customer_name = record.Grantee || record.second_party || record.customer_name || record.CustomerName || param.customer_name || '';
                        param.customer_account_no = record.customer_account_no || record.CustomerAccountNo || param.customer_account_no || '';
                        param.customer_code = record.customer_code || record.CustomerCode || param.customer_code || '';
                        param.property_address = record.property_address || record.PropertyAddress || param.property_address || '';

                        // Personal Details - Try multiple aliases
                        param.dob = record.dob || record.DOB || param.dob || '';
                        param.nin = record.nin || record.NIN || record.NationalID || param.nin || '';
                        param.tin = record.tin || record.TIN || record.TaxID || param.tin || '';
                        param.rc_no = record.rc_no || record.RCNo || record.RC_NO || param.rc_no || '';
                        param.phone = record.phone || record.Phone || record.PhoneNumber || param.phone || '';
                        param.email = record.email || record.Email || param.email || '';
                        param.residence_address = record.residence_address || record.ResidenceAddress || record.Address || param.residence_address || '';

                        // Handle General Registry (Top-level field)
                        const genRegistrySelect = document.getElementById('general-registry');
                        if (genRegistrySelect) {
                            const registryVal = record.general_registry || record.gen_reg || record.registry || '';
                            if (registryVal) {
                                let matched = false;
                                for (let i = 0; i < genRegistrySelect.options.length; i++) {
                                    if (genRegistrySelect.options[i].value === registryVal || genRegistrySelect.options[i].text === registryVal) {
                                        genRegistrySelect.selectedIndex = i;
                                        matched = true;
                                        break;
                                    }
                                }
                                if (!matched && registryVal) {
                                     // Value not found in dropdown options — leave dropdown unchanged
                                }
                                genRegistrySelect.dispatchEvent(new Event('change', { bubbles: true }));
                            }
                        }
                    }
                },

                // Sync File Title to Current Holder in Regular Indexing mode
                init() {
                    const record = window.editingRecord;
                    if (record) {
                        // If the record has file_titles array (from previous multi-file save), use it
                        if (Array.isArray(record.file_titles) && record.file_titles.length > 0) {
                             this.fileParams = record.file_titles.map((title, index) => ({
                                 id: Date.now() + Math.random() + index,
                                 title: title,
                             }));
                        } else if (record.file_title) {
                            // Legacy single record or first time editing a single record
                            this.fileParams = [{
                                id: Date.now(),
                                title: record.file_title,
                                applicant_name: record.applicant_name || record.file_title,
                                
                                // Entity & Customer Details
                                entity_type: record.entity_type,
                                entity_name: record.entity_name,
                                entity_physical_address: record.entity_physical_address,
                                customer_type: record.customer_type,
                                customer_name: record.customer_name,
                                customer_account_no: record.customer_account_no,
                                customer_code: record.customer_code,
                                property_address: record.property_address,
                                
                                // Personal Details
                                gender: record.gender,
                                dob: record.dob,
                                nin: record.nin,
                                tin: record.tin,
                                rc_no: record.rc_no,
                                phone: record.phone,
                                residence_address: record.residence_address,

                                // Property Details
                                land_use_type: record.land_use_type,
                                plot_number: record.plot_number,
                                tp_number: record.tp_number,
                                lpkn_no: record.lpkn_no,
                                district: record.district,
                                street_name: record.street_name,
                                custom_street_name: record.street_name === 'Other' || record.street_name === 'other' ? record.custom_street_name : '',
                                lga: record.lga || record.lgsaOrCity,
                                location: record.location,
                                latitude: record.latitude ?? null,
                                longitude: record.longitude ?? null,
                                plot_size: record.plot_size
                            }];
                        }
                    }

                    // Watchers for title sync and batch apply
                    this.$watch('fileParams', (params, oldParams) => {
                        // Sync title to applicant_name (instead of the reverse which was locking the field)
                        params.forEach(param => {
                            if (param.title !== param.applicant_name) {
                                param.applicant_name = param.title;
                            }
                        });

                        // Real-time batch apply for Property Details
                        if (this.indexing_type === 'Block' && this.applyPropertyToAll && params.length > 1) {
                            const current = params[this.activeTab];
                            const propFields = ['land_use_type', 'plot_number', 'tp_number', 'lpkn_no', 'district', 'custom_district', 'street_name', 'custom_street_name', 'lga', 'location', 'latitude', 'longitude', 'plot_size'];
                            
                            params.forEach((param, idx) => {
                                if (idx !== this.activeTab) {
                                    propFields.forEach(field => {
                                        if (param[field] !== current[field]) {
                                            param[field] = current[field];
                                        }
                                    });
                                }
                            });
                        }

                        // Real-time batch apply for Personal Details
                        if (this.indexing_type === 'Block' && this.applyPersonalToAll && params.length > 1) {
                            const current = params[this.activeTab];
                            const personalFields = ['dob', 'nin', 'tin', 'rc_no', 'phone', 'residence_address', 'customer_type'];
                            
                            params.forEach((param, idx) => {
                                if (idx !== this.activeTab) {
                                    personalFields.forEach(field => {
                                        if (param[field] !== current[field]) {
                                            param[field] = current[field];
                                        }
                                    });
                                }
                            });
                        }

                        // Real-time batch apply for Entity & Customer Details
                        if (this.indexing_type === 'Block' && this.applyEntityToAll && params.length > 1) {
                            const current = params[this.activeTab];
                            const entityFields = ['entity_type', 'entity_name', 'entity_physical_address', 'customer_type', 'customer_name', 'customer_account_no', 'customer_code', 'property_address'];
                            
                            params.forEach((param, idx) => {
                                if (idx !== this.activeTab) {
                                    entityFields.forEach(field => {
                                        if (param[field] !== current[field]) {
                                            param[field] = current[field];
                                        }
                                    });
                                }
                            });
                        }

                        this.$nextTick(() => {
                            if (window.lucide && typeof window.lucide.createIcons === 'function') {
                                window.lucide.createIcons();
                            }
                        });

                        if (this.indexing_type === 'Regular' && params.length > 0) {
                            const firstTitle = params[0].title;
                            const holderInput = document.getElementById('current-holder');
                            if (holderInput) {
                                holderInput.value = firstTitle;
                                holderInput.dispatchEvent(new Event('input', { bubbles: true }));
                            }
                        } else if (this.indexing_type === 'Block' && params.length > 0) {
                            const wrapper = document.getElementById('current-holders-wrapper');
                            if (wrapper) {
                                let rows = wrapper.querySelectorAll('.current-holder-row');
                                
                                // Remove excess rows
                                while (rows.length > params.length) {
                                    wrapper.removeChild(rows[rows.length - 1]);
                                    rows = wrapper.querySelectorAll('.current-holder-row');
                                }
                                
                                // Add missing rows
                                while (rows.length < params.length) {
                                    const newRow = document.createElement('div');
                                    newRow.className = 'current-holder-row flex gap-2';
                                    
                                    const input = document.createElement('input');
                                    input.type = 'text';
                                    input.name = 'current_holder[]';
                                    input.className = 'current-holder-input block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm';
                                    
                                    const btn = document.createElement('button');
                                    btn.type = 'button';
                                    btn.className = 'remove-holder-btn inline-flex items-center px-3 py-2 border border-red-600 text-red-600 rounded-md hover:bg-red-50';
                                    btn.innerHTML = '<i data-lucide="trash-2" class="h-4 w-4 text-red-600"></i>';
                                    btn.onclick = function() {
                                        // Manual remove if needed
                                        newRow.remove();
                                    };
                                    
                                    newRow.appendChild(input);
                                    newRow.appendChild(btn);
                                    wrapper.appendChild(newRow);
                                    
                                    if (window.lucide && typeof window.lucide.createIcons === 'function') {
                                        window.lucide.createIcons();
                                    }
                                    rows = wrapper.querySelectorAll('.current-holder-row');
                                }
                                
                                // Update values
                                params.forEach((param, index) => {
                                    if(rows[index]) {
                                        const input = rows[index].querySelector('input.current-holder-input');
                                        if (input && input.value !== param.title) {
                                            input.value = param.title;
                                            input.dispatchEvent(new Event('input', { bubbles: true }));
                                        }
                                    }
                                });
                            }
                        }
                    }, { deep: true });

                    this.$watch('indexing_type', (type) => {
                        if (type === 'Regular' && this.fileParams.length > 0) {
                            const firstTitle = this.fileParams[0].title;
                            const holderInput = document.getElementById('current-holder');
                            if (holderInput) {
                                holderInput.value = firstTitle;
                                holderInput.dispatchEvent(new Event('input', { bubbles: true }));
                            }
                        } else if (type === 'Block' && this.fileParams.length > 0) {
                            const params = this.fileParams;
                            const wrapper = document.getElementById('current-holders-wrapper');
                            if (wrapper) {
                                let rows = wrapper.querySelectorAll('.current-holder-row');
                                
                                // Remove excess rows
                                while (rows.length > params.length) {
                                    wrapper.removeChild(rows[rows.length - 1]);
                                    rows = wrapper.querySelectorAll('.current-holder-row');
                                }
                                
                                // Add missing rows
                                while (rows.length < params.length) {
                                    const newRow = document.createElement('div');
                                    newRow.className = 'current-holder-row flex gap-2';
                                    
                                    const input = document.createElement('input');
                                    input.type = 'text';
                                    input.name = 'current_holder[]';
                                    input.className = 'current-holder-input block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm';
                                    
                                    const btn = document.createElement('button');
                                    btn.type = 'button';
                                    btn.className = 'remove-holder-btn inline-flex items-center px-3 py-2 border border-red-600 text-red-600 rounded-md hover:bg-red-50';
                                    btn.innerHTML = '<i data-lucide="trash-2" class="h-4 w-4 text-red-600"></i>';
                                    btn.onclick = function() {
                                        newRow.remove();
                                    };
                                    
                                    newRow.appendChild(input);
                                    newRow.appendChild(btn);
                                    wrapper.appendChild(newRow);
                                    
                                    if (window.lucide && typeof window.lucide.createIcons === 'function') {
                                        window.lucide.createIcons();
                                    }
                                    rows = wrapper.querySelectorAll('.current-holder-row');
                                }
                                
                                // Update values
                                params.forEach((param, index) => {
                                    if(rows[index]) {
                                        const input = rows[index].querySelector('input.current-holder-input');
                                        if (input && input.value !== param.title) {
                                            input.value = param.title;
                                            input.dispatchEvent(new Event('input', { bubbles: true }));
                                        }
                                    }
                                });
                            }
                        }
                    });
                }

            }));
        });
    </script>

    {{-- Title Status integration (reuses the standalone title-status backend) --}}
    <script>
        (function () {
            const TS_STORE_URL = '{{ route('title-status.store') }}';
            const TS_INITIATED_BY_BY_TYPE = @json(\App\Models\TitleStatusApplication::INITIATED_BY_BY_TYPE);
            const TS_TYPE_VERB = {
                'Withdrawal (Application)':                            'Withdrawal',
                'Cancellation (RofO)':                                'Cancellation',
                'Revoke (CofO)':                                      'Revocation',
                'Litigation':                                         'Litigation',
                'Amendment/Reconsideration (Application/RofO/CofO)':  'Amendment/Reconsideration',
                'Surrender':                                          'Surrender',
                'Re-grant':                                           'Re-grant',
                'Subdivision':                                        'Subdivision',
                'Merger':                                             'Merger',
                'Change of Purpose':                                  'Change of Purpose',
                'Extension':                                          'Extension',
                'Change of Name':                                     'Change of Name',
                'Separation':                                         'Separation',
                'Resettlement':                                       'Resettlement',
                'Closed':                                             'Closed',
            };

            const typeGroup    = document.getElementById('ts-title-type-group');
            const statusBlock  = document.getElementById('ts-status-fields');
            const seeRow       = document.getElementById('ts-see-row');
            const seeHidden    = document.getElementById('ts-see-fileno');
            const seeDisplay   = document.getElementById('ts-see-fileno-display');
            const reasonEl     = document.getElementById('ts-reason');
            const remarkEl     = document.getElementById('ts-remark');
            const extKindEl    = document.getElementById('ts-extension-kind');
            const extLabelEl   = document.getElementById('ts-extension-label');
            const regrantKindEl  = document.getElementById('ts-regrant-kind');
            const regrantLabelEl = document.getElementById('ts-regrant-label');
            const closedKindEl   = document.getElementById('ts-closed-kind');
            const closedLabelEl  = document.getElementById('ts-closed-label');
            const resettleKindEl  = document.getElementById('ts-resettlement-kind');
            const resettleLabelEl = document.getElementById('ts-resettlement-label');
            const seeLabelEl   = document.getElementById('ts-see-label');
            const seeHintEl    = document.getElementById('ts-see-hint');
            const reasonRow    = document.getElementById('ts-reason-row');

            if (!typeGroup) return;

            // Extension sub-kind (Plot Extension / File Extension), chosen via popup.
            const EXTENSION_KINDS = ['Plot Extension', 'File Extension'];
            const getExtensionKind = () => (extKindEl?.value || '').trim();
            const setExtensionKind = (kind) => {
                if (extKindEl) extKindEl.value = kind || '';
                if (extLabelEl) extLabelEl.textContent = kind ? kind : 'Extension';
            };
            // Re-grant direction (Re-granted From / Re-granted To), chosen via popup.
            //   From — this file is the NEW file; Initial FileNo is the OLD number it came from.
            //   To   — this file is the OLD file; the picked number is the NEW one replacing it.
            const REGRANT_FROM = 'Re-granted From';
            const REGRANT_TO   = 'Re-granted To';
            const getRegrantKind = () => (regrantKindEl?.value || '').trim();
            const setRegrantKind = (kind) => {
                if (regrantKindEl)  regrantKindEl.value = kind || '';
                if (regrantLabelEl) regrantLabelEl.textContent = kind ? kind : 'Re-grant';
            };

            // Closure direction (Closed To / Continued From), chosen via popup.
            //   Closed To      — this file is being closed; the picked number is the file it continues in.
            //   Continued From — this file is the continuation; the picked number is the closed file.
            const CLOSED_TO      = 'Closed To';
            const CONTINUED_FROM = 'Continued From';
            const getClosedKind = () => (closedKindEl?.value || '').trim();
            const setClosedKind = (kind) => {
                if (closedKindEl)  closedKindEl.value = kind || '';
                if (closedLabelEl) closedLabelEl.textContent = kind ? kind : 'Closed';
            };

            // Resettlement direction (Resettled From / Resettled To), same From/To sense as
            // Re-grant: "From" = this file is the new one, "To" = this file is the old one.
            const RESETTLED_FROM = 'Resettled From';
            const RESETTLED_TO   = 'Resettled To';
            const getResettleKind = () => (resettleKindEl?.value || '').trim();
            const setResettleKind = (kind) => {
                if (resettleKindEl)  resettleKindEl.value = kind || '';
                if (resettleLabelEl) resettleLabelEl.textContent = kind ? kind : 'Resettlement';
            };

            // Resolve the effective title type: a ticked "Extension" / "Re-grant" / "Closed" /
            // "Resettlement" reports the chosen sub-kind.
            const resolveType = (t) => {
                if (t === 'Extension')    return getExtensionKind() || t;
                if (t === 'Re-grant')     return getRegrantKind()   || t;
                if (t === 'Closed')       return getClosedKind()    || t;
                if (t === 'Resettlement') return getResettleKind()  || t;
                return t;
            };

            // Checkboxes live in two cards (Parcel Update + Normal in the Indexing Type card,
            // Title Status Update in the General Registry card), so query document-wide.
            const typeCbs = () => Array.from(document.querySelectorAll('.ts-title-type-cb'));
            const getSelectedTypes = () => typeCbs().filter(cb => cb.checked).map(cb => cb.value);
            const getActionableTypes = () => getSelectedTypes().filter(isActionableType);

            const getFileNo = () =>
                ((document.getElementById('fileno')?.value
                    || document.getElementById('file-number-display')?.value
                    || '').trim()).replace(/\(\s*T\s*\)\s*$/i, '').trim();

            const initiatedByFor = (type) => {
                const opts = TS_INITIATED_BY_BY_TYPE[type] || ['Ministry'];
                return opts[0] || 'Ministry';
            };

            // Classification flags (Parcel Update). They do NOT trigger the title-status
            // backend, but they DO contribute an auto line to the shared Remark / Comment box.
            const CLASSIFICATION_TYPES = ['Dummy File', 'Temporary'];
            const NON_ACTIONABLE = ['Normal', ...CLASSIFICATION_TYPES];
            const isActionableType = (t) => t !== '' && !NON_ACTIONABLE.includes(t);
            const isClassificationType = (t) => CLASSIFICATION_TYPES.includes(t);
            // Everything that should appear in Remark / Comment (actionable + classification).
            const isRemarkType = (t) => t !== '' && t !== 'Normal';
            const getRemarkTypes = () => getSelectedTypes().filter(isRemarkType);

            // Statuses whose auto remark is a fixed sentence about a counterpart file, so the
            // Reason box has nothing to feed. Kept in step with buildRemarkFor() below: a bare
            // "Resettlement" with no direction still falls through to the generic sentence.
            const REASONLESS_TYPES = ['Re-grant', 'Closed', ...CLASSIFICATION_TYPES];
            const usesReason = (t) => {
                if (!isRemarkType(t) || REASONLESS_TYPES.includes(t)) return false;
                if (t === 'Resettlement') return !getResettleKind();
                return true;
            };

            function buildRemarkFor(type) {
                const fileNo = getFileNo();
                const now = new Date();
                const pad = n => String(n).padStart(2, '0');
                const dt  = `${pad(now.getDate())}/${pad(now.getMonth() + 1)}/${now.getFullYear()} ${pad(now.getHours())}:${pad(now.getMinutes())}`;
                const fileRef = fileNo ? ` FileNo ${fileNo}` : '';
                if (isClassificationType(type)) {
                    return `File${fileRef} was marked as ${type} on ${dt}`;
                }
                if (type === 'Re-grant') {
                    const seeFileno = (seeHidden?.value || '').trim();
                    if (!seeFileno) return 'This File has been Re-granted';
                    return getRegrantKind() === REGRANT_TO
                        ? `This File has been Re-granted to ${seeFileno}`
                        : `This File has been Re-granted from ${seeFileno}`;
                }
                if (type === 'Resettlement' && getResettleKind()) {
                    const seeFileno = (seeHidden?.value || '').trim();
                    if (!seeFileno) return 'This File has been Resettled';
                    return getResettleKind() === RESETTLED_TO
                        ? `This File has been Resettled to ${seeFileno}`
                        : `This File has been Resettled from ${seeFileno}`;
                }
                if (type === 'Closed') {
                    const seeFileno = (seeHidden?.value || '').trim();
                    if (getClosedKind() === CONTINUED_FROM) {
                        return seeFileno
                            ? `This File is Continued From ${seeFileno}`
                            : 'This File is a Continuation of a Closed File';
                    }
                    return seeFileno
                        ? `This File has been Closed and Continued in ${seeFileno}`
                        : 'This File has been Closed';
                }
                const verb   = (type === 'Extension')
                    ? (getExtensionKind() || 'Extension')
                    : (TS_TYPE_VERB[type] || type);
                const reason = (reasonEl?.value || '').trim() || '[Reason]';
                const initiator = initiatedByFor(type);
                return `${verb}${fileRef} was initiated by ${initiator} on ${dt} due to ${reason}`;
            }

            function buildRemark() {
                const types = getRemarkTypes();
                if (!types.length) return '';
                return types.map(buildRemarkFor).join('\n');
            }

            function refreshRemark() {
                if (remarkEl) remarkEl.value = buildRemark();
            }

            function toggleStatusFields() {
                const remarkTypes = getRemarkTypes();
                const actionable  = getActionableTypes();
                const hasType   = remarkTypes.length > 0;
                const isRegrant = actionable.includes('Re-grant');
                const isClosed  = actionable.includes('Closed');
                const isResettle = actionable.includes('Resettlement');
                // These statuses all point at a counterpart file, so they share the See row.
                const needsSee  = isRegrant || isClosed || isResettle;

                // Deselecting happens silently in two cases — the Title Status Update radios
                // replace each other, and ticking "Normal" clears the rest in code — so neither
                // fires a change event on the option that lost its tick. Drop any stale sub-kind.
                if (!isRegrant && getRegrantKind()) setRegrantKind('');
                if (!isClosed && getClosedKind()) setClosedKind('');
                if (!isResettle && getResettleKind()) setResettleKind('');
                if (!actionable.includes('Extension') && getExtensionKind()) setExtensionKind('');

                if (isRegrant) {
                    const to = getRegrantKind() === REGRANT_TO;
                    if (seeLabelEl) seeLabelEl.textContent = to ? 'Re-granted To FileNo' : 'Initial FileNo';
                    if (seeHintEl) {
                        seeHintEl.textContent = to
                            ? 'The NEW file number this file was re-granted to. This field is optional.'
                            : 'The OLD file number this file was re-granted from, written as "See ..." on the physical file. This field is optional.';
                    }
                } else if (isClosed) {
                    const from = getClosedKind() === CONTINUED_FROM;
                    if (seeLabelEl) seeLabelEl.textContent = from ? 'Continued From FileNo' : 'Continued In FileNo';
                    if (seeHintEl) {
                        seeHintEl.textContent = from
                            ? 'The CLOSED file number this file carries on from. This field is optional.'
                            : 'The file number this file continues in now that it is closed. This field is optional.';
                    }
                } else if (isResettle) {
                    const to = getResettleKind() === RESETTLED_TO;
                    if (seeLabelEl) seeLabelEl.textContent = to ? 'Resettled To FileNo' : 'Resettled From FileNo';
                    if (seeHintEl) {
                        seeHintEl.textContent = to
                            ? 'The NEW file number this file was resettled to. This field is optional.'
                            : 'The OLD file number this file was resettled from. This field is optional.';
                    }
                }

                // Reason only shows for statuses that actually quote it in their remark; drop
                // anything already typed so a hidden box can't submit a stale reason.
                const needsReason = remarkTypes.some(usesReason);
                reasonRow?.classList.toggle('hidden', !needsReason);
                if (!needsReason && reasonEl) reasonEl.value = '';

                statusBlock?.classList.toggle('hidden', !hasType);
                seeRow?.classList.toggle('hidden', !needsSee);
                // NOTE: the See file number (see_fileno) is OPTIONAL for Re-grant and Closed
                // Do NOT add required validation on this field
                if (!needsSee && seeHidden) { seeHidden.value = ''; if (seeDisplay) seeDisplay.value = ''; }
                if (typeof lucide !== 'undefined') lucide.createIcons();
            }

            // Only "Normal" is exclusive: checking it clears every other option, and checking
            // any other option clears "Normal". Dummy File, Temporary and the actionable title
            // statuses are all freely multi-selectable. Delegated on document since the
            // checkboxes span two separate cards.
            // Prompt the user to choose the extension kind (Plot / File) when "Extension"
            // is ticked. Cancelling un-ticks the option. Returns nothing; updates hidden field.
            function promptExtensionKind(cb) {
                const finish = () => { toggleStatusFields(); refreshRemark(); };

                if (typeof Swal === 'undefined') {
                    // Minimal fallback without SweetAlert.
                    const useFile = window.confirm('Extension type:\n\nOK = Plot Extension\nCancel = File Extension');
                    setExtensionKind(useFile ? 'Plot Extension' : 'File Extension');
                    finish();
                    return;
                }

                Swal.fire({
                    title: 'Extension Type',
                    text: 'Select the type of extension',
                    icon: 'question',
                    showDenyButton: true,
                    showCancelButton: true,
                    confirmButtonText: 'Plot Extension',
                    denyButtonText: 'File Extension',
                    cancelButtonText: 'Cancel',
                    confirmButtonColor: '#059669',
                    denyButtonColor: '#4f46e5',
                }).then((result) => {
                    if (result.isConfirmed) {
                        setExtensionKind('Plot Extension');
                    } else if (result.isDenied) {
                        setExtensionKind('File Extension');
                    } else {
                        // Cancelled → revert the selection.
                        cb.checked = false;
                        setExtensionKind('');
                    }
                    finish();
                });
            }

            // Re-grant and Closed share the See row, so a fresh pick must not inherit the
            // counterpart file left behind by the other status.
            function resetSeeFileno() {
                if (seeHidden)  seeHidden.value = '';
                if (seeDisplay) seeDisplay.value = '';
            }

            // Ask which way the re-grant runs when "Re-grant" is picked. Cancelling clears the
            // selection — a direction is what makes the Initial FileNo readable either way.
            function promptRegrantKind(cb) {
                const finish = () => { toggleStatusFields(); refreshRemark(); };
                resetSeeFileno();

                if (typeof Swal === 'undefined') {
                    const isFrom = window.confirm('Re-grant direction:\n\nOK = Re-granted From (this is the new file)\nCancel = Re-granted To (this is the old file)');
                    setRegrantKind(isFrom ? REGRANT_FROM : REGRANT_TO);
                    finish();
                    return;
                }

                Swal.fire({
                    title: 'Re-grant Direction',
                    html: 'Is this file the <b>new</b> file or the <b>old</b> one?<br>'
                        + '<span class="text-sm text-gray-500">Re-granted From = this file replaced an older file.<br>'
                        + 'Re-granted To = this file has been replaced by a newer file.</span>',
                    icon: 'question',
                    showDenyButton: true,
                    showCancelButton: true,
                    confirmButtonText: REGRANT_FROM,
                    denyButtonText: REGRANT_TO,
                    cancelButtonText: 'Cancel',
                    confirmButtonColor: '#4f46e5',
                    denyButtonColor: '#0d9488',
                }).then((result) => {
                    if (result.isConfirmed) {
                        setRegrantKind(REGRANT_FROM);
                    } else if (result.isDenied) {
                        setRegrantKind(REGRANT_TO);
                    } else {
                        cb.checked = false;
                        setRegrantKind('');
                    }
                    finish();
                });
            }

            // Ask which way the closure runs when "Closed" is picked. Cancelling clears the
            // selection, same as Re-grant.
            function promptClosedKind(cb) {
                const finish = () => { toggleStatusFields(); refreshRemark(); };
                resetSeeFileno();

                if (typeof Swal === 'undefined') {
                    const isTo = window.confirm('Closure direction:\n\nOK = Closed To (this file is being closed)\nCancel = Continued From (this file continues a closed file)');
                    setClosedKind(isTo ? CLOSED_TO : CONTINUED_FROM);
                    finish();
                    return;
                }

                Swal.fire({
                    title: 'Closure Direction',
                    html: 'Is this file the one being <b>closed</b>, or the one <b>carrying on</b>?<br>'
                        + '<span class="text-sm text-gray-500">Closed To = this file is closed and continues in another file.<br>'
                        + 'Continued From = this file continues a file that was closed.</span>',
                    icon: 'question',
                    showDenyButton: true,
                    showCancelButton: true,
                    confirmButtonText: CLOSED_TO,
                    denyButtonText: CONTINUED_FROM,
                    cancelButtonText: 'Cancel',
                    confirmButtonColor: '#4f46e5',
                    denyButtonColor: '#0d9488',
                }).then((result) => {
                    if (result.isConfirmed) {
                        setClosedKind(CLOSED_TO);
                    } else if (result.isDenied) {
                        setClosedKind(CONTINUED_FROM);
                    } else {
                        cb.checked = false;
                        setClosedKind('');
                    }
                    finish();
                });
            }

            // Ask which way the resettlement runs when "Resettlement" is picked.
            function promptResettleKind(cb) {
                const finish = () => { toggleStatusFields(); refreshRemark(); };
                resetSeeFileno();

                if (typeof Swal === 'undefined') {
                    const isFrom = window.confirm('Resettlement direction:\n\nOK = Resettled From (this is the new file)\nCancel = Resettled To (this is the old file)');
                    setResettleKind(isFrom ? RESETTLED_FROM : RESETTLED_TO);
                    finish();
                    return;
                }

                Swal.fire({
                    title: 'Resettlement Direction',
                    html: 'Is this file the <b>new</b> file or the <b>old</b> one?<br>'
                        + '<span class="text-sm text-gray-500">Resettled From = this file received the resettlement.<br>'
                        + 'Resettled To = the holder on this file was resettled to a newer file.</span>',
                    icon: 'question',
                    showDenyButton: true,
                    showCancelButton: true,
                    confirmButtonText: RESETTLED_FROM,
                    denyButtonText: RESETTLED_TO,
                    cancelButtonText: 'Cancel',
                    confirmButtonColor: '#4f46e5',
                    denyButtonColor: '#0d9488',
                }).then((result) => {
                    if (result.isConfirmed) {
                        setResettleKind(RESETTLED_FROM);
                    } else if (result.isDenied) {
                        setResettleKind(RESETTLED_TO);
                    } else {
                        cb.checked = false;
                        setResettleKind('');
                    }
                    finish();
                });
            }

            document.addEventListener('change', function (e) {
                const cb = e.target;
                if (!cb || !cb.classList || !cb.classList.contains('ts-title-type-cb')) return;
                if (cb.value === 'Normal' && cb.checked) {
                    typeCbs().forEach(o => { if (o.value !== 'Normal') o.checked = false; });
                } else if (cb.value !== 'Normal' && cb.checked) {
                    const normal = typeCbs().find(o => o.value === 'Normal');
                    if (normal) normal.checked = false;
                }

                // "Extension" ticked → ask Plot vs File; unticked → reset the chosen kind.
                if (cb.value === 'Extension') {
                    if (cb.checked) {
                        promptExtensionKind(cb);
                        return; // popup callback runs toggleStatusFields()/refreshRemark()
                    }
                    setExtensionKind('');
                }

                // "Re-grant" picked → ask From vs To; it is a radio, so it can only be
                // cleared by another option winning the group (handled in toggleStatusFields).
                if (cb.value === 'Re-grant' && cb.checked) {
                    promptRegrantKind(cb);
                    return; // popup callback runs toggleStatusFields()/refreshRemark()
                }

                // "Closed" picked → ask Closed To vs Continued From, same radio caveat.
                if (cb.value === 'Closed' && cb.checked) {
                    promptClosedKind(cb);
                    return; // popup callback runs toggleStatusFields()/refreshRemark()
                }

                // "Resettlement" picked → ask Resettled From vs To, same radio caveat.
                if (cb.value === 'Resettlement' && cb.checked) {
                    promptResettleKind(cb);
                    return; // popup callback runs toggleStatusFields()/refreshRemark()
                }

                toggleStatusFields();
                refreshRemark();
            });
            reasonEl?.addEventListener('input', refreshRemark);

            // See (Additional File No.) picker
            document.getElementById('ts-see-select-btn')?.addEventListener('click', function () {
                if (!window.GlobalFileNoModal) return;
                GlobalFileNoModal.open({
                    // Do NOT write into the main #fileno / file_number inputs — the See file
                    // is a separate reference and must not overwrite the main file number.
                    autoPopulateGenericFields: false,
                    callback: function (data) {
                        if (!data || !data.fileNumber) return;
                        seeHidden.value = data.fileNumber;
                        seeDisplay.value = data.fileNumber;
                        refreshRemark();
                    }
                });
            });
            document.getElementById('ts-see-clear-btn')?.addEventListener('click', function () {
                resetSeeFileno();
                refreshRemark(); // the cleared number is quoted in the auto remark
            });

            // Called by handleRegistryFieldToggling() when the chosen registry (SLTR /
            // KANGIS / DCIV) hides both Title Status cards — drop any selection so a
            // stale status isn't submitted with the hidden fields.
            window.tsClearSelections = function () {
                typeCbs().forEach(cb => { cb.checked = false; });
                setExtensionKind('');
                setRegrantKind('');
                setClosedKind('');
                setResettleKind('');
                if (reasonEl)   reasonEl.value = '';
                if (remarkEl)   remarkEl.value = '';
                if (seeHidden)  seeHidden.value = '';
                if (seeDisplay) seeDisplay.value = '';
                toggleStatusFields();
            };

            // Called by create-indexing-dialog.js after a file index is created/updated.
            window.tsAfterFileIndexCreated = function (data, formData) {
                const types = getActionableTypes();
                if (!types.length) return; // "Normal" or unselected → no title status

                formData = formData || {};
                const pick = (v) => Array.isArray(v) ? (v[0] || '') : (v || '');
                const reg  = (formData.general_registry || '').toString().toLowerCase();
                const url  = reg.includes('dciv') ? 'dciv' : 'land';
                const fileNo = getFileNo() || pick(formData.file_number);
                if (!fileNo) return;

                const reason = (reasonEl?.value || '').trim();
                const customRemark = (remarkEl?.value || '').trim();
                // When classification flags are also selected, the Remark box holds their lines
                // too, so don't reuse it verbatim for the title-status record — regenerate per type.
                const hasClassification = !!document.querySelector('.ts-classification-cb:checked');

                // Per-type remarks (parallel to `title_types`). A single selection may keep the
                // user-edited remark; otherwise each type gets its own generated remark.
                const remarks = types.map(function (type) {
                    return (types.length === 1 && customRemark && !hasClassification) ? customRemark : buildRemarkFor(type);
                });
                // The statuses that reference a counterpart file via the shared See row.
                const usesSee = types.includes('Re-grant')
                    || types.includes('Closed')
                    || types.includes('Resettlement');

                // Report the chosen sub-kinds — Plot/File Extension, and Re-granted From/To —
                // in place of the generic "Extension"/"Re-grant" so they persist distinctly.
                const reportedTypes = types.map(resolveType);

                // One request carrying every selected type so the backend records all of them
                // (one application per type) and writes a combined title-status flag on the file.
                const payload = {
                    url,
                    title_type:     reportedTypes[0],   // kept for validation / backward compatibility
                    title_types:    reportedTypes,
                    remarks:        remarks,
                    file_no:        fileNo,
                    see_fileno:     usesSee ? (seeHidden?.value || '').trim() : '',
                    file_title:     pick(formData.file_title),
                    applicant_name: pick(formData.current_holder) || pick(formData.file_title),
                    plot_no:        pick(formData.plot_number),
                    location:       pick(formData.location),
                    land_use:       pick(formData.land_use_type),
                    district:       pick(formData.district) || pick(formData.custom_district),
                    lga:            pick(formData.lga),
                    initiated_by:   initiatedByFor(types[0]),
                    reason:         reason,
                    remark:         remarks.join('\n'),
                    // Title Status raised from File Indexing = false decommissioning
                    // (the file is flagged but NOT actually decommissioned).
                    false_decommissioning: 1,
                };

                fetch(TS_STORE_URL, {
                    method: 'POST',
                    keepalive: true,
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    },
                    body: JSON.stringify(payload),
                })
                .then(r => r.json())
                .then(res => {
                    if (!res.success) {
                        console.warn('Title status not recorded:', res.message || res.errors);
                    } else {
                        console.log('Title status recorded (' + types.length + '):', types.join(', '));
                    }
                })
                .catch(err => console.warn('Title status request failed:', err));
            };
        })();
    </script>
@endsection

@push('scripts')
    {{-- Google Maps JS — used by the Property Details "Apply & Pin on Map" geocoder --}}
    <script src="https://maps.googleapis.com/maps/api/js?key={{ config('services.google_maps.key') }}" defer></script>
@endpush