@extends('layouts.app')

@section('page-title')
    {{ __('Update KANGIS File Index') }}
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
                                        Update KANGIS File Index
                                    </h2>
                                </div>
                                <p class="text-gray-600">
                                    Update this physical KANGIS file. The file number is fixed — the placeholder identifies which physical folder this is.
                                </p>
                            </div>
                        </div>
                        @if(isset($record))
                            <div class="mt-2 px-3 py-2 bg-blue-50 border border-blue-200 rounded-md">
                                <p class="text-sm text-blue-800">
                                    <span class="font-semibold">Editing:</span> {{ $record->file_number ?? 'Unknown' }} -
                                    {{ $record->file_title ?? 'No Title' }}
                                    @if(!empty($record->kangis_fileno_placeholder))
                                        <span class="ml-2 inline-flex items-center rounded bg-purple-100 px-2 py-0.5 font-mono text-xs font-semibold text-purple-800">
                                            Placeholder: {{ $record->kangis_fileno_placeholder }}
                                        </span>
                                    @endif
                                </p>
                            </div>
                        @endif
                    </div>

                    <form id="new-file-form" data-default-indexer="{{ Auth::user()->name ?? 'Current User' }}"
                        data-check-indexed-url="{{ route('fileindex.check-indexed') }}"
                        data-indexed-view-url-template="{{ route('fileindexing.show', ['id' => '__ID__']) }}"
                        data-transactions-url-template="{{ route('fileindexing.transactions', ['fileIndexing' => '__ID__']) }}"
                        data-editing-id="{{ $record->id }}"
                        data-action-url="{{ route('kangis.file-index.update', $record->id) }}" data-method="PUT"
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
                                window.returnToUrl = @json($returnTo ?? '');

                                // KANGIS update mode. Read by create-indexing-dialog.js, which
                                // treats it as inert when absent, so the create page is unaffected.
                                //  - submit is retargeted at the KANGIS update endpoint
                                //  - the "a new suffix will be assigned" confirmation is suppressed:
                                //    this row's _N identity was settled at creation and never moves
                                //  - the record is applied by id, never re-looked-up by file number
                                //    (checkIndexed() normalises "KNML 1_2" and "KNML 12" to the same
                                //    key, so a lookup here could populate the wrong physical file)
                                window.kangisUpdateMode = {
                                    id: @json($record->id),
                                    url: @json(route('kangis.file-index.update', $record->id)),
                                    fileNumber: @json($record->file_number ?? ''),
                                    placeholder: @json($record->kangis_fileno_placeholder ?? ''),
                                };
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
                                            'Withdrawal (Allocation)'                             => ['Withdrawal (Allocation)', 'undo-2'],
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
                                                        <span class="leading-tight">{{ $tsMeta[0] }}</span>
                                                    </span>
                                                </label>
                                            @endforeach
                                        </div>
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
                                                    <span class="leading-tight"@if($tsValue === 'Re-grant') id="ts-regrant-label"@elseif($tsValue === 'Closed') id="ts-closed-label"@endif>{{ $tsMeta[0] }}</span>
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
                                </div>
                            </div>
                        </div>

                        {{-- Title Status details (shown when a Title Status is selected) --}}
                        <div id="ts-status-fields" class="hidden mt-6 rounded-lg border border-teal-200 bg-teal-50/40 p-6 space-y-5">
                           

                            {{-- Counterpart file number for Re-grant / Resettlement / Closed.
                                 It is NOT typed here: the number is whatever the officer entered
                                 in the "Related File Number" card below, so the two can never
                                 disagree. A single related file selects itself; with several, the
                                 officer says which one this title status refers to. --}}
                            <div id="ts-see-row" class="form-group hidden">
                                <input type="hidden" id="ts-see-fileno">
                                <label for="ts-see-picker" class="block text-sm font-medium text-gray-700 mb-2"><span id="ts-see-label">Initial FileNo</span> <span class="text-xs font-normal text-gray-500">(Optional)</span></label>
                                <select id="ts-see-picker"
                                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-teal-500 focus:border-teal-500 sm:text-sm bg-white"></select>
                                <p class="text-[11px] text-gray-400 mt-1" id="ts-see-hint">Taken from the Related File Number section below.</p>
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

                            {{-- Remark / Comment. Hidden for the statuses the system words itself
                                 from the related file number (Re-grant / Resettlement / Closed);
                                 it is still submitted, just not shown or edited. --}}
                            <div class="form-group" id="ts-remark-row">
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
    @include('fileindexing.partials.year_policy')
    <script src="{{ asset('js/global-fileno-modal.js') }}?v={{ @filemtime(public_path('js/global-fileno-modal.js')) }}"></script>
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
                    const street = (String(param.street_name || '').toLowerCase() === 'other')
                        ? param.custom_street_name : param.street_name;
                    const district = (String(param.district || '').toLowerCase() === 'other')
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

                // Kano city centre — where the map opens when an address cannot be
                // resolved and the officer has to locate the plot on the imagery.
                _kanoCenter: { lat: 12.0022, lng: 8.5920 },

                setPin(param, index, lat, lng, recenter = false) {
                    param.latitude  = this._roundCoord(lat);
                    param.longitude = this._roundCoord(lng);

                    const map = this._maps[index];
                    const marker = this._markers[index];
                    if (map && marker) {
                        const pos = { lat: param.latitude, lng: param.longitude };
                        marker.setPosition(pos);
                        if (recenter) map.panTo(pos);
                    } else {
                        // First pin dropped by clicking a map that opened empty —
                        // renderPropertyMap creates the marker.
                        this.renderPropertyMap(param, index);
                    }
                },

                /**
                 * Create (or reuse) the Leaflet map for a file tab.
                 * Returns null while the map container or the map library is
                 * not ready yet.
                 */
                _ensureMap(param, index, center, zoom) {
                    const mapEl = document.getElementById('map-' + index);
                    if (!mapEl || typeof google === 'undefined' || !google.maps) return null;

                    let map = this._maps[index];
                    if (!map) {
                        map = new google.maps.Map(mapEl, {
                            center: center,
                            zoom: zoom,
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
                        map.setCenter(center);
                        // Container may have been display:none while hidden — nudge a resize.
                        google.maps.event.trigger(map, 'resize');
                    }
                    return map;
                },

                /**
                 * Open the map with no pin so the officer can click the property
                 * directly. Used when address lookup finds nothing — which is
                 * common for Kano plot addresses, so it must not be a dead end.
                 */
                openMapForManualPin(param, index) {
                    param.awaitingManualPin = true;
                    this.$nextTick(() => {
                        this._ensureMap(param, index, this._kanoCenter, 13);
                    });
                },

                renderPropertyMap(param, index, zoom = 17) {
                    this.$nextTick(() => {
                        const pos = { lat: param.latitude, lng: param.longitude };
                        const map = this._ensureMap(param, index, pos, zoom);
                        if (!map) return;

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

                /**
                 * Non-blocking corner toast. Geocoding is best-effort — the officer
                 * always finishes by dragging the pin — so its outcomes are reported
                 * here rather than through a modal that has to be dismissed.
                 */
                _mapToast(icon, title, text = '') {
                    if (typeof Swal === 'undefined') return;
                    Swal.fire({
                        toast: true,
                        position: 'top-end',
                        icon: icon,
                        title: title,
                        text: text,
                        showConfirmButton: false,
                        timer: 4500,
                        timerProgressBar: true
                    });
                },

                /** LGA-only address — the coarse fallback when the full one misses. */
                buildLgaAddress(param) {
                    const lga = (param.lga || '').toString().trim();
                    if (!lga || /^select /i.test(lga)) return null;
                    return [lga, 'KANO', 'NIGERIA'].join(', ');
                },

                geocodeLocation(param, index) {
                    const address = this.buildGeocodeAddress(param);
                    if (!address || !param.location) return;

                    if (typeof google === 'undefined' || !google.maps) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Map not ready',
                            text: 'The map is still loading. Please try again in a moment.'
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
                            // Kano plot/street addresses are largely absent from the map
                            // data, so a miss here is routine rather than exceptional.
                            // Drop back to the LGA centroid, which gets the officer to the
                            // right part of Kano to drag from.
                            this._geocodeLgaFallback(param, index);
                        } else {
                            this.openMapForManualPin(param, index);
                            this._mapToast('warning', 'Address lookup unavailable',
                                'Zoom to the property on the satellite map and click it to drop the pin.');
                        }
                    });
                },

                /**
                 * Second, coarser geocode attempt using only the LGA. Lands the pin on
                 * the LGA centroid at a wide zoom — deliberately approximate, and the
                 * toast says so, because the officer still has to place it exactly.
                 */
                _geocodeLgaFallback(param, index) {
                    const lgaAddress = this.buildLgaAddress(param);

                    if (!lgaAddress) {
                        this.openMapForManualPin(param, index);
                        this._mapToast('info', 'Pin the property on the map',
                            'The address could not be matched. Zoom in and click the property.');
                        return;
                    }

                    const geocoder = new google.maps.Geocoder();
                    geocoder.geocode({ address: lgaAddress, region: 'NG' }, (results, status) => {
                        if (status === 'OK' && results[0]) {
                            const pos = results[0].geometry.location;
                            param.latitude  = this._roundCoord(pos.lat());
                            param.longitude = this._roundCoord(pos.lng());
                            // Zoom 13 ≈ whole LGA in view: honest about the precision and
                            // gives room to pan to the actual plot.
                            this.renderPropertyMap(param, index, 13);
                            this._mapToast('info', 'Approximate location — ' + param.lga + ' LGA',
                                'The exact address was not found. Drag the pin onto the property.');
                        } else {
                            this.openMapForManualPin(param, index);
                            this._mapToast('info', 'Pin the property on the map',
                                'The address could not be matched. Zoom in and click the property.');
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

                    // The raw land_use_type stored on the record is not guaranteed to
                    // match a <option value> in the dropdown, and x-model can only select
                    // an option that exists — so an unmatched value silently renders as
                    // the empty placeholder. populateLandUseFromRecord() resolves it
                    // fuzzily (adding an option if truly unknown) and its change event
                    // syncs the resolved value back into param.land_use_type. Runs in
                    // $nextTick so the <select> and its options are in the DOM first.
                    this.$nextTick(() => {
                        if (typeof window.populateLandUseFromRecord !== 'function') return;
                        this.fileParams.forEach((param, index) => {
                            if (param.land_use_type) {
                                window.populateLandUseFromRecord(param.land_use_type, index);
                            }
                        });
                    });

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
                'Withdrawal (Allocation)':                            'Withdrawal',
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
            const seePicker    = document.getElementById('ts-see-picker');
            const reasonEl     = document.getElementById('ts-reason');
            const remarkEl     = document.getElementById('ts-remark');
            const regrantKindEl  = document.getElementById('ts-regrant-kind');
            const regrantLabelEl = document.getElementById('ts-regrant-label');
            const closedKindEl   = document.getElementById('ts-closed-kind');
            const closedLabelEl  = document.getElementById('ts-closed-label');
            const seeLabelEl   = document.getElementById('ts-see-label');
            const seeHintEl    = document.getElementById('ts-see-hint');
            const reasonRow    = document.getElementById('ts-reason-row');
            const remarkRow    = document.getElementById('ts-remark-row');

            if (!typeGroup) return;

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

            // Resolve the effective title type for statuses that still use a direction.
            const resolveType = (t) => {
                if (t === 'Re-grant')     return getRegrantKind()   || t;
                if (t === 'Closed')       return getClosedKind()    || t;
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

            // Statuses that name a counterpart file. They take that number from the Related File
            // Number card instead of a field of their own, and the system words their remark.
            const RELATED_SOURCED_TYPES = ['Re-grant', 'Resettlement', 'Closed'];

            // Statuses whose auto remark is a fixed sentence about a counterpart file, so the
            // Reason box has nothing to feed. Kept in step with buildRemarkFor() below.
            const REASONLESS_TYPES = ['Re-grant', 'Resettlement', 'Closed', ...CLASSIFICATION_TYPES];
            const usesReason = (t) => {
                if (!isRemarkType(t) || REASONLESS_TYPES.includes(t)) return false;
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
                if (type === 'Resettlement') {
                    const seeFileno = (seeHidden?.value || '').trim();
                    if (!seeFileno) return 'This File has been Resettled';
                    return `This File has been Resettled with related FileNo ${seeFileno}`;
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
                const verb   = TS_TYPE_VERB[type] || type;
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
                const needsSee  = seeApplies();

                // Deselecting happens silently in two cases — the Title Status Update radios
                // replace each other, and ticking "Normal" clears the rest in code — so neither
                // fires a change event on the option that lost its tick. Drop any stale sub-kind.
                if (!isRegrant && getRegrantKind()) setRegrantKind('');
                if (!isClosed && getClosedKind()) setClosedKind('');

                // Label the counterpart picker for whichever direction was chosen. The trailing
                // sentence is the same everywhere: the officer picks, they do not type.
                const fromRelated = ' Picked from the Related File Number section below; optional.';
                if (isRegrant) {
                    const to = getRegrantKind() === REGRANT_TO;
                    if (seeLabelEl) seeLabelEl.textContent = to ? 'Re-granted To FileNo' : 'Initial FileNo';
                    if (seeHintEl) {
                        seeHintEl.textContent = (to
                            ? 'The NEW file number this file was re-granted to.'
                            : 'The OLD file number this file was re-granted from.') + fromRelated;
                    }
                } else if (isClosed) {
                    const from = getClosedKind() === CONTINUED_FROM;
                    if (seeLabelEl) seeLabelEl.textContent = from ? 'Continued From FileNo' : 'Continued In FileNo';
                    if (seeHintEl) {
                        seeHintEl.textContent = (from
                            ? 'The CLOSED file number this file carries on from.'
                            : 'The file number this file continues in now that it is closed.') + fromRelated;
                    }
                } else if (isResettle) {
                    if (seeLabelEl) seeLabelEl.textContent = 'Related Resettlement FileNo';
                    if (seeHintEl) {
                        seeHintEl.textContent = 'The related file number for this resettlement.' + fromRelated;
                    }
                }

                // Reason only shows for statuses that actually quote it in their remark; drop
                // anything already typed so a hidden box can't submit a stale reason.
                const needsReason = remarkTypes.some(usesReason);
                reasonRow?.classList.toggle('hidden', !needsReason);
                if (!needsReason && reasonEl) reasonEl.value = '';

                // The system words these remarks itself from the related file number, so the
                // box is hidden rather than editable — it is still submitted.
                const remarkEditable = remarkTypes.some(t => !RELATED_SOURCED_TYPES.includes(t));
                remarkRow?.classList.toggle('hidden', hasType && !remarkEditable);

                statusBlock?.classList.toggle('hidden', !hasType);
                // NOTE: the counterpart file number (see_fileno) is OPTIONAL for all three
                // statuses. Do NOT add required validation on it. syncSeeOptions() owns the
                // row's visibility, since that depends on how many related files there are.
                if (needsSee) {
                    syncSeeOptions();
                } else {
                    seeRow?.classList.add('hidden');
                    resetSeeFileno();
                }
                if (typeof lucide !== 'undefined') lucide.createIcons();
            }

            // Only "Normal" is exclusive: checking it clears every other option, and checking
            // any other option clears "Normal". Dummy File, Temporary and the actionable title
            // statuses are all freely multi-selectable. Delegated on document since the
            // checkboxes span two separate cards.
            // Is a status that names a counterpart file currently selected?
            const seeApplies = () => getActionableTypes().some(t => RELATED_SOURCED_TYPES.includes(t));

            // The file numbers entered in the Related File Number card, de-duplicated.
            function relatedFileNos() {
                return Array.from(document.querySelectorAll('#related-files-wrapper .related-file-input'))
                    .map(i => (i.value || '').trim())
                    .filter(Boolean)
                    .filter((v, i, a) => a.indexOf(v) === i);
            }

            // Mirror those numbers into the counterpart picker. One related file selects itself;
            // several force an explicit choice, since only the officer knows which one the title
            // status refers to. A pick already made survives as long as it is still on the list.
            //
            // When a pick DISAPPEARS from the list it is never silently replaced. In edit mode
            // the list can change behind this form's back — unlinking a related file elsewhere
            // removes it — and auto-adopting "the only one left" would re-point a Re-grant /
            // Resettlement / Closed at a file the officer never chose. Instead the pick is
            // dropped and the row is forced open so the choice is made again explicitly.
            function syncSeeOptions() {
                if (!seePicker) return;
                const nos     = relatedFileNos();
                const current = (seeHidden?.value || '').trim();
                const pickDropped = current !== '' && !nos.includes(current);
                const mustChoose  = nos.length > 1 || pickDropped;

                seePicker.innerHTML = '';
                if (!nos.length) {
                    seePicker.appendChild(new Option('No related file number entered yet', ''));
                    seePicker.disabled = true;
                } else {
                    seePicker.disabled = false;
                    if (mustChoose) {
                        seePicker.appendChild(new Option('— Select the related file this applies to —', ''));
                    }
                    nos.forEach(n => seePicker.appendChild(new Option(n, n)));
                }

                // Auto-select the lone related file only when nothing was chosen before.
                const next = nos.includes(current)
                    ? current
                    : ((!pickDropped && nos.length === 1) ? nos[0] : '');
                seePicker.value = next;
                if (seeHidden) seeHidden.value = next;

                if (pickDropped) {
                    console.warn(
                        `Title status counterpart "${current}" is no longer a related file number of this record; the choice was cleared.`
                    );
                }

                // Only shown when there is a genuine choice to make. A lone related file
                // selects itself and none leaves nothing to pick, so the row stays out of
                // the way in both cases — the number is still used to word the remark.
                // A dropped pick always shows, otherwise it would be cleared invisibly.
                seeRow?.classList.toggle('hidden', !seeApplies() || !mustChoose);
                refreshRemark();
            }

            // A freshly picked status must not inherit the counterpart chosen for the previous one.
            function resetSeeFileno() {
                if (seeHidden) seeHidden.value = '';
                if (seePicker) seePicker.value = '';
            }

            // ---- Counterpart file number, asked for right after the status choice ------------
            //
            // Once the officer chooses a directed Re-grant or Closure, or Resettlement, the
            // next question is which related file it references, so a popup asks for that
            // number there and then. Whatever is entered is pre-filled into the Related File
            // Number card below, which stays fully editable afterwards.

            // How the counterpart reads for each direction, so the popup asks for the right thing.
            const SEE_LABEL_BY_KIND = {
                [REGRANT_FROM]:   'Re-granted From FileNo',
                [REGRANT_TO]:     'Re-granted To FileNo',
                'Resettlement':   'Related Resettlement FileNo',
                [CLOSED_TO]:      'Continued In FileNo',
                [CONTINUED_FROM]: 'Continued From FileNo',
            };
            const SEE_HELP_BY_KIND = {
                [REGRANT_FROM]:   'The OLD file number this file was re-granted from.',
                [REGRANT_TO]:     'The NEW file number this file was re-granted to.',
                'Resettlement':   'Select the related file number for this resettlement.',
                [CLOSED_TO]:      'The file number this file continues in now that it is closed.',
                [CONTINUED_FROM]: 'The CLOSED file number this file carries on from.',
            };

            // File numbers compare loosely: case and spacing vary by typist, and a "(T)"
            // temporary suffix names the same physical file as the number without it.
            const normFileNo = (v) => (v || '')
                .toString()
                .trim()
                .toUpperCase()
                .replace(/\s+/g, ' ')
                .replace(/\(\s*T\s*\)$/, '')
                .trim();

            // A file cannot be re-granted / resettled / continued from ITSELF — that is the
            // officer entering the number they are already indexing, so it is refused outright.
            function isSelfFileNo(no) {
                const self = normFileNo(getFileNo());
                return self !== '' && normFileNo(no) === self;
            }

            // Pre-fill the counterpart into the Related File Number card. Reuses a row that
            // already holds it, then the first blank row, and only then adds a new one — the
            // officer's own rows are never overwritten. Nothing is ever removed here: the number
            // stays there to be edited or deleted by hand, which is the point of pre-filling
            // rather than owning it.
            function prefillRelatedFileNo(no) {
                const wrapper = document.getElementById('related-files-wrapper');
                if (!wrapper || !no) return;

                const inputs = () => Array.from(wrapper.querySelectorAll('.related-file-input'));
                if (inputs().some(i => normFileNo(i.value) === normFileNo(no))) return; // already listed

                // A file with a counterpart is by definition a "Has Related File?" record.
                const hasCb = document.getElementById('has-related-file');
                if (hasCb && !hasCb.checked) {
                    hasCb.checked = true;
                    hasCb.dispatchEvent(new Event('change', { bubbles: true }));
                }

                const blank = inputs().find(i => !(i.value || '').trim());
                if (blank) {
                    blank.value = no;
                    blank.dispatchEvent(new Event('input', { bubbles: true }));
                    return;
                }

                // addRelatedFileRow() clones a row and fills it without firing 'input',
                // so re-sync by hand afterwards.
                if (typeof window.addRelatedFileRow === 'function') {
                    window.addRelatedFileRow(no);
                } else {
                    document.getElementById('add-related-file-btn')?.click();
                    const last = inputs().pop();
                    if (last) last.value = no;
                }
                syncSeeOptions();
            }

            // Adopt a counterpart: pre-fill it below, then hold it as the pick. syncSeeOptions()
            // rebuilds the picker from the card and only keeps a pick that is on it, which the
            // pre-fill has just made true — so re-assert afterwards.
            function applySeeFileNo(no) {
                const value = (no || '').trim();
                if (!value) return;
                prefillRelatedFileNo(value);
                syncSeeOptions();
                if (seeHidden) seeHidden.value = value;
                if (seePicker && Array.from(seePicker.options).some(o => o.value === value)) {
                    seePicker.value = value;
                }
                refreshRemark();
            }

            // Ask for the counterpart file number through the global file-number selector, so
            // the officer picks a real, existing file off the registry tabs rather than typing a
            // number that may not exist. Closing the selector without applying simply leaves the
            // number unset — it is optional.
            function promptSeeFileNo(kind) {
                const label = SEE_LABEL_BY_KIND[kind] || 'Related FileNo';
                const help  = SEE_HELP_BY_KIND[kind] || '';

                // No selector on the page (scripts still loading, or a stripped-down view) —
                // fall back to typing the number so the flow is never a dead end.
                if (typeof GlobalFileNoModal === 'undefined') {
                    const entered = (window.prompt(`${label}\n\n${help}\n\n(Leave blank to skip)`) || '').trim();
                    if (!entered) return;
                    if (isSelfFileNo(entered)) {
                        window.alert(`${label} cannot be ${getFileNo()} — that is the file being indexed.`);
                        promptSeeFileNo(kind);
                        return;
                    }
                    applySeeFileNo(entered);
                    return;
                }

                GlobalFileNoModal.open({
                    // This picks the OTHER file, so it must not overwrite the fields describing
                    // the file being indexed — same stance as the Related File Number rows.
                    autoPopulateGenericFields: false,
                    callback: function (data) {
                        const picked = (data?.fileNumber || '').trim();
                        if (!picked) return;
                        if (isSelfFileNo(picked)) {
                            flagSelfFileNo(label, picked, kind);
                            return;
                        }
                        applySeeFileNo(picked);
                    },
                });
            }

            // The counterpart may not be the file being indexed — nothing is re-granted,
            // resettled or continued from itself. Say so, then put the selector back up.
            function flagSelfFileNo(label, picked, kind) {
                const msg = `${picked} is the file you are indexing. ${label} must be a different file.`;

                if (typeof Swal === 'undefined') {
                    window.alert(msg);
                    reopenSeeSelector(kind);
                    return;
                }

                Swal.fire({
                    icon: 'error',
                    title: 'Same file number',
                    text: msg,
                    confirmButtonText: 'Pick another file',
                    confirmButtonColor: '#4f46e5',
                    // The selector sits at z-index 2000000 and lingers for a moment after
                    // applying, so this has to be told to sit above it. The class carries
                    // the rule (see components/global-fileno-modal.blade.php); willOpen is
                    // the backstop — didOpen runs only after the open animation, by which
                    // point the card has already painted behind the selector.
                    customClass: { container: 'fileno-selector-alert' },
                    willOpen: (el) => {
                        const container = el.closest('.swal2-container') || el.parentElement;
                        if (container) container.style.zIndex = '2000001';
                    },
                }).then(() => reopenSeeSelector(kind));
            }

            // apply() closes the selector on a timer, so reopening straight away would be undone
            // by that pending close. Wait for it to actually go, with a cap so a change in the
            // selector's own behaviour can never strand the officer without a prompt.
            function reopenSeeSelector(kind) {
                const el = document.getElementById('global-fileno-modal');
                let tries = 0;
                (function wait() {
                    if (!el || el.classList.contains('hidden') || tries++ > 40) {
                        promptSeeFileNo(kind);
                        return;
                    }
                    setTimeout(wait, 50);
                })();
            }

            // Continue from the selected status/direction to its related file. The status UI is
            // settled before the selector opens — picking a number refreshes the remark itself,
            // so closing the selector without picking leaves a consistent form either way.
            function afterKindChosen(kind, done) {
                done();
                if (kind) promptSeeFileNo(kind);
            }

            // Ask which way the re-grant runs when "Re-grant" is picked. Cancelling clears the
            // selection — a direction is what makes the Initial FileNo readable either way.
            function promptRegrantKind(cb) {
                const finish = () => { toggleStatusFields(); refreshRemark(); };
                resetSeeFileno();

                if (typeof Swal === 'undefined') {
                    const isFrom = window.confirm('Re-grant direction:\n\nOK = Re-granted From (this is the new file)\nCancel = Re-granted To (this is the old file)');
                    setRegrantKind(isFrom ? REGRANT_FROM : REGRANT_TO);
                    afterKindChosen(getRegrantKind(), finish);
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
                    afterKindChosen(getRegrantKind(), finish);
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
                    afterKindChosen(getClosedKind(), finish);
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
                    afterKindChosen(getClosedKind(), finish);
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

                if (cb.value === 'Resettlement' && cb.checked) {
                    resetSeeFileno();
                    afterKindChosen('Resettlement', () => {
                        toggleStatusFields();
                        refreshRemark();
                    });
                    return;
                }

                toggleStatusFields();
                refreshRemark();
            });
            reasonEl?.addEventListener('input', refreshRemark);

            // The counterpart file number always comes from the Related File Number card, so
            // there is one place to enter it and the two can never disagree.
            seePicker?.addEventListener('change', function () {
                if (seeHidden) seeHidden.value = seePicker.value || '';
                refreshRemark();
            });

            // The related rows are readonly inputs driven by their own picker/modal. The picker
            // dispatches 'input', but Clear and Remove only mutate the DOM — so watch for both,
            // deferring the click so the row is already gone when we re-read the list.
            const relatedWrapper = document.getElementById('related-files-wrapper');
            relatedWrapper?.addEventListener('input', function (e) {
                if (e.target?.classList?.contains('related-file-input')) syncSeeOptions();
            });
            relatedWrapper?.addEventListener('click', function () { setTimeout(syncSeeOptions, 0); });
            document.getElementById('has-related-file')?.addEventListener('change', function () {
                setTimeout(syncSeeOptions, 0);
            });

            // Called by handleRegistryFieldToggling() when the chosen registry (SLTR /
            // KANGIS / DCIV) hides both Title Status cards — drop any selection so a
            // stale status isn't submitted with the hidden fields.
            window.tsClearSelections = function () {
                typeCbs().forEach(cb => { cb.checked = false; });
                setRegrantKind('');
                setClosedKind('');
                if (reasonEl) reasonEl.value = '';
                if (remarkEl) remarkEl.value = '';
                resetSeeFileno();
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

                // The statuses that name a counterpart file take it from the Related File Number
                // card. Re-read it here: the related picker can write a value without firing an
                // event, so the box on screen may be a step behind the form being submitted.
                const usesSee = types.some(t => RELATED_SOURCED_TYPES.includes(t));
                if (usesSee) syncSeeOptions();

                const reason = (reasonEl?.value || '').trim();
                const customRemark = (remarkEl?.value || '').trim();
                // When classification flags are also selected, the Remark box holds their lines
                // too, so don't reuse it verbatim for the title-status record — regenerate per type.
                const hasClassification = !!document.querySelector('.ts-classification-cb:checked');

                // Per-type remarks (parallel to `title_types`). A single selection may keep the
                // user-edited remark; otherwise each type gets its own generated remark. The
                // related-sourced statuses are always regenerated — their box is not editable.
                const remarks = types.map(function (type) {
                    const reuseCustom = types.length === 1 && customRemark && !hasClassification
                        && !RELATED_SOURCED_TYPES.includes(type);
                    return reuseCustom ? customRemark : buildRemarkFor(type);
                });

                // Report the chosen sub-kinds for Re-grant and Closed. Resettlement remains
                // a single, non-directional status.
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

    @include('fileindexing.addons.partials.update_bootstrap')

    {{-- KANGIS update bootstrap. Runs after create-indexing-dialog.js has initialised.
         Populates the form straight from the server-provided row and pins the file
         number so this page can only ever update the variant it was opened for. --}}
    <script>
        (function initKangisUpdateMode() {
            const mode = window.kangisUpdateMode;
            if (!mode || !window.editingRecord) {
                return;
            }

            function freezeFileNumber() {
                const value = mode.fileNumber || '';

                // #file-number-display is what the user sees; #fileno is the hidden
                // field the form reads. Both are pinned to the stored value.
                const display = document.getElementById('file-number-display');
                if (display) {
                    if (value) display.value = value;
                    display.readOnly = true;
                    display.setAttribute('aria-readonly', 'true');
                    display.classList.add('cursor-not-allowed');
                }

                const hidden = document.getElementById('fileno');
                if (hidden && value) {
                    hidden.value = value;
                }

                // Remove the only two ways to re-open file-number selection.
                ['select-file-number-btn', 'clear-file-number-btn'].forEach(function (id) {
                    const btn = document.getElementById(id);
                    if (btn) {
                        btn.disabled = true;
                        btn.classList.add('hidden');
                    }
                });

                if (display && !document.getElementById('kangis-update-fileno-hint')) {
                    const hint = document.createElement('p');
                    hint.id = 'kangis-update-fileno-hint';
                    hint.className = 'mt-2 text-xs text-purple-700';
                    hint.innerHTML = 'File number is fixed for this physical file'
                        + (mode.placeholder
                            ? ' (<strong class="font-mono">' + mode.placeholder + '</strong>)'
                            : '')
                        + '. Change the <strong>KANGIS FileNo Placeholder</strong> to correct which physical folder this record describes.';
                    display.closest('.form-group')?.appendChild(hint);
                }
            }

            // applyExistingIndexedRecord() does not touch General Registry or the
            // placeholder fields — on the create page those are driven by the
            // file-number modal, which never runs here. Set them explicitly.
            function applyKangisFields() {
                const record = window.editingRecord || {};

                const registrySelect = document.getElementById('general-registry');
                if (registrySelect) {
                    const wanted = (record.general_registry || 'KANGIS').toUpperCase();
                    const option = Array.from(registrySelect.options).find(function (o) {
                        return (o.value || '').toUpperCase() === wanted;
                    }) || Array.from(registrySelect.options).find(function (o) {
                        return (o.value || '').toUpperCase().includes('KANGIS');
                    });

                    if (option) {
                        registrySelect.value = option.value;
                        registrySelect.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                }

                // Belt and braces: the placeholder is the whole point of this page, so it
                // must be visible even if the registry toggle above found no match.
                const wrapper = document.getElementById('kangis-fileno-placeholder-wrapper');
                if (wrapper) {
                    wrapper.classList.remove('hidden');
                }

                let placeholder = (mode.placeholder || record.kangis_fileno_placeholder || '').trim();
                if (!placeholder) {
                    return;
                }

                // Trailing "(T)" is carried by the Temporary checkbox, not the serial.
                const tempMatch = placeholder.match(/\(\s*T\s*\)\s*$/i);
                if (tempMatch) {
                    placeholder = placeholder.replace(/\(\s*T\s*\)\s*$/i, '').trim();
                    const tempBox = document.getElementById('kangis-fileno-is-temp');
                    if (tempBox) {
                        tempBox.checked = true;
                    }
                }

                // "MLKN 001" -> prefix "MLKN", serial "001".
                const parts = placeholder.split(/\s+/);
                const prefix = (parts.shift() || '').toUpperCase();
                const serial = parts.join(' ').trim();

                const prefixSelect = document.getElementById('kangis-fileno-prefix');
                if (prefixSelect && prefix) {
                    let option = Array.from(prefixSelect.options).find(function (o) {
                        return (o.value || '').toUpperCase() === prefix;
                    });

                    // The prefix may have been retired from kangis_prefixes since the
                    // record was created — keep it selectable rather than losing it.
                    if (!option) {
                        option = document.createElement('option');
                        option.value = prefix;
                        option.textContent = prefix;
                        prefixSelect.appendChild(option);
                    }

                    prefixSelect.value = option.value;
                    prefixSelect.dispatchEvent(new Event('change', { bubbles: true }));
                }

                const serialInput = document.getElementById('kangis-fileno-serial');
                if (serialInput && serial) {
                    serialInput.value = serial;
                    serialInput.dispatchEvent(new Event('input', { bubbles: true }));
                }

                const hiddenPlaceholder = document.getElementById('kangis-fileno-placeholder');
                if (hiddenPlaceholder && !hiddenPlaceholder.value) {
                    hiddenPlaceholder.value = mode.placeholder || '';
                }
            }

            window.addEventListener('fileindex:update-populated', function () {
                applyKangisFields();
                freezeFileNumber();
            });
        })();
    </script>
@endsection

@push('scripts')
    {{-- Map stack — used by the Property Details "Apply & Pin on Map" geocoder --}}
    @include('partials.maps_scripts')
@endpush
