@extends('layouts.app')
@section('page-title')
    {{ __('Instrument Capture') }}
@endsection


@section('content')
    @include('instruments.create.css')
    @include('propertycard.css.style')
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
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
                                <h3 class="font-medium text-blue-800 text-sm leading-tight">Deed of Assignment/Gift</h3>
                                <p class="text-xs text-black text-justify flex items-start gap-2"><span
                                        class="inline-flex items-center justify-center w-6 h-6 bg-yellow-600 text-white rounded-full text-xs font-bold flex-shrink-0 mt-0.5">01</span>A
                                    document that legally transfers ownership of an interest in land or property from one
                                    party (assignor) to another (assignee).</p>
                            </button>
                            <button
                                class="instrument-type-btn p-2 border rounded-lg text-center bg-purple-50 border-purple-200 hover:bg-purple-100"
                                data-type="deed-of-mortgage">
                                <h3 class="font-medium text-purple-800 text-xs leading-tight">Deed of Mortgage/Tripartite
                                    Mortgage</h3>
                                <p class="text-xs text-black text-justify flex items-start gap-2"><span
                                        class="inline-flex items-center justify-center w-6 h-6 bg-purple-600 text-white rounded-full text-xs font-bold flex-shrink-0 mt-0.5">02</span>A
                                    formal agreement used to secure a loan against landed property, with the lender holding
                                    interest until full repayment.</p>
                            </button>
                            <!-- <button
                                                                                            class="instrument-type-btn p-2 border rounded-lg text-center bg-red-50 border-red-200 hover:bg-red-100"
                                                                                            data-type="tripartite-mortgage">
                                                                                            <h3 class="font-medium text-red-800">Tripartite Mortgage</h3>
                                                                                            <p class="text-xs text-black text-justify flex items-start gap-2"><span
                                                                                                    class="inline-flex items-center justify-center w-6 h-6 bg-red-600 text-white rounded-full text-xs font-bold flex-shrink-0 mt-0.5">03</span>A
                                                                                                three-party agreement involving the borrower, lender, and property owner, typically used
                                                                                                where the borrower is not the titleholder.</p>
                                                                                        </button> -->
                            <button
                                class="instrument-type-btn p-2 border rounded-lg text-center bg-lime-50 border-lime-200 hover:bg-lime-100"
                                data-type="deed-of-surrender-release">
                                <h3 class="font-medium text-lime-800 text-[11px] leading-tight break-words">Deed of
                                    Surrender and Release/Power of Attorney/Irrevocable Power of Attorney</h3>
                                <p class="text-xs text-black text-justify flex items-start gap-2"><span
                                        class="inline-flex items-center justify-center w-6 h-6 bg-lime-600 text-white rounded-full text-xs font-bold flex-shrink-0 mt-0.5">03</span>A
                                    legal agreement in which a tenant voluntarily returns possession of property to the
                                    landlord, or a document that discharges a party from a previous claim or mortgage on a
                                    property.</p>
                            </button>
                            <button
                                class="instrument-type-btn p-2 border rounded-lg text-center bg-orange-50 border-orange-200 hover:bg-orange-100"
                                data-type="occupancy-permit">
                                <h3 class="font-medium text-orange-800">Occupancy Permit (OP)</h3>
                                <p class="text-xs text-black text-justify flex items-start gap-2"><span
                                        class="inline-flex items-center justify-center w-6 h-6 bg-orange-600 text-white rounded-full text-xs font-bold flex-shrink-0 mt-0.5">04</span>An
                                    official document issued by Kano State Government granting permission to occupy and use
                                    a specific property or land for designated purposes.</p>
                            </button>
                            <button
                                class="instrument-type-btn p-2 border rounded-lg text-center bg-teal-50 border-teal-200 hover:bg-teal-100"
                                data-type="certificate-of-occupancy">
                                <h3 class="font-medium text-teal-800">Certificate of Occupancy</h3>
                                <p class="text-xs text-black text-justify flex items-start gap-2"><span
                                        class="inline-flex items-center justify-center w-6 h-6 bg-teal-600 text-white rounded-full text-xs font-bold flex-shrink-0 mt-0.5">05</span>A
                                    legal document issued by the government that proves ownership of land for a specified
                                    period (usually 99 years)
                                    in accordance with the Land Use Act.</p>
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
                            <!-- <button
                                                                    class="instrument-type-btn p-2 border rounded-lg text-center bg-orange-50 border-orange-200 hover:bg-orange-100"
                                                                    data-type="deed-of-sub-division">
                                                                    <h3 class="font-medium text-orange-800">Deed of Sub-Division</h3>
                                                                    <p class="text-xs text-black text-justify flex items-start gap-2"><span
                                                                            class="inline-flex items-center justify-center w-6 h-6 bg-orange-600 text-white rounded-full text-xs font-bold flex-shrink-0 mt-0.5">07</span>A
                                                                        legal instrument used to divide a single parcel of land into multiple plots, each with
                                                                        its own separate title or interest.</p>
                                                                </button>
                                                                <button
                                                                    class="instrument-type-btn p-2 border rounded-lg text-center bg-cyan-50 border-cyan-200 hover:bg-cyan-100"
                                                                    data-type="deed-of-merger">
                                                                    <h3 class="font-medium text-cyan-800">Deed of Merger</h3>
                                                                    <p class="text-xs text-black text-justify flex items-start gap-2"><span
                                                                            class="inline-flex items-center justify-center w-6 h-6 bg-cyan-600 text-white rounded-full text-xs font-bold flex-shrink-0 mt-0.5">08</span>A
                                                                        document that combines multiple property interests or parcels into a single title or
                                                                        ownership.</p>
                                                                </button> -->
                            <button
                                class="instrument-type-btn p-2 border rounded-lg text-center bg-slate-50 border-slate-200 hover:bg-slate-100"
                                data-type="devolution-order">
                                <h3 class="font-medium text-slate-800">Devolution Order</h3>
                                <p class="text-xs text-black text-justify flex items-start gap-2"><span
                                        class="inline-flex items-center justify-center w-6 h-6 bg-slate-600 text-white rounded-full text-xs font-bold flex-shrink-0 mt-0.5">08</span>A
                                    court-issued legal instrument used to transfer property ownership from a deceased
                                    person's estate to their rightful heirs or beneficiaries.</p>
                            </button>

                            <!-- <button
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
                                                                                                non-revocable legal instrument that permanently empowers the attorney to act on behalf
                                                                                                of the donor in managing or transferring land/property rights.</p>
                                                                                        </button> -->
                        </div>
                    </div>
                </div>
            </div>

            <!-- Instrument Registration Form Dialog-->
            @include('instruments.partials.register_modal', ['showCaptureBanner' => true])

            @include('components.global-fileno-modal')

            <!-- Templates for Instrument Types -->
            <template id="template-power-of-attorney">
                @include('instruments.partials.types.simple-duration')
            </template>
            <template id="template-irrevocable-power-of-attorney">
                @include('instruments.partials.types.simple-duration')
            </template>
            <template id="template-occupancy-permit">
                @include('instruments.partials.types.occupancy-permit')
            </template>
            <template id="template-certificate-of-occupancy">
                @include('instruments.partials.types.certificate-of-occupancy')
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
                @include('instruments.partials.types.sidebars.deed-of-mortgage')
            </template>
            <template id="template-tripartite-mortgage-sidebar">
                @include('instruments.partials.types.sidebars.tripartite-mortgage')
            </template>
            <template id="template-deed-of-surrender-release-sidebar">
                @include('instruments.partials.types.sidebars.deed-of-surrender-release')
            </template>
            <template id="template-deed-of-assignment-sidebar">
                @include('instruments.partials.types.sidebars.deed-of-assignment')
            </template>
            <template id="template-deed-of-gift-sidebar">
                @include('instruments.partials.types.sidebars.deed-of-assignment')
            </template>
            <template id="template-certificate-of-occupancy-sidebar">
                @include('instruments.partials.types.sidebars.deed-of-assignment')
            </template>
            <template id="template-power-of-attorney-sidebar">
                @include('instruments.partials.types.sidebars.solicitor_toggle')
            </template>
            <template id="template-irrevocable-power-of-attorney-sidebar">
                @include('instruments.partials.types.sidebars.solicitor_toggle')
            </template>
        </div>


        {{-- Scripts --}}
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
        <script src="{{ asset('js/global-fileno-modal.js') }}"></script>
        <script>
            window.InstrumentCaptureConfig = {
                csrfToken: "{{ csrf_token() }}",
                urls: {
                    tpLookupSearch: "{{ route('instruments.tpLookups.search') }}",
                    tpLookupStore: "{{ route('instruments.tpLookups.store') }}",
                    generateRds: "{{ url('instrument_registration/generate-rds') }}",
                    viewRds: "{{ url('instrument_registration/view-rds') }}",
                    printRds: "{{ url('instrument_registration/print-rds') }}",
                    generateCor: "{{ url('coroi/generate') }}",
                    viewCor: "{{ route('coroi.index') }}"
                }
            };
        </script>
        <script src="{{ asset('js/instruments-capture.js') }}?v={{ time() }}"></script>
    <!-- Paints the Deeds workflow strip beside the file number. -->
    <script src="{{ asset('js/deeds-pipeline.js') }}?v={{ @filemtime(public_path('js/deeds-pipeline.js')) }}"></script>
        <script src="{{ asset('js/pra/helpers.js') }}"></script>
        <script src="{{ asset('js/pra/state.js') }}"></script>
        <script src="{{ asset('js/pra/modal.js') }}"></script>
        <script src="{{ asset('js/pra/form-controller.js') }}?v={{ @filemtime(public_path('js/pra/form-controller.js')) }}"></script>

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
                            class="hidden px-4 py-2 bg-blue-500 text-white text-base font-medium rounded-md w-full shadow-sm hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-300">
                            Update Existing Record
                        </button>
                        <button type="button" id="btn-create-new"
                            class="hidden mt-3 px-4 py-2 bg-gray-100 text-gray-700 text-base font-medium rounded-md w-full shadow-sm hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-gray-300">
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
        @include('propertycard.partials.add_property_record')

        <!-- Footer -->
        @include('admin.footer')
    </div>
@endsection
