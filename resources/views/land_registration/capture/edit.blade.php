@extends('layouts.app')

@section('page-title')
    {{ __('Edit Deed of Purchase') }}
@endsection

@section('content')
    {{--
        Edit screen. Same shape as the Deeds edit page: the shared capture form
        opens over it and is populated from the record, so there is one form to
        maintain rather than a read path and a write path that can disagree.
    --}}
    @include('instruments.create.css')

    <div class="flex-1 overflow-auto">
        @include('admin.header')

        <div class="p-6">
            <div class="max-w-7xl mx-auto">
                <div class="flex items-center justify-between mb-8">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-800">Edit Deed of Purchase</h1>
                        <p class="text-gray-500 mt-1">
                            {{ $record->registration_number ? 'Registered as ' . $record->registration_number . ' — ' : '' }}
                            {{ $record->mlsFNo ?: ($record->kangisFileNo ?: ($record->NewKANGISFileno ?: $record->temp_fileno)) }}
                        </p>
                    </div>
                    <a href="{{ route('land-registration.capture.index') }}"
                        class="flex items-center gap-2 px-4 py-2 bg-white border border-gray-200 rounded-lg text-sm font-medium text-gray-600 hover:bg-gray-50 transition-all">
                        <i data-lucide="arrow-left" class="w-4 h-4"></i>
                        Back to List
                    </a>
                </div>

                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-8 text-center">
                    <div class="max-w-md mx-auto">
                        <div class="w-16 h-16 bg-orange-50 text-orange-600 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i data-lucide="edit-3" class="w-8 h-8"></i>
                        </div>
                        <h2 class="text-xl font-bold text-gray-800 mb-2">Editing Record #{{ $record->id }}</h2>
                        <p class="text-gray-500 mb-6">The edit form opens automatically.</p>

                        <button type="button" onclick="openEditDialog()"
                            class="px-6 py-3 bg-orange-600 text-white font-semibold rounded-lg hover:bg-orange-700 transition-all shadow-md inline-flex items-center gap-2">
                            <i data-lucide="external-link" class="w-5 h-5"></i>
                            Open Edit Form
                        </button>
                    </div>
                </div>

                @php $isEdit = true; @endphp
                @include('instruments.partials.register_modal')

                @include('components.global-fileno-modal')

                <template id="template-{{ config('land_registration.type_key') }}">
                    @include('land_registration.partials.types.deed-of-purchase')
                </template>
                <template id="template-{{ config('land_registration.type_key') }}-sidebar">
                    @include('instruments.partials.types.sidebars.solicitor_toggle')
                </template>
            </div>
        </div>
    </div>

    {{-- Scripts --}}
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="{{ asset('js/global-fileno-modal.js') }}"></script>
    <script>
        window.InstrumentCaptureConfig = {
            csrfToken: "{{ csrf_token() }}",

            // The per-type fields (Amount, Receipt No) live in the "Additional
            // Details" section, which the Deeds screens keep hidden for every
            // instrument. This registry wants them.
            showAdditionalDetails: true,
            additionalDetailsTitle: "Payment",

            urls: {
                captureBase: "{{ url('land-registration/instruments') }}",
                store: "{{ route('land-registration.capture.store') }}",
                checkDuplicate: "{{ route('land-registration.capture.check-duplicate') }}",
                nextRegistrationParticulars: "{{ route('land-registration.capture.next-registration-particulars') }}",
                tpLookupSearch: "{{ route('instruments.tpLookups.search') }}",
                tpLookupStore: "{{ route('instruments.tpLookups.store') }}",
                generateRds: "{{ url('land-registration/generate-rds') }}",
                viewRds: "{{ url('land-registration/view-rds') }}",
                printRds: "{{ url('land-registration/print-rds') }}",
                generateCor: "{{ url('land-registration/cor/generate') }}",
                viewCor: "{{ route('land-registration.cor.index') }}"
            }
        };
    </script>
    <script src="{{ asset('js/instruments-capture.js') }}?v={{ @filemtime(public_path('js/instruments-capture.js')) }}"></script>

    <script>
        window.editRecordData = @json($record);

        function openEditDialog() {
            if (typeof openRegistrationDialog !== 'function') return;

            openRegistrationDialog("{{ config('land_registration.type_key') }}");

            const titleEl = document.getElementById('dialog-title');
            const subtitleEl = document.getElementById('dialog-subtitle');
            const submitBtn = document.getElementById('submit-btn');

            if (titleEl) titleEl.innerText = 'Edit Deed of Purchase';
            if (subtitleEl) subtitleEl.innerText = 'Update registration details';
            if (submitBtn) submitBtn.innerHTML = '<i data-lucide="save" class="h-4 w-4"></i> Update Instrument';

            // populateForm sets the PUT method and the update URL from the record id.
            if (typeof populateForm === 'function' && window.editRecordData) {
                setTimeout(() => populateForm(window.editRecordData), 100);
            }

            if (typeof lucide !== 'undefined') lucide.createIcons();
        }

        document.addEventListener('DOMContentLoaded', function () {
            setTimeout(openEditDialog, 500);

            const backToList = () => window.location.href = "{{ route('land-registration.capture.index') }}";

            // Cancel and close both leave the record alone and return to the list,
            // rather than dropping the officer on an empty capture form.
            const cancelBtn = document.getElementById('cancel-btn');
            if (cancelBtn) {
                cancelBtn.replaceWith(cancelBtn.cloneNode(true));
                document.getElementById('cancel-btn').addEventListener('click', backToList);
            }

            const closeBtn = document.getElementById('dialog-close-btn');
            if (closeBtn) closeBtn.addEventListener('click', backToList);
        });
    </script>
@endsection
