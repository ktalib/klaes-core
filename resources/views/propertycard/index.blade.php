@extends('layouts.app')
@section('page-title')
    {{ __('Property Record Assistant') }}
@endsection
@section('content')
@include('propertycard.css.style') 
    <!-- Main Content --> 
    <div class="flex-1 overflow-auto">
        <!-- Header -->
        @include('admin.header')
        <!-- Dashboard Content -->
        <div class="p-6"> 
            @php
                $currentUser = auth()->user();
                $assignRoles = $currentUser && is_array($currentUser->assign_role)
                    ? $currentUser->assign_role
                    : array_filter(array_map('trim', explode(',', (string) ($currentUser->assign_role ?? ''))));
                $isSupperAdminRole = in_array('Supper Admin', $assignRoles, true);
            @endphp
            <div class="container mx-auto py-6 space-y-6">
                @if(session('success'))
                    <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">
                        {{ session('success') }}
                    </div>
                @endif

                @if(session('warning'))
                    <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                        {{ session('warning') }}
                    </div>
                @endif

                @if(session('error'))
                    <div class="rounded-lg border border-rose-200 bg-rose-50 p-4 text-sm text-rose-900">
                        {{ session('error') }}
                    </div>
                @endif

                @if($errors->any())
                    <div class="rounded-lg border border-rose-200 bg-rose-50 p-4 text-sm text-rose-900">
                        <div class="font-semibold mb-1">Import validation failed</div>
                        <ul class="list-disc pl-5 space-y-1">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <!-- Page Header -->
                <!-- Statistics Dashboard -->
                @if(isset($stats))
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                    <div class="bg-white rounded-lg shadow-sm p-6 border-l-4 border-blue-500">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-blue-100 text-blue-500 mr-4">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500 font-medium">Daily Records</p>
                                <h3 class="text-2xl font-bold text-gray-800">{{ number_format($stats['daily']) }}</h3>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-lg shadow-sm p-6 border-l-4 border-green-500">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-green-100 text-green-500 mr-4">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500 font-medium">Monthly Records</p>
                                <h3 class="text-2xl font-bold text-gray-800">{{ number_format($stats['monthly']) }}</h3>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-lg shadow-sm p-6 border-l-4 border-purple-500">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-purple-100 text-purple-500 mr-4">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                </svg>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500 font-medium">Total Records</p>
                                <h3 class="text-2xl font-bold text-gray-800">{{ number_format($stats['total']) }}</h3>
                            </div>
                        </div>
                    </div>
                </div>
                @endif
        
                <div class="flex items-center justify-end gap-3 mb-4">
                    <a href="{{ route('property-records.op-import-template') }}"
                       class="inline-flex items-center rounded border border-gray-200 px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50"
                       title="Download CSV template for OP Import">
                        <i class="fas fa-download mr-2"></i>
                        OP Template
                    </a>
                    <form id="op-import-form" action="{{ route('property-records.import-op') }}" method="POST" enctype="multipart/form-data" class="inline-flex">
                        @csrf
                        <input id="op-import-type" type="hidden" name="op_type" value="">
                        <input id="op-import-file" type="file" name="op_file" class="hidden" accept=".csv,.txt"
                               >
                        <button id="op-import-trigger" type="button"
                                onclick="beginOpImportSelection();"
                                class="inline-flex items-center rounded border border-emerald-200 px-3 py-2 text-sm font-medium text-emerald-700 hover:bg-emerald-50">
                            <i class="fas fa-file-import mr-2"></i>
                            OP Import
                        </button>
                    </form>
                    @if($isSupperAdminRole)
                        <a href="{{ route('pra-pic-audit.index') }}" class="inline-flex items-center rounded border border-blue-200 px-3 py-2 text-sm font-medium text-blue-700 hover:bg-blue-50">
                            <i class="fas fa-chart-bar mr-2"></i>
                            PRA/PIC Audit Dashboard
                        </a>
                    @endif
                    <a href="{{ route('propertycard.ai') }}" class="flex items-center text-gray-600">
                        <i class="fas fa-robot mr-2"></i> AI Assistant
                    </a>
                </div>

                @if(session('op_import_summary'))
                    @php($opImportSummary = session('op_import_summary'))
                    <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">
                        <div class="font-semibold mb-1">OP Import Summary</div>
                        <div>Total Rows: {{ $opImportSummary['total_rows'] ?? 0 }}</div>
                        <div>Imported: {{ $opImportSummary['imported'] ?? 0 }}</div>
                        <div>Skipped: {{ $opImportSummary['skipped'] ?? 0 }}</div>
                        @if(!empty($opImportSummary['errors']))
                            <div class="mt-2 font-medium">Notes:</div>
                            <ul class="list-disc pl-5 mt-1 space-y-1 max-h-40 overflow-y-auto">
                                @foreach(array_slice($opImportSummary['errors'], 0, 20) as $errorItem)
                                    <li>{{ $errorItem }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @endif

                <!-- Manual Property Details Content -->
                <div id="manual-assistant">
                    @include('propertycard.partials.property_details', [
                        'showIndexCardButton' => false
                    ])
                </div>

                <!-- AI Property Details Content (placeholder kept for future use) -->
                <div id="ai-assistant" style="display: none;"></div>
           
            </div>
        
            <!-- Property Modal Dialogs -->
            @include('propertycard.partials.add_property_record')
            @include('propertycard.partials.edit_property_record')
            @include('propertycard.partials.view_property_record')
        </div>
        <!-- Footer -->
        @include('admin.footer')
    </div>
    
    @include('components.global-fileno-modal')
    <script src="{{ asset('js/global-fileno-modal.js') }}"></script>
    <script>
        (function () {
            const fileInput = document.getElementById('op-import-file');
            const opTypeInput = document.getElementById('op-import-type');
            const triggerBtn = document.getElementById('op-import-trigger');
            const importForm = document.getElementById('op-import-form');

            if (!fileInput || !triggerBtn || !importForm || !opTypeInput) {
                return;
            }

            window.beginOpImportSelection = async function () {
                const optionsHtml = [
                    '<option value="">Select OP Type</option>',
                    '<option value="OP Direct Allocation">OP Direct Allocation</option>',
                    '<option value="OP Resettlement">OP Resettlement</option>'
                ].join('');

                const showTypeError = () => {
                    if (window.Swal) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'OP Type Required',
                            text: 'Please select OP Type before choosing the import file.'
                        });
                    } else {
                        alert('Please select OP Type before choosing the import file.');
                    }
                };

                let selectedType = '';

                if (window.Swal) {
                    const result = await Swal.fire({
                        title: 'Select OP Type',
                        html: `<select id="op-type-picker" class="swal2-select" style="display:block; width:100%; margin:0 auto;">${optionsHtml}</select>`,
                        showCancelButton: true,
                        confirmButtonText: 'Continue',
                        cancelButtonText: 'Cancel',
                        focusConfirm: false,
                        preConfirm: () => {
                            const picker = document.getElementById('op-type-picker');
                            const value = picker ? picker.value : '';
                            if (!value) {
                                Swal.showValidationMessage('Please select OP Type.');
                                return false;
                            }
                            return value;
                        }
                    });

                    if (!result.isConfirmed || !result.value) {
                        return;
                    }

                    selectedType = String(result.value).trim();
                } else {
                    selectedType = prompt('Enter OP Type: OP Direct Allocation / OP Resettlement', 'OP Direct Allocation') || '';
                    selectedType = selectedType.trim();

                    const allowed = ['OP Direct Allocation', 'OP Resettlement'];
                    if (!selectedType || allowed.indexOf(selectedType) === -1) {
                        showTypeError();
                        return;
                    }
                }

                if (!selectedType) {
                    showTypeError();
                    return;
                }

                opTypeInput.value = selectedType;
                fileInput.click();
            };

            fileInput.addEventListener('change', function () {
                if (!fileInput.files || fileInput.files.length === 0) {
                    return;
                }

                if (!opTypeInput.value) {
                    if (window.Swal) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'OP Type Required',
                            text: 'Please select OP Type before importing.'
                        });
                    }
                    fileInput.value = '';
                    return;
                }

                triggerBtn.disabled = true;
                triggerBtn.classList.add('opacity-70', 'cursor-not-allowed');
                triggerBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Importing OP...';
                importForm.submit();
            });
        })();
    </script>
    <!-- Include JavaScript after all DOM elements -->
    @include('propertycard.js.javascript')
    @include('propertycard.partials.property_form_sweetalert')
    <script src="{{ asset('js/property-timeline-modal.js') }}"></script>
@endsection
