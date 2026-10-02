<style>
    /* Keep SweetAlert / dialogs above the instrument capture modal (z-index: 1000020) */
    .swal2-container { z-index: 2000000 !important; }
    .swal2-backdrop-show { z-index: 1999999 !important; }

    /* Specific scrollbar styling and flex constraints for registration-form */
    #registration-form {
        overflow-y: auto !important;
        min-height: 0 !important;
        flex: 1 1 0% !important;
    }
</style>
<div id="registration-dialog"
    class="dialog-backdrop hidden transition-opacity duration-300 ease-in-out z-50 fixed inset-0 flex items-center justify-center bg-black/50">
    <div class="dialog-content bg-white rounded-xl shadow-2xl w-full flex flex-col m-4 animate-scale-in overflow-hidden"
        style="max-width: 65vw; max-height: 98vh;">
        <!-- Header -->
        <div
            class="px-6 py-4 border-b border-gray-100 flex items-center justify-between bg-white sticky top-0 z-10">
            <div>
                <h2 id="dialog-title" class="text-xl font-bold text-gray-800">Capture Instrument</h2>
                <p id="dialog-subtitle" class="text-sm text-gray-500 mt-0.5">Enter the details for the new instrument</p>
            </div>
            <button type="button" id="dialog-close-btn"
                class="p-2 hover:bg-gray-100 rounded-lg transition-colors text-gray-400 hover:text-gray-600"
                aria-label="Close">
                <i data-lucide="x" class="h-6 w-6"></i>
            </button>
        </div>

        <form id="registration-form" class="flex-1 overflow-y-auto custom-scrollbar">
            <div class="p-6 space-y-6">
                @csrf
                <input type="hidden" id="instrument_id" name="instrument_id" value="{{ $record->id ?? '' }}">
                
                <!-- Subtype Selection (Initially hidden) -->
                <div id="subtype-selector-container" class="hidden space-y-4 mb-6 p-4 bg-blue-50/50 rounded-xl border border-blue-100/50">
                    <div class="flex items-start gap-3">
                        <div class="mt-1 bg-blue-500/10 p-2 rounded-lg">
                            <i data-lucide="info" class="h-4 w-4 text-blue-600"></i>
                        </div>
                        <div class="flex-1">
                            <label class="text-sm font-semibold text-blue-900 mb-1 block">Select Specific Instrument Subtype</label>
                            <p class="text-xs text-blue-700/80 mb-3 leading-relaxed">
                                Some instruments share the same legal foundation but differ in structure or parties involved. 
                                Please select the correct subtype to ensure accurate documentation and processing.
                            </p>
                            <div class="relative group">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i data-lucide="layers" class="h-4 w-4 text-blue-400 group-focus-within:text-blue-500 transition-colors"></i>
                                </div>
                                <select id="instrument-subtype-select" 
                                    class="w-full pl-9 pr-3 py-2.5 bg-white border border-blue-200 rounded-lg text-sm text-gray-700 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition-all cursor-pointer">
                                    <!-- Options injected via JS -->
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- PRA History Alert Placeholder -->
                <div id="pra-history-alert"
                    class="hidden bg-gradient-to-r from-indigo-50 to-blue-50 border border-indigo-100 p-4 mb-6 rounded-2xl shadow-sm animate-pulse-subtle flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="bg-indigo-500/10 p-2 rounded-xl">
                            <i data-lucide="history" class="h-5 w-5 text-indigo-600"></i>
                        </div>
                        <div>
                            <p class="text-sm text-indigo-900 font-bold leading-none">History Found</p>
                            <p class="text-[11px] text-indigo-600/70 mt-1 font-medium italic">This file number has associated PRA & Instrument Registration record(s).</p>
                        </div>
                    </div>
                    <button type="button" id="btn-view-pra-history"
                        class="px-4 py-2 bg-indigo-600 text-white rounded-xl text-xs font-bold hover:bg-indigo-700 transition-all duration-200 shadow-sm flex items-center gap-2 hover:scale-105 active:scale-95">
                        <i data-lucide="eye" class="h-3.5 w-3.5"></i>
                        Explore History
                    </button>
                </div>
                
                {{-- Commissioned-file mode. Shown only when this dialog was opened to capture the
                     OP behind a file number that was already commissioned without one: submit
                     then writes the OP and its Transfer of Title instead of registering an
                     instrument. Driven by window.opCommissionedFileMode in instruments-capture.js. --}}
                <div id="opcf-band" class="hidden bg-blue-50/60 border border-blue-200 rounded-2xl p-4 mb-6">
                    <div class="flex items-start gap-3 mb-3">
                        <div class="bg-blue-500/10 p-2 rounded-lg">
                            <i data-lucide="file-check-2" class="h-4 w-4 text-blue-600"></i>
                        </div>
                        <div>
                            <p class="text-sm font-bold text-blue-900 leading-none">Already-Commissioned File</p>
                            <p class="text-[11px] text-blue-700/80 mt-1">
                                Pick the commissioned file this OP belongs to. Saving writes one PRA row —
                                the Occupancy Permit itself, on this file number. No Transfer of Title is
                                created: the permit is simply being recorded, the holder is not changing.
                            </p>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-end gap-3">
                        <div class="flex-1 min-w-[16rem]">
                            <label for="opcf-file-no" class="text-xs font-semibold text-gray-800 mb-1 block">
                                Commissioned File Number <span class="text-rose-500">*</span>
                            </label>
                            {{-- Disabled: the number is only ever set by the Select picker, which
                                 runs the lookup itself — there is nothing to type or confirm. --}}
                            <div class="flex items-center gap-2">
                                <input type="text" id="opcf-file-no" placeholder="No file selected" disabled
                                    class="flex-1 px-3 py-2 bg-gray-100 border border-gray-300 rounded-lg text-gray-700 font-mono text-sm uppercase">
                                <button type="button" id="opcf-select-btn"
                                    class="px-3 py-2 bg-white text-blue-600 font-medium rounded-lg border border-blue-200 hover:bg-blue-50 text-sm whitespace-nowrap">
                                    Select
                                </button>
                            </div>
                        </div>
                    </div>

                    <div id="opcf-result" class="hidden mt-3 grid grid-cols-1 md:grid-cols-3 gap-4 text-xs">
                        <div>
                            <div class="text-gray-400 font-semibold uppercase tracking-wide text-[10px]">Commissioned To</div>
                            <div id="opcf-file-name" class="font-semibold text-gray-800 mt-0.5">—</div>
                        </div>
                        <div>
                            <div class="text-gray-400 font-semibold uppercase tracking-wide text-[10px]">Prop ID</div>
                            <div id="opcf-prop-id" class="font-mono text-gray-700 mt-0.5">—</div>
                        </div>
                        <div>
                            <div class="text-gray-400 font-semibold uppercase tracking-wide text-[10px]">Plot / Location</div>
                            <div id="opcf-plot" class="text-gray-700 mt-0.5">—</div>
                        </div>
                    </div>
                    <div id="opcf-error" class="hidden mt-3 rounded-lg border border-rose-300 bg-rose-50 px-3 py-2 text-xs text-rose-800"></div>
                </div>

                @php
                    $storeRoute = isset($isEdit) && $isEdit ? route('instruments.update', $record->id) : route('instruments.store');
                    $method = isset($isEdit) && $isEdit ? 'PUT' : 'POST';
                @endphp

                <input type="hidden" id="storeUrl" value="{{ $storeRoute }}" />
                <input type="hidden" id="formMethod" value="{{ $method }}" />
                <input type="hidden" id="generateParticularsUrl"
                    value="{{ route('instruments.generateParticulars') }}" />

                <!-- Hidden fields for file number storage -->
                <input type="hidden" id="hidden_mlsFNo" name="mlsFNo" value="{{ $record->mlsFNo ?? '' }}">
                <input type="hidden" id="hidden_kangisFileNo" name="kangisFileNo" value="{{ $record->kangisFileNo ?? '' }}">
                <input type="hidden" id="hidden_NewKANGISFileno" name="NewKANGISFileno" value="{{ $record->NewKANGISFileno ?? '' }}">
                <input type="hidden" id="hidden_fileno" name="fileno" value="{{ $record->fileno ?? '' }}">


                <!-- OP Type Selection (Moved to top) -->
                <div id="op-type-container" class="hidden mb-6 bg-blue-50 p-3 rounded-lg border border-blue-100">
                    <label class="block text-sm font-semibold text-blue-800 mb-2">Occupancy Permit (OP) Type</label>
                    <div class="flex items-center gap-6">
                        <div class="flex items-center gap-2">
                            <input type="radio" id="op_type_resettlement" name="op_type" value="OP Resettlement" 
                                class="w-4 h-4 text-blue-600 border-gray-300 focus:ring-blue-500 cursor-pointer">
                            <label for="op_type_resettlement" class="text-sm text-gray-700 cursor-pointer">Resettlement</label>
                        </div>
                        <div class="flex items-center gap-2">
                            <input type="radio" id="op_type_direct_allocation" name="op_type" value="OP Direct Allocation" 
                                class="w-4 h-4 text-blue-600 border-gray-300 focus:ring-blue-500 cursor-pointer">
                            <label for="op_type_direct_allocation" class="text-sm text-gray-700 cursor-pointer">Direct Allocation</label>
                        </div>
                    </div>
                </div>

                <!-- Certificate of Occupancy Type Selection -->
                <div id="coo-type-container" class="hidden mb-6 bg-teal-50 p-3 rounded-lg border border-teal-100">
                    <label class="block text-sm font-semibold text-teal-800 mb-2">Certificate of Occupancy Type</label>
                    <div class="flex items-center gap-6">
                        <select id="cofoType" name="cofo_type"
                            class="w-full px-3 py-2 bg-white border border-teal-200 rounded-lg text-sm focus:ring-1 focus:ring-teal-500 outline-none">
                            <option value="">Select C of O Type</option>
                            <option value="Direct Allocation">Direct Allocation</option>
                            <option value="Recertification">Recertification</option>
                            <option value="Conversion">Conversion</option>
                        </select>
                    </div>
                </div>

                @if(!(isset($isView) && $isView))
                    <div class="mb-4 flex justify-end gap-2">
                        <button type="button" id="hard-refresh-registration-form-btn"
                            class="hidden px-3 py-2 bg-white text-blue-700 font-medium rounded-lg border border-blue-300 hover:bg-blue-50 text-sm whitespace-nowrap">
                            Hard Fresh
                        </button>
                        <button type="button" id="reset-registration-form-btn"
                            class="px-3 py-2 bg-white text-amber-700 font-medium rounded-lg border border-amber-300 hover:bg-amber-50 text-sm whitespace-nowrap">
                            Reset Form
                        </button>
                    </div>
                @endif

                <!-- File Number, Registration & Instrument Settings -->
                <div class="grid grid-cols-3 gap-6">
                    <!-- File Number Section -->
                    <div class="border-l-4 border-blue-500 pl-4">
                        <h3 id="fileno-label" class="text-sm font-semibold text-gray-800 mb-4">File Number</h3>

                        <div class="flex items-center gap-2">
                            <div class="relative flex-1 group">
                                <div
                                    class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i data-lucide="file-text" class="h-4 w-4 text-gray-400"></i>
                                </div>
                                <input type="text" id="display_fileno"
                                    class="w-full pl-9 pr-3 py-2 bg-white border border-gray-300 rounded-lg text-gray-700 font-mono text-sm focus:ring-1 focus:ring-blue-500"
                                    placeholder="No file number selected" readonly 
                                    value="{{ ($record->mlsFNo ?? $record->kangisFileNo ?? $record->NewKANGISFileno ?? $record->fileno ?? '') }}">
                            </div>
                            @if(!(isset($isView) && $isView))
                                <button type="button" id="select-file-btn"
                                    class="px-3 py-2 bg-white text-blue-600 font-medium rounded-lg border border-blue-200 hover:bg-blue-50 text-sm whitespace-nowrap">
                                    Select
                                </button>
                            @endif
                        </div>
                        <p class="mt-2 text-xs text-gray-400 truncate" id="file_source_info">
                            @if(isset($record))
                                Linked to property records.
                            @else
                                Select a file to associate.
                            @endif
                        </p>

                        <!-- OP Serial Number (Hidden by default) -->
                        <div id="op_serial_number_container" class="mt-4 hidden">
                            <label for="op_serial_number" class="text-xs font-semibold text-gray-800 mb-1 block">OP SerialNo</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i data-lucide="hash" class="h-4 w-4 text-gray-400"></i>
                                </div>
                                <input type="text" id="op_serial_number" name="op_serial_number"
                                    class="w-full pl-9 pr-3 py-2 bg-white border border-gray-300 rounded-lg text-gray-700 text-sm focus:ring-1 focus:ring-blue-500"
                                    oninput="this.value = this.value.replace(/[^0-9]/g, '').replace(/^0+/, '')"
                                    placeholder="Enter OP SerialNo" value="{{ $record->op_serial_number ?? '' }}">
                            </div>
                        </div>

                        <!-- Land Use (Purpose) Split Dropdowns -->
                        <div id="land_use_section" class="mt-4 grid grid-cols-2 gap-3">
                            <div>
                                <label for="land_use_id" class="text-xs font-semibold text-gray-800 mb-1 block" required>Land Use</label>
                                <select id="land_use_id" name="land_use_id" class="w-full px-3 py-2 bg-white border border-gray-300 rounded-lg text-sm focus:ring-1 focus:ring-blue-500 outline-none">
                                    <option value="">Select Land Use</option>
                                    <!-- Populated via JS -->
                                </select>
                            </div>
                            <div>
                                <label for="purpose_id" class="text-xs font-semibold text-gray-800 mb-1 block">Purpose</label>
                                <select id="purpose_id" name="purpose_id" class="w-full px-3 py-2 bg-white border border-gray-300 rounded-lg text-sm focus:ring-1 focus:ring-blue-500 outline-none" required>
                                    <option value="">Select Purpose</option>
                                    <!-- Populated via JS based on Land Use -->
                                </select>
                            </div>
                        </div>

                        <!-- Keep hidden inputs for backward compatibility and string values -->
                        <input type="hidden" id="land_use" name="land_use" value="{{ $record->land_use ?? '' }}">
                        <input type="hidden" id="purpose" name="purpose" value="{{ $record->purpose ?? '' }}">
                        <input type="hidden" id="initial_land_use_id" value="{{ $record->land_use_id ?? '' }}">
                        <input type="hidden" id="initial_purpose_id" value="{{ $record->purpose_id ?? '' }}">
                        <div style="display:none">
                        <span id="land_use_display" class="hidden"></span> 
                        </div> 
                    </div>

                    <!-- Entry Date Section -->
                    <div class="border-l-4 border-gray-400 pl-4">
                        <h3 class="text-sm font-semibold text-gray-800 mb-4">Transaction Date</h3>
                        <div class="relative mb-4">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i data-lucide="calendar-days" class="h-4 w-4 text-gray-400"></i>
                            </div>
                            <input
                                type="date"
                                id="transactionDate"
                                name="transactionDate"
                                class="w-full pl-10 px-4 py-2.5 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-100 focus:border-blue-400 outline-none transition-all text-sm"
                                value="{{ isset($record->transaction_date) ? \Carbon\Carbon::parse($record->transaction_date)->format('Y-m-d') : '' }}">
                        </div>

                        <h3 class="text-sm font-semibold text-gray-800 mb-4">Entry Date</h3>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i data-lucide="calendar" class="h-4 w-4 text-gray-400"></i>
                            </div>
                            <input
                                type="date"
                                id="entryDate"
                                name="entryDate"
                                readonly
                                tabindex="-1"
                                aria-disabled="true"
                                class="w-full pl-10 px-4 py-2.5 bg-gray-100 border border-gray-300 rounded-lg text-gray-500 cursor-not-allowed outline-none text-sm"
                                value="{{ isset($record->instrument_date) ? \Carbon\Carbon::parse($record->instrument_date)->format('Y-m-d') : '' }}">
                        </div>

                        <!-- OP Registration Particulars (Occupancy Permit) -->
                        <div id="op-registration-details" class="mt-4 hidden">
                            <p class="text-xs font-semibold text-gray-800 mb-2">Registration Details</p>
                            <div class="grid grid-cols-3 gap-3">
                                <div>
                                    <label for="serial_no" class="text-[11px] font-semibold text-gray-500 mb-1 block">Serial No</label>
                                    <input type="text" id="serial_no" name="serial_no" inputmode="numeric"
                                        class="w-full px-3 py-2 bg-white border border-gray-300 rounded-lg text-sm focus:ring-1 focus:ring-blue-500"
                                        placeholder="Serial No">
                                </div>
                                <div>
                                    <label for="reg_page_no_display" class="text-[11px] font-semibold text-gray-500 mb-1 block">Page No</label>
                                    <input type="text" id="reg_page_no_display" inputmode="numeric"
                                        class="w-full px-3 py-2 bg-gray-100 border border-gray-300 rounded-lg text-sm text-gray-500 cursor-not-allowed"
                                        placeholder="Auto-filled" readonly aria-disabled="true">
                                    <input type="hidden" id="reg_page_no" name="page_no">
                                </div>
                                <div>
                                    <label for="volume_no" class="text-[11px] font-semibold text-gray-500 mb-1 block">Vol No</label>
                                    <input type="text" id="volume_no" name="volume_no" inputmode="numeric"
                                        class="w-full px-3 py-2 bg-white border border-gray-300 rounded-lg text-sm focus:ring-1 focus:ring-blue-500"
                                        placeholder="Vol No">
                                </div>
                            </div>

                            <div class="mt-3">
                                <label for="registration_number" class="text-[11px] font-semibold text-gray-500 mb-1 block">Registration Particulars</label>
                                <input type="text" id="registration_number" name="registration_number" readonly
                                    class="w-full px-3 py-2 bg-gray-100 border border-gray-300 rounded-lg text-sm text-gray-600"
                                    placeholder="Serial/Page/Vol">
                            </div>

                            <div class="mt-3 grid grid-cols-2 gap-3">
                                <div>
                                    <label for="registrationDate" class="text-[11px] font-semibold text-gray-500 mb-1 block">Reg Date</label>
                                    <input type="date" id="registrationDate" name="registrationDate"
                                        class="w-full px-3 py-2 bg-white border border-gray-300 rounded-lg text-sm focus:ring-1 focus:ring-blue-500"
                                        value="{{ old('registrationDate', isset($record->reg_date) ? substr((string) $record->reg_date, 0, 10) : '') }}">
                                </div>
                                <div>
                                    <label for="registrationTime" class="text-[11px] font-semibold text-gray-500 mb-1 block">Reg Time</label>
                                    <input type="time" id="registrationTime" name="registrationTime"
                                        class="w-full px-3 py-2 bg-white border border-gray-300 rounded-lg text-sm focus:ring-1 focus:ring-blue-500"
                                        value="{{ old('registrationTime', $record->reg_time ?? '') }}">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Instrument Info Display Section -->
                    <div id="instrument-type-info-display"
                        class="border-l-4 border-gray-200 pl-4 h-full flex flex-col justify-center bg-gray-50 rounded-r-lg pr-2">
                        @if(isset($record))
                            <div class="flex items-center gap-2">
                                <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center text-blue-600">
                                    <i data-lucide="file-text" class="h-4 w-4"></i>
                                </div>
                                <div>
                                    <h4 class="text-sm font-bold text-gray-800">{{ $record->instrument_type }}</h4>
                                    <p class="text-[10px] text-gray-500 uppercase tracking-wider">Selected Instrument</p>
                                </div>
                            </div>
                        @else
                            <p class="text-xs text-gray-400 italic">Select an instrument type to view details.</p>
                        @endif
                    </div>
                </div>

                <hr class="my-6 border-gray-200">

                <!-- Parties & Instrument Details (3 columns) -->
                <div class="grid grid-cols-3 gap-6">
                    <!-- First Party -->
                    <div class="border-l-4 border-green-500 pl-4">
                        <h3 id="first-party-title" class="text-sm font-semibold text-gray-800 mb-4">{{ isset($record) ? 'Party 1' : 'Grantor' }}</h3>

                        <div class="space-y-3">
                            <x-instrument-input id="firstPartyName" label="" icon="user" placeholder="Name"
                                value="{{ $record->party_1_name ?? '' }}" />

                            <div class="grid grid-cols-2 gap-3">
                                <x-instrument-input id="firstPartyHouseNo" label="" icon="home" placeholder="House No"
                                    value="{{ $record->party_1_house_no ?? '' }}" />
                                <x-instrument-input id="firstPartyPlotNo" label="" icon="hash" placeholder="Plot No"
                                    value="{{ $record->party_1_plot_no ?? '' }}" />
                            </div>

                            <div id="first-party-row-1" class="grid grid-cols-2 gap-3">
                                <div>
                                    <x-instrument-select id="firstPartyStreet" label="" icon="map-pin"
                                        :options="$streetNames->merge([(object)['name' => 'Others']])"
                                        placeholder="Select Street"
                                        value="{{ old('firstPartyStreet', $record->party_1_address ?? '') }}"
                                        optionValue="name" optionLabel="name" />
                                    <div id="manual_firstPartyStreet_container" class="mt-2 hidden">
                                        <x-instrument-input id="manual_firstPartyStreet" name="manual_firstPartyStreet" label="" icon="edit-3"
                                            placeholder="Specify Street" />
                                    </div>
                                </div>
                                <div id="district-container">
                                    <x-instrument-select id="firstPartyDistrict" label="" icon="map-pin"
                                        :options="$districts->merge([(object)['name' => 'Others']])"
                                        placeholder="Select District" value="{{ $record->party_1_district ?? '' }}"
                                        optionValue="name" optionLabel="name" />
                                    <div id="manual_firstPartyDistrict_container" class="mt-2 hidden">
                                        <x-instrument-input id="manual_firstPartyDistrict" name="manual_firstPartyDistrict" label="" icon="edit-3"
                                            placeholder="Specify District" />
                                    </div>
                                </div>
                            </div>

                            <div id="kano-grantor-fields" class="space-y-3">
                                <div id="first-party-row-2" class="grid grid-cols-2 gap-3">
                                    <x-instrument-select id="firstPartyLga" label="" icon="map"
                                        :options="[]" placeholder="LGA" value="{{ $record->party_1_lga ?? $record->party_1_city ?? '' }}" />
                                    <x-instrument-select id="firstPartyState" label="" icon="map-pin"
                                        :options="$states" placeholder="State" value="{{ $record->party_1_state ?? 'Kano' }}" />
                                </div>
                                <x-instrument-input id="firstPartyPhone" label="" icon="phone"
                                    placeholder="Phone Number" value="{{ $record->party_1_phone ?? '' }}" />
                            </div>
                        </div>
                    </div>

                    <!-- Second Party -->
                    <div class="border-l-4 border-amber-500 pl-4">
                        <h3 id="second-party-title" class="text-sm font-semibold text-gray-800 mb-4">{{ isset($record) ? 'Party 2' : 'Grantee' }}</h3>

                        <div class="space-y-3">
                            <x-instrument-input id="secondPartyName" label="" icon="user-plus"
                                placeholder="Name" value="{{ $record->party_2_name ?? '' }}" />

                            <div class="grid grid-cols-2 gap-3">
                                <x-instrument-input id="secondPartyHouseNo" label="" icon="home" placeholder="House No"
                                    value="{{ $record->party_2_house_no ?? '' }}" />
                                <x-instrument-input id="secondPartyPlotNo" label="" icon="hash" placeholder="Plot No"
                                    value="{{ $record->party_2_plot_no ?? '' }}" />
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <x-instrument-select id="secondPartyStreet" label="" icon="map-pin"
                                        :options="$streetNames->merge([(object)['name' => 'Others']])"
                                        placeholder="Select Street"
                                        value="{{ old('secondPartyStreet', $record->party_2_address ?? '') }}"
                                        optionValue="name" optionLabel="name" />
                                    <div id="manual_secondPartyStreet_container" class="mt-2 hidden">
                                        <x-instrument-input id="manual_secondPartyStreet" name="manual_secondPartyStreet" label="" icon="edit-3"
                                            placeholder="Specify Street" />
                                    </div>
                                </div>
                                <div>
                                    <x-instrument-select id="secondPartyDistrict" label="" icon="map-pin"
                                        :options="$districts->merge([(object)['name' => 'Others']])"
                                        placeholder="Select District" value="{{ $record->party_2_district ?? '' }}"
                                        optionValue="name" optionLabel="name" />
                                    <div id="manual_secondPartyDistrict_container" class="mt-2 hidden">
                                        <x-instrument-input id="manual_secondPartyDistrict" name="manual_secondPartyDistrict" label="" icon="edit-3"
                                            placeholder="Specify District" />
                                    </div>
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <x-instrument-select id="secondPartyLga" label="" icon="map"
                                    :options="[]" placeholder="LGA" value="{{ $record->party_2_lga ?? $record->party_2_city ?? '' }}" />
                                <x-instrument-select id="secondPartyState" label="" icon="map-pin"
                                    :options="$states" placeholder="State" value="{{ $record->party_2_state ?? 'Kano' }}" />
                            </div>
                            <x-instrument-input id="secondPartyPhone" label="" icon="phone"
                                placeholder="Phone Number" value="{{ $record->party_2_phone ?? '' }}" />
                        </div>
                    </div>

                    <!-- Instrument Sidebar -->
                    <div class="border-l-4 border-rose-500 pl-4 h-full">
                        <div id="instrument-sidebar" class="h-full">
                            <!-- Sidebar toggles will be inserted here -->
                        </div>
                    </div>
                </div>

                <hr class="my-6 border-gray-200">

                <!-- Row 2b: Property Details -->
                <div class="border-l-4 border-teal-500 pl-4">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-sm font-semibold text-gray-800">Property Details</h3>
                        <div class="flex items-center gap-2">
                            <input type="checkbox" id="surveyInfo" class="checkbox h-3.5 w-3.5 accent-teal-600" 
                                {{ (isset($record->survey_plan_no) || isset($record->lga) || isset($record->district)) ? 'checked' : '' }}>
                            <label for="surveyInfo"
                                class="text-xs font-medium text-teal-700 cursor-pointer">Include Survey
                                Info</label>
                        </div>
                    </div> 

                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-3">
                            <div class="grid grid-cols-3 gap-3">
                                <x-instrument-input id="house_no" label="" icon="home" placeholder="House No"
                                    value="{{ old('house_no', $record->house_no ?? '') }}" />
                                <x-instrument-input id="plotNumber" label="" icon="hash"
                                    placeholder="Plot Number" value="{{ $record->plot_number ?? '' }}" />
                                <div>
                                    <div class="relative">
                                        <select id="tp_no" name="tp_no" class="tp-lookup-select w-full px-3 py-2.5 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-100 focus:border-blue-400 outline-none transition-all text-sm">
                                            <option value="">TP No</option>
                                            @if(!empty($record->tp_no))
                                                <option value="{{ $record->tp_no }}" selected>{{ $record->tp_no }}</option>
                                            @endif
                                        </select>
                                    </div>
                                    <div id="manual_tp_no_container" class="mt-2 hidden">
                                        <x-instrument-input id="manual_tp_no" name="manual_tp_no" label="" icon="edit-3"
                                            placeholder="Enter TP No" value="{{ old('manual_tp_no') }}" />
                                    </div>
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-3 gap-3">
                                <x-instrument-input id="size" label="" icon="maximize" placeholder="Size"
                                    value="{{ $record->size ?? '' }}" />
                                <x-instrument-input id="lpkn_no" label="" icon="file-text" placeholder="LPKN No" 
                                    value="{{ $record->lpkn_no ?? '' }}" />
                            </div>

                            <div>
                                <x-instrument-select id="streetName" label="" icon="map-pin" :options="$streetNames->merge([(object)['name' => 'Others']])"
                                    placeholder="Select Street Name" value="{{ old('streetName', $record->property_street_name ?? '') }}"
                                    optionValue="name" optionLabel="name" />
                                <div id="manual_street_container" class="mt-2 hidden">
                                    <x-instrument-input id="manual_street_name" name="manual_street_name" label="" icon="edit-3"
                                        placeholder="Enter Street Name" value="{{ old('manual_street_name') }}" />
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <x-instrument-select id="desc_district" label="" icon="map-pin" :options="$districts->merge([(object)['name' => 'Others']])"
                                        placeholder="Select District " value="" optionValue="name" optionLabel="name" />
                                    <!-- Manual District Input -->
                                    <div id="manual_district_container" class="mt-2 hidden">
                                        <x-instrument-input id="manual_district" name="manual_district" label="" icon="edit-3" placeholder="Enter District Name" />
                                    </div>
                                </div>
                                <x-instrument-select id="desc_lga" label="" icon="map" :options="$lgas"
                                    placeholder="Select LGA " value="" optionValue="name" optionLabel="name" />
                            </div>

                            <div>
                                <label id="propertyState-label" for="propertyState" class="block text-sm font-medium text-gray-700 mb-1">State</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i data-lucide="map-pin" class="h-4 w-4 text-gray-400"></i>
                                    </div>
                                    <input id="propertyState" name="property_location" type="text"
                                        class="w-full pl-10 px-4 py-2.5 bg-gray-100 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-100 focus:border-blue-400 outline-none transition-all text-sm cursor-not-allowed"
                                        placeholder="State" value="Kano" readonly>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label id="propertyDescription-label" for="propertyDescription"
                                class="block text-sm font-medium text-gray-700 mb-1">Property Description / Address</label>
                            <div class="relative">
                                <div class="absolute top-3 left-3 pointer-events-none">
                                    <i data-lucide="map" class="h-4 w-4 text-gray-400"></i>
                                </div>
                                <textarea id="propertyDescription" name="propertyDescription" rows="5"
                                    class="w-full pl-10 px-4 py-2.5 bg-gray-100 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-100 focus:border-blue-400 outline-none transition-all text-sm cursor-not-allowed"
                                    placeholder="Property Description / Address" readonly>{{ $record->property_description ?? '' }}</textarea>
                            </div>
                        </div>
                    </div>

                    <div id="survey-info-section"
                        class="mt-4 {{ (isset($record->survey_plan_no) || isset($record->lga) || isset($record->district)) ? '' : 'hidden' }} p-3 bg-gray-50 rounded-lg border border-gray-100">
                        <div class="grid grid-cols-3 gap-4">
                            <x-instrument-input id="surveyPlanNo" label="" icon="file-text"
                                placeholder="Survey Plan No" value="{{ $record->survey_plan_no ?? '' }}" />
                            <div>
                                <x-instrument-select id="district" label="" icon="map-pin" :options="$districts->merge([(object)['name' => 'Others']])"
                                    placeholder="District" value="{{ $record->district ?? '' }}" optionValue="name" optionLabel="name" />
                                <div id="manual_survey_district_container" class="mt-2 hidden">
                                    <x-instrument-input id="manual_survey_district" name="manual_survey_district" label="" icon="edit-3" placeholder="Specify District" />
                                </div>
                            </div>
                            <x-instrument-select id="lga" label="" icon="map" :options="$lgas"
                                placeholder="LGA" value="{{ $record->lga ?? '' }}" optionValue="name" optionLabel="name" required/>
                        </div>
                    </div>
                </div>

                <hr class="my-6 border-gray-200">

                <!-- Row 3: Solicitor Details -->
                <div id="solicitor-section" class="border-l-4 border-indigo-500 pl-4">
                    {{-- <div class="flex items-center justify-between mb-4">
                        <h3 class="text-sm font-semibold text-gray-800">Solicitor</h3>
                        <div class="flex items-center gap-2">
                            <input type="checkbox" id="includeSolicitor"
                                class="checkbox h-3.5 w-3.5 accent-indigo-600"
                                {{ isset($record->solicitor_name) ? 'checked' : '' }}>
                            <label for="includeSolicitor"
                                class="text-xs font-medium text-indigo-700 cursor-pointer">Include
                                Solicitor</label>
                        </div>
                    </div> --}}

                    {{-- Hidden checkbox to maintain functionality for JS if needed, or we'll rely on the sidebar one --}}
                    <input type="checkbox" id="includeSolicitor" class="hidden" checked>


                    <div id="solicitor-container" class="{{ isset($record->solicitor_name) ? '' : 'hidden' }} space-y-3">
                        {{-- Fields relocated to sidebar partial --}}
                    </div>
                </div>

                <hr class="my-6 border-gray-200">

                <!-- Row 4: Instrument Main Details -->
                <!-- Row 4: Instrument Main Details -->
                <div id="additional-details-section" class="hidden border-l-4 border-sky-500 pl-4">
                    <h3 class="text-sm font-semibold text-gray-800 mb-4">Additional Details</h3>
                    <div id="instrument-fields" class="space-y-4">
                        @if(isset($record))
                            <!-- Pre-populate some fields if we have a record -->
                             <div class="grid grid-cols-3 gap-4">
                                <x-instrument-input id="rootRegNo" label="Registration Number" icon="file-text" value="{{ $record->root_reg_number ?? '' }}" />
                             </div>
                        @else
                            <div class="text-sm text-gray-500 italic py-2">Select an instrument type to view specific fields</div>
                        @endif
                    </div>
                </div>

            </div>
        </form>

        <!-- Footer -->
        <div
            class="px-6 py-4 bg-gray-50 border-t border-gray-100 flex items-center justify-end gap-3 sticky bottom-0 z-10">
            <button type="button" id="cancel-btn"
                class="px-5 py-2.5 text-gray-700 font-medium bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-200 transition-all shadow-sm">
                {{ (isset($isView) && $isView) ? 'Close' : 'Cancel' }}
            </button>
            @if(!(isset($isView) && $isView))
                <button type="button" id="submit-btn"
                    class="px-6 py-2.5 text-white font-medium bg-blue-600 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-all shadow-md flex items-center gap-2">
                    <i data-lucide="save" class="h-4 w-4"></i>
                    {{ isset($isEdit) && $isEdit ? 'Update Instrument' : 'Capture Instrument' }}
                </button>
            @endif
        </div>
    </div>
</div>

@include('instruments.partials.pra_history_modal')

