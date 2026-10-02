@extends('layouts.app')
@section('page-title')
    {{ __('Land Registration — Capture') }}
@endsection

@section('content')
    {{--
        The Land Registry's capture screen.

        One card, because this registry registers one instrument. The form
        itself, its templates and the capture JS are the SAME ones the Deeds
        screen uses — sharing them is what keeps both registries capturing
        through identical logic. What differs is where it posts:
        InstrumentCaptureConfig.urls below points the shared JS at the
        land-registration endpoints, which are the consent-free ones.
    --}}
    @include('instruments.create.css')
    @include('propertycard.css.style')
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

    <div class="flex-1 overflow-auto">
        @include('admin.header')

        <div class="p-6">
            <div class="h-full p-2">
                <div class="max-w-6xl mx-auto">
                    <div class="card p-6">
                        <div class="flex items-start justify-between mb-4 gap-4">
                            <div>
                                <h1 class="text-2xl font-bold">Land Registration</h1>
                                <p class="text-gray-600">Select an instrument to capture</p>
                            </div>
                            <a href="{{ route('land-registration.capture.index') }}"
                                class="inline-flex items-center gap-2 px-3 py-2 text-sm border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors whitespace-nowrap">
                                <i data-lucide="list" class="h-4 w-4 text-gray-500"></i>
                                <span>View Register</span>
                            </a>
                        </div>

                        {{-- The short description, not the long one: this card is
                             a quarter of the row wide, and the full sentence was
                             what crowded it out before. --}}
                        <div class="grid grid-cols-4 gap-3 mb-6">
                            <button
                                class="instrument-type-btn flex items-start gap-3 p-4 border rounded-lg text-left bg-orange-50 border-orange-200 hover:bg-orange-100 transition-colors"
                                data-type="{{ config('land_registration.type_key') }}">
                                <span
                                    class="inline-flex items-center justify-center w-7 h-7 bg-orange-600 text-white rounded-full text-xs font-bold flex-shrink-0">01</span>
                                <span class="flex flex-col gap-0.5">
                                    <span class="font-medium text-orange-800 text-sm leading-snug">
                                        {{ config('land_registration.card.title') }}
                                    </span>
                                    <span class="text-xs text-gray-600 leading-snug">
                                        {{-- The card's own sentence, not the short
                                             lookup one: this has room to breathe,
                                             the table cell in the types modal does
                                             not. --}}
                                        {{ config('land_registration.card.description') }}
                                    </span>
                                </span>
                            </button>
                        </div>

                        @php
                            // Only read to decide whether registration is possible
                            // at all; the number itself is not shown here.
                            $nextNumber = app(\App\Services\InstrumentRegistrationService::class)
                                ->peekRegistrationNumber(config('land_registration.instrument_type'));
                        @endphp
                        @unless($nextNumber['configured'] ?? false)
                            <div class="flex items-center gap-2 text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-lg p-3">
                                <i data-lucide="alert-triangle" class="h-4 w-4"></i>
                                <span>
                                    The numbering vault for
                                    <strong>{{ config('land_registration.instrument_type') }}</strong>
                                    is not configured, so nothing can be registered yet.
                                    Run <code>php artisan land-registration:install</code>, or add it under
                                    Manage Instrument Types.
                                </span>
                            </div>
                        @endunless
                    </div>
                </div>
            </div>

            {{-- The shared capture form. Reused verbatim: the capture JS binds to
                 the element ids inside it, so a copy would have to be kept in
                 step with that JS forever. --}}
            @include('instruments.partials.register_modal', ['showCaptureBanner' => true])

            @include('components.global-fileno-modal')

            <template id="template-{{ config('land_registration.type_key') }}">
                @include('land_registration.partials.types.deed-of-purchase')
            </template>
            <template id="template-{{ config('land_registration.type_key') }}-sidebar">
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

                // The per-type fields (Amount, Receipt No) live in the
                // "Additional Details" section, which the Deeds screens keep
                // hidden for every instrument. This registry wants them.
                showAdditionalDetails: true,
                additionalDetailsTitle: "Payment",

                urls: {
                    // Land Registration endpoints. captureBase/store/checkDuplicate
                    // are what make this page consent-free: the land
                    // check-duplicate never returns a consent, so the shared JS's
                    // consent picker and auto-fill have nothing to act on.
                    captureBase: "{{ url('land-registration/instruments') }}",
                    store: "{{ route('land-registration.capture.store') }}",
                    checkDuplicate: "{{ route('land-registration.capture.check-duplicate') }}",
                    nextRegistrationParticulars: "{{ route('land-registration.capture.next-registration-particulars') }}",

                    // Registry-agnostic reference lookups — shared with Deeds on
                    // purpose, there is nothing land-specific about a TP number.
                    tpLookupSearch: "{{ route('instruments.tpLookups.search') }}",
                    tpLookupStore: "{{ route('instruments.tpLookups.store') }}",

                    // Documents: the Land Registry's own RDS and CoR.
                    generateRds: "{{ url('land-registration/generate-rds') }}",
                    viewRds: "{{ url('land-registration/view-rds') }}",
                    printRds: "{{ url('land-registration/print-rds') }}",
                    generateCor: "{{ url('land-registration/cor/generate') }}",
                    viewCor: "{{ route('land-registration.cor.index') }}"
                }
            };
        </script>
        <script src="{{ asset('js/instruments-capture.js') }}?v={{ @filemtime(public_path('js/instruments-capture.js')) }}"></script>
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

        @include('instruments.partials.pra_history_modal')
        @include('propertycard.partials.add_property_record')

        @include('admin.footer')
    </div>
@endsection
