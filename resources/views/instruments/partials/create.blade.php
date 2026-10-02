@extends('layouts.app')
@section('page-title')
    {{ __('Instrument Capture') }}
@endsection


@section('content')
    @include('instruments.create.css')
    <!-- Main Content -->
    <div class="flex-1 overflow-auto">
        <!-- Header -->
        @include('admin.header')
        <!-- Dashboard Content -->
        <div class="p-6">

            <div class="h-full p-2">
                <div class="max-w-6xl mx-auto">
                    <div class="card p-6">
                        <h1 class="text-2xl font-bold mb-4">Instrument Types</h1>
                        <p class="text-gray-600 mb-6">Select an instrument type to capture</p>

                        <!-- Instrument Type Selection - Updated Types -->
                        <div class="grid grid-cols-4 gap-3 mb-6">
                            <button
                                class="instrument-type-btn p-2 border rounded-lg text-center bg-yellow-50 border-yellow-200 hover:bg-yellow-100"
                                data-type="deed-of-assignment">
                                <h3 class="font-medium text-yellow-800">Deed of Assignment</h3>
                                <p class="text-xs text-black text-justify flex items-start gap-2"><span
                                        class="inline-flex items-center justify-center w-6 h-6 bg-yellow-600 text-white rounded-full text-xs font-bold flex-shrink-0 mt-0.5">01</span>A
                                    document that legally transfers ownership of an interest in land or property from one
                                    party (assignor) to another (assignee).</p>
                            </button>
                            <button
                                class="instrument-type-btn p-2 border rounded-lg text-center bg-purple-50 border-purple-200 hover:bg-purple-100"
                                data-type="deed-of-mortgage">
                                <h3 class="font-medium text-purple-800">Deed of Mortgage</h3>
                                <p class="text-xs text-black text-justify flex items-start gap-2"><span
                                        class="inline-flex items-center justify-center w-6 h-6 bg-purple-600 text-white rounded-full text-xs font-bold flex-shrink-0 mt-0.5">02</span>A
                                    formal agreement used to secure a loan against landed property, with the lender holding
                                    interest until full repayment.</p>
                            </button>
                            <button
                                class="instrument-type-btn p-2 border rounded-lg text-center bg-red-50 border-red-200 hover:bg-red-100"
                                data-type="tripartite-mortgage">
                                <h3 class="font-medium text-red-800">Tripartite Mortgage</h3>
                                <p class="text-xs text-black text-justify flex items-start gap-2"><span
                                        class="inline-flex items-center justify-center w-6 h-6 bg-red-600 text-white rounded-full text-xs font-bold flex-shrink-0 mt-0.5">03</span>A
                                    three-party agreement involving the borrower, lender, and property owner, typically used
                                    where the borrower is not the titleholder.</p>
                            </button>
                            <button
                                class="instrument-type-btn p-2 border rounded-lg text-center bg-lime-50 border-lime-200 hover:bg-lime-100"
                                data-type="deed-of-surrender-release">
                                <h3 class="font-medium text-lime-800">Deed of Surrender/Release</h3>
                                <p class="text-xs text-black text-justify flex items-start gap-2"><span
                                        class="inline-flex items-center justify-center w-6 h-6 bg-lime-600 text-white rounded-full text-xs font-bold flex-shrink-0 mt-0.5">04</span>A
                                    legal agreement in which a tenant voluntarily returns possession of property to the
                                    landlord, or a document that discharges a party from a previous claim or mortgage on a
                                    property.</p>
                            </button>
                            <button
                                class="instrument-type-btn p-2 border rounded-lg text-center bg-orange-50 border-orange-200 hover:bg-orange-100"
                                data-type="occupancy-permit">
                                <h3 class="font-medium text-orange-800">Occupancy Permit (OP)</h3>
                                <p class="text-xs text-black text-justify flex items-start gap-2"><span
                                        class="inline-flex items-center justify-center w-6 h-6 bg-orange-600 text-white rounded-full text-xs font-bold flex-shrink-0 mt-0.5">05</span>An
                                    official document issued by Kano State Government granting permission to occupy and use
                                    a specific property or land for designated purposes.</p>
                            </button>
                            <button
                                class="instrument-type-btn p-2 border rounded-lg text-center bg-indigo-50 border-indigo-200 hover:bg-indigo-100"
                                data-type="deed-of-lease">
                                <h3 class="font-medium text-indigo-800">Deed of Lease</h3>
                                <p class="text-xs text-black text-justify flex items-start gap-2"><span
                                        class="inline-flex items-center justify-center w-6 h-6 bg-indigo-600 text-white rounded-full text-xs font-bold flex-shrink-0 mt-0.5">06</span>A
                                    contractual document that grants possession and use of land or property to a lessee for
                                    a specified period under agreed terms.</p>
                            </button>
                            <button
                                class="instrument-type-btn p-2 border rounded-lg text-center bg-pink-50 border-pink-200 hover:bg-pink-100"
                                data-type="deed-of-sub-lease">
                                <h3 class="font-medium text-pink-800">Deed of Sub-Lease</h3>
                                <p class="text-xs text-black text-justify flex items-start gap-2"><span
                                        class="inline-flex items-center justify-center w-6 h-6 bg-pink-600 text-white rounded-full text-xs font-bold flex-shrink-0 mt-0.5">07</span>An
                                    agreement where a lessee (not the owner) leases part or all of the leased property to
                                    another party.</p>
                            </button>
                            <button
                                class="instrument-type-btn p-2 border rounded-lg text-center bg-orange-50 border-orange-200 hover:bg-orange-100"
                                data-type="deed-of-sub-division">
                                <h3 class="font-medium text-orange-800">Deed of Sub-Division</h3>
                                <p class="text-xs text-black text-justify flex items-start gap-2"><span
                                        class="inline-flex items-center justify-center w-6 h-6 bg-orange-600 text-white rounded-full text-xs font-bold flex-shrink-0 mt-0.5">08</span>A
                                    legal instrument used to divide a single parcel of land into multiple plots, each with
                                    its own separate title or interest.</p>
                            </button>
                            <button
                                class="instrument-type-btn p-2 border rounded-lg text-center bg-cyan-50 border-cyan-200 hover:bg-cyan-100"
                                data-type="deed-of-merger">
                                <h3 class="font-medium text-cyan-800">Deed of Merger</h3>
                                <p class="text-xs text-black text-justify flex items-start gap-2"><span
                                        class="inline-flex items-center justify-center w-6 h-6 bg-cyan-600 text-white rounded-full text-xs font-bold flex-shrink-0 mt-0.5">09</span>A
                                    document that combines multiple property interests or parcels into a single title or
                                    ownership.</p>
                            </button>
                            <button
                                class="instrument-type-btn p-2 border rounded-lg text-center bg-slate-50 border-slate-200 hover:bg-slate-100"
                                data-type="devolution-order">
                                <h3 class="font-medium text-slate-800">Devolution Order</h3>
                                <p class="text-xs text-black text-justify flex items-start gap-2"><span
                                        class="inline-flex items-center justify-center w-6 h-6 bg-slate-600 text-white rounded-full text-xs font-bold flex-shrink-0 mt-0.5">10</span>A
                                    court-issued legal instrument used to transfer property ownership from a deceased
                                    person's estate to their rightful heirs or beneficiaries.</p>
                            </button>
                            <button
                                class="instrument-type-btn p-2 border rounded-lg text-center bg-emerald-50 border-emerald-200 hover:bg-emerald-100"
                                data-type="deed-of-gift">
                                <h3 class="font-medium text-emerald-800">Deed of Gift</h3>
                                <p class="text-xs text-black text-justify flex items-start gap-2"><span
                                        class="inline-flex items-center justify-center w-6 h-6 bg-emerald-600 text-white rounded-full text-xs font-bold flex-shrink-0 mt-0.5">11</span>A
                                    legal document that transfers ownership of property from a donor (giver) to a donee
                                    (receiver) without monetary consideration, typically as a gift.</p>
                            </button>
                            <button
                                class="instrument-type-btn p-2 border rounded-lg text-center bg-blue-50 border-blue-200 hover:bg-blue-100"
                                data-type="power-of-attorney">
                                <h3 class="font-medium text-blue-800">Power of Attorney</h3>
                                <p class="text-xs text-black text-justify flex items-start gap-2"><span
                                        class="inline-flex items-center justify-center w-6 h-6 bg-blue-600 text-white rounded-full text-xs font-bold flex-shrink-0 mt-0.5">12</span>A
                                    legal document granting authority to a person (the attorney) to act on behalf of another
                                    (the donor) in property-related matters.</p>
                            </button>
                            <button
                                class="instrument-type-btn p-2 border rounded-lg text-center bg-green-50 border-green-200 hover:bg-green-100"
                                data-type="irrevocable-power-of-attorney">
                                <h3 class="font-medium text-green-800">Irrevocable Power of Attorney</h3>
                                <p class="text-xs text-black text-justify flex items-start gap-2"><span
                                        class="inline-flex items-center justify-center w-6 h-6 bg-green-600 text-white rounded-full text-xs font-bold flex-shrink-0 mt-0.5">13</span>A
                                    non-revocable legal instrument that permanently empowers the attorney to act on behalf
                                    of the donor in managing or transferring land/property rights.</p>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Instrument Registration Form Dialog -->
            <div id="registration-dialog"
                class="dialog-backdrop hidden transition-opacity duration-300 ease-in-out z-50 fixed inset-0 flex items-center justify-center bg-black/50">
                <div class="dialog-content bg-white rounded-xl shadow-2xl w-full flex flex-col m-4 animate-scale-in overflow-hidden"
                    style="max-width: 65vw; max-height: 98vh;">
                    <!-- Header -->
                    <div
                        class="px-6 py-4 border-b border-gray-100 flex items-center justify-between bg-white sticky top-0 z-10">
                        <div>
                            <h2 id="dialog-title" class="text-xl font-bold text-gray-800">Capture Instrument</h2>
                            <p class="text-sm text-gray-500 mt-0.5">Enter the details for the new instrument</p>
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
                            <!-- PRA History Alert Placeholder -->
                            <div id="pra-history-alert"
                                class="hidden bg-indigo-50 border-l-4 border-indigo-400 p-4 mb-4 shadow-sm animate-pulse-subtle">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0">
                                            <i data-lucide="history" class="h-5 w-5 text-indigo-400"></i>
                                        </div>
                                        <div class="ml-3">
                                            <p class="text-sm text-indigo-700 font-medium">
                                                This file number has records in PRA history.
                                            </p>
                                        </div>
                                    </div>
                                    <button type="button" id="btn-view-pra-history"
                                        class="ml-auto bg-indigo-100 text-indigo-700 px-3 py-1 rounded-md text-xs font-semibold hover:bg-indigo-200 transition-colors flex items-center gap-1">
                                        <i data-lucide="eye" class="h-3.5 w-3.5"></i>
                                        View History
                                    </button>
                                </div>
                            </div>
                            <input type="hidden" id="storeUrl" value="{{ route('instruments.store') }}" />
                            <input type="hidden" id="generateParticularsUrl"
                                value="{{ route('instruments.generateParticulars') }}" />

                            <!-- Hidden fields for file number storage -->
                            <input type="hidden" id="hidden_mlsFNo" name="mlsFNo" value="">
                            <input type="hidden" id="hidden_kangisFileNo" name="kangisFileNo" value="">
                            <input type="hidden" id="hidden_NewKANGISFileno" name="NewKANGISFileno" value="">
                            {{-- Generic field if needed --}}
                            <input type="hidden" id="hidden_fileno" name="fileno" value="">


                            <!-- File Number, Registration & Instrument Settings -->
                            <div class="grid grid-cols-3 gap-6">
                                <!-- File Number Section -->
                                <div class="border-l-4 border-blue-500 pl-4">
                                    <h3 class="text-sm font-semibold text-gray-800 mb-4">File Number</h3>

                                    <div class="flex items-center gap-2">
                                        <div class="relative flex-1 group">
                                            <div
                                                class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                <i data-lucide="file-text" class="h-4 w-4 text-gray-400"></i>
                                            </div>
                                            <input type="text" id="display_fileno"
                                                class="w-full pl-9 pr-3 py-2 bg-white border border-gray-300 rounded-lg text-gray-700 font-mono text-sm focus:ring-1 focus:ring-blue-500"
                                                placeholder="No file number selected" readonly>
                                        </div>
                                        <button type="button" id="select-file-btn"
                                            class="px-3 py-2 bg-white text-blue-600 font-medium rounded-lg border border-blue-200 hover:bg-blue-50 text-sm whitespace-nowrap">
                                            Select
                                        </button>
                                    </div>
                                    <p class="mt-2 text-xs text-gray-400 truncate" id="file_source_info">Select a file to
                                        associate.</p>
                                </div>

                                <!-- Entry Date Section (Moved here) -->
                                <div class="border-l-4 border-gray-400 pl-4">
                                    <h3 class="text-sm font-semibold text-gray-800 mb-4">Entry Date</h3>
                                    <x-instrument-input id="entryDate" label="" type="date" icon="calendar" />
                                </div>

                                <!-- Instrument Info Display Section -->
                                <div id="instrument-type-info-display"
                                    class="border-l-4 border-gray-200 pl-4 h-full flex flex-col justify-center bg-gray-50 rounded-r-lg pr-2">
                                    <!-- Content injected via JS -->
                                    <p class="text-xs text-gray-400 italic">Select an instrument type to view details.</p>
                                </div>
                            </div>

                            <hr class="my-6 border-gray-200">

                            <!-- Parties & Instrument Details (3 columns) -->
                            <div class="grid grid-cols-3 gap-6">
                                <!-- First Party -->
                                <div class="border-l-4 border-green-500 pl-4">
                                    <h3 id="first-party-title" class="text-sm font-semibold text-gray-800 mb-4">Grantor</h3>

                                    <div class="space-y-3">
                                        <x-instrument-input id="firstPartyName" label="" icon="user" placeholder="Name" />
                                        <input id="firstPartyStreet" name="firstPartyStreet"
                                            class="w-full px-3 py-2 bg-white border border-gray-300 rounded-lg text-sm"
                                            placeholder="Street Address">
                                        <div class="grid grid-cols-2 gap-3">
                                            <input id="firstPartyCity" name="firstPartyCity"
                                                class="w-full px-3 py-2 bg-white border border-gray-300 rounded-lg text-sm"
                                                placeholder="City">
                                            <x-instrument-select id="firstPartyState" label="" icon="map-pin"
                                                :options="$states" placeholder="State" />
                                        </div>
                                        <x-instrument-input id="firstPartyPhone" label="" icon="phone" :phone="true"
                                            placeholder="Phone Number" />
                                    </div>
                                </div>

                                <!-- Second Party -->
                                <div class="border-l-4 border-amber-500 pl-4">
                                    <h3 id="second-party-title" class="text-sm font-semibold text-gray-800 mb-4">Grantee
                                    </h3>

                                    <div class="space-y-3">
                                        <x-instrument-input id="secondPartyName" label="" icon="user-plus"
                                            placeholder="Name" />
                                        <input id="secondPartyStreet" name="secondPartyStreet"
                                            class="w-full px-3 py-2 bg-white border border-gray-300 rounded-lg text-sm"
                                            placeholder="Street Address">
                                        <div class="grid grid-cols-2 gap-3">
                                            <input id="secondPartyCity" name="secondPartyCity"
                                                class="w-full px-3 py-2 bg-white border border-gray-300 rounded-lg text-sm"
                                                placeholder="City">
                                            <x-instrument-select id="secondPartyState" label="" icon="map-pin"
                                                :options="$states" placeholder="State" />
                                        </div>
                                        <x-instrument-input id="secondPartyPhone" label="" icon="phone" :phone="true" :phone-required="true"
                                            placeholder="Phone Number" />
                                    </div>
                                </div>

                                <!-- Instrument Sidebar (Row 2, Col 3) -->
                                <div class="border-l-4 border-rose-500 pl-4 h-full">
                                    <div id="instrument-sidebar" class="h-full">
                                        <!-- Sidebar toggles will be inserted here -->
                                        <!-- Empty by default unless specific instrument has logic -->
                                    </div>
                                </div>
                            </div>

                            <hr class="my-6 border-gray-200">

                            <!-- Row 2b: Property Details -->
                            <div class="border-l-4 border-teal-500 pl-4">
                                <div class="flex items-center justify-between mb-4">
                                    <h3 class="text-sm font-semibold text-gray-800">Property Details</h3>
                                    <div class="flex items-center gap-2">
                                        <input type="checkbox" id="surveyInfo" class="checkbox h-3.5 w-3.5 accent-teal-600">
                                        <label for="surveyInfo"
                                            class="text-xs font-medium text-teal-700 cursor-pointer">Include Survey
                                            Info</label>
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <x-instrument-textarea id="propertyDescription" label="" icon="map"
                                        placeholder="Property Description / Address" rows="2" />

                                    <div class="space-y-3">
                                        <div class="grid grid-cols-2 gap-3">
                                            <x-instrument-input id="plotNumber" label="" icon="hash"
                                                placeholder="Plot Number" />
                                            <x-instrument-input id="size" label="" icon="maximize" placeholder="Size" />
                                        </div>
                                        <x-instrument-input id="propertyLocation" label="" icon="map-pin"
                                            placeholder="Location / Area" />
                                    </div>
                                </div>

                                <div id="survey-info-section"
                                    class="mt-4 hidden p-3 bg-gray-50 rounded-lg border border-gray-100">
                                    <div class="grid grid-cols-3 gap-4">
                                        <x-instrument-input id="surveyPlanNo" label="" icon="file-text"
                                            placeholder="Survey Plan No" />
                                        <x-instrument-input id="lga" label="" icon="map" placeholder="LGA" />
                                        <x-instrument-input id="district" label="" icon="map" placeholder="District" />
                                    </div>
                                </div>
                            </div>

                            <hr class="my-6 border-gray-200">

                            <!-- Row 3: Solicitor Details -->
                            <div id="solicitor-section" class="border-l-4 border-indigo-500 pl-4">
                                <div class="flex items-center justify-between mb-4">
                                    <h3 class="text-sm font-semibold text-gray-800">Solicitor</h3>
                                    <div class="flex items-center gap-2">
                                        <input type="checkbox" id="includeSolicitor"
                                            class="checkbox h-3.5 w-3.5 accent-indigo-600">
                                        <label for="includeSolicitor"
                                            class="text-xs font-medium text-indigo-700 cursor-pointer">Include
                                            Solicitor</label>
                                    </div>
                                </div>

                                <div id="solicitor-container" class="hidden space-y-3">
                                    <div class="grid grid-cols-2 gap-4">
                                        <x-instrument-input id="solicitorName" label="" icon="briefcase"
                                            placeholder="Name" />
                                        <input id="solicitorCity" name="solicitorCity"
                                            class="w-full px-3 py-2 bg-white border border-gray-300 rounded-lg text-sm"
                                            placeholder="City">
                                    </div>
                                    <div class="grid grid-cols-2 gap-4">
                                        <x-instrument-select id="solicitorState" label="" icon="map-pin" :options="$states"
                                            placeholder="State" />
                                        <x-instrument-textarea id="solicitorAddress" label="" icon="map-pin"
                                            placeholder="Auto-generated from city and state" rows="1" readonly />
                                    </div>
                                </div>
                            </div>

                            <hr class="my-6 border-gray-200">

                            <!-- Row 4: Instrument Main Details (Full Width) -->
                            <div id="additional-details-section" class="hidden border-l-4 border-sky-500 pl-4">
                                <h3 class="text-sm font-semibold text-gray-800 mb-4">Additional Details</h3>
                                <div id="instrument-fields" class="space-y-4">
                                    <!-- Dynamic fields will be inserted here -->
                                    <div class="text-sm text-gray-500 italic py-2">Select an instrument type
                                        to view specific fields
                                    </div>
                                </div>
                            </div>

                        </div>


                    </form>

                    <!-- Footer -->
                    <div
                        class="px-6 py-4 bg-gray-50 border-t border-gray-100 flex items-center justify-end gap-3 sticky bottom-0 z-10">
                        <button type="button" id="cancel-btn"
                            class="px-5 py-2.5 text-gray-700 font-medium bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-200 transition-all shadow-sm">
                            Cancel
                        </button>
                        <button type="button" id="submit-btn"
                            class="px-6 py-2.5 text-white font-medium bg-blue-600 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-all shadow-md flex items-center gap-2">
                            <i data-lucide="save" class="h-4 w-4"></i>
                            Capture Instrument
                        </button>
                    </div>
                </div>
            </div>

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


        {{-- Scripts --}}
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <script src="{{ asset('js/global-fileno-modal.js') }}"></script>
        <script>
            window.InstrumentCaptureConfig = window.InstrumentCaptureConfig || {
                csrfToken: "{{ csrf_token() }}",
                urls: {
                    generateRds: "{{ url('instrument_registration/generate-rds') }}",
                    viewRds: "{{ url('instrument_registration/view-rds') }}",
                    printRds: "{{ url('instrument_registration/print-rds') }}",
                    generateCor: "{{ url('coroi/generate') }}",
                    viewCor: "{{ route('coroi.index') }}"
                }
            };
        </script>
        <script src="{{ asset('js/instruments-capture.js') }}?v={{ time() }}"></script>

        <!-- Footer -->
        @include('admin.footer')
    </div>
    <!-- Duplicate Check Modal -->
    <div id="duplicate-modal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full"
        style="z-index: 1000030;">
        <div class="relative top-20 mx-auto p-5 border max-w-xl w-full shadow-lg rounded-md bg-white">
            <button type="button" id="btn-close-duplicate"
                class="absolute top-3 right-3 p-2 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors"
                aria-label="Close duplicate dialog">
                <i data-lucide="x" class="h-5 w-5"></i>
            </button>
            <div class="mt-3 text-center">
                <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-yellow-100">
                    <i data-lucide="alert-triangle" class="h-6 w-6 text-yellow-600"></i>
                </div>
                <h3 class="text-lg leading-6 font-medium text-gray-900 mt-2">Duplicate Record Found</h3>
                <div class="mt-2 px-7 py-3">
                    <p class="text-sm text-gray-500">
                        An instrument record already exists for this file number.
                    </p>
                    <div id="duplicate-details" class="text-left mt-4 text-sm bg-gray-50 p-3 rounded text-gray-700">
                        <!-- Details injected via JS -->
                    </div>
                </div>
                <div class="items-center px-4 py-3">
                    <button type="button" id="btn-update-existing"
                        class="px-4 py-2 bg-blue-500 text-white text-base font-medium rounded-md w-full shadow-sm hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-300">
                        Update Existing Record
                    </button>
                    <button type="button" id="btn-create-new"
                        class="mt-3 px-4 py-2 bg-gray-100 text-gray-700 text-base font-medium rounded-md w-full shadow-sm hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-gray-300">
                        Create New Record
                    </button>
                    <button type="button" id="btn-close-duplicate-footer"
                        class="mt-3 px-4 py-2 bg-white text-gray-600 text-base font-medium rounded-md w-full border border-gray-200 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-300">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>
    {{-- PRA History Modal (shared partial) --}}
    @include('instruments.partials.pra_history_modal')
@endsection
